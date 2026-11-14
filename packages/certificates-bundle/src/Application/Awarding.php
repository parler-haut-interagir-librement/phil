<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Application;

use JsonException;
use Phil\CertificatesBundle\Application\Contract\CertificateUrls;
use Phil\CertificatesBundle\Domain\Contract\CertificateRepository;
use Phil\CertificatesBundle\Domain\Contract\LearningActivityRepository;
use Phil\CertificatesBundle\Domain\Event\CertificateAwarded;
use Phil\CertificatesBundle\Domain\Event\CertificateRenewed;
use Phil\CertificatesBundle\Domain\Exception\CertificateRefused;
use Phil\CertificatesBundle\Domain\Exception\StatusIndexTaken;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\Status\CertificateStatus;
use Phil\CertificatesBundle\Domain\ValueObject\Attainment;
use Phil\CertificatesBundle\Domain\ValueObject\Credits;
use Phil\CertificatesBundle\Domain\ValueObject\MemberId;
use Phil\CertificatesBundle\Domain\ValueObject\Period;
use Phil\CertificatesBundle\Domain\ValueObject\VerificationCode;
use Phil\OpenBadges\Achievement;
use Phil\OpenBadges\Baking\Baked;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\Jwt\JwtVerifier;
use Phil\OpenBadges\Recipient;
use Phil\OpenBadgesBundle\Application\Securing;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SensitiveParameter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Uid\Uuid;

/**
 * Awards the certificate of a course to a learner whose attainment meets its criteria: a code for the register, the
 * next index in the status lists, and an Open Badges credential — its achievement the course, its recipient the
 * identity the learner gave, its validity the course's — signed as the issuer's configuration says.
 *
 * The identity is a decentralized identifier, an e-mail address or a URL; an address or a URL is kept in the
 * credential only as a salted hash, and nowhere else.
 *
 * With a lock factory, the status index is taken and the certificate stored under one lock, so that two awards at
 * once never reach for the same index. Without one — or with a lock store some server does not share —, the storage's
 * unique index refuses the second of them with `StatusIndexTaken`, and the award starts again with the next index, a
 * new code and a credential signed again, `ATTEMPTS` times at most. Once stored, `CertificateAwarded` is dispatched
 * when there is a dispatcher.
 *
 * A recertification awards a new certificate of the same course, linked to the one it renews, to the same recipient:
 * the identity given again must be the one the first credential holds. It asks for the credits of continuing
 * education the course requires, gathered by the holder since the first certificate was awarded, or, for a course
 * that requires none, an attainment that meets its criteria. A revoked certificate, or one already renewed, is not
 * renewed. The new certificate is valid from the end of the first when that end is still to come; `CertificateAwarded`
 * then `CertificateRenewed` are dispatched.
 */
final readonly class Awarding
{
    /**
     * The name of the lock the status index is taken under: one for the whole register.
     */
    public const string LOCK = 'phil_certificates.status_index';

    /**
     * How many times an award is attempted when the storage finds its status index taken.
     */
    public const int ATTEMPTS = 3;

    public function __construct(
        private Courses $courses,
        private CertificateRepository $certificates,
        private CredentialIssuer $credentials,
        private Securing $securing,
        private CertificateUrls $urls,
        private ClockInterface $clock,
        private StatusListLayout $layout = new StatusListLayout(),
        private ?LockFactory $locks = null,
        private ?EventDispatcherInterface $events = null,
        private ?LearningActivityRepository $activities = null,
    ) {
    }

    /**
     * @param string|null $holder the stable identifier of the member, by which the credits of continuing education of a
     *                            recertification are counted; never an e-mail address
     */
    public function award(string $course, string $recipientName, #[SensitiveParameter] string $identity, Attainment $attainment = new Attainment(), bool $listed = false, ?string $holder = null): Certificate
    {
        $course = $this->open($course);
        $this->checkCriteria($course, $attainment);

        return $this->store($course, $recipientName, $identity, $listed, null === $holder ? null : MemberId::fromString($holder), null);
    }

    /**
     * Awards the certificate that renews the one of that code.
     *
     * @param Attainment|null $attainment checked against the criteria when given; required for a course that asks for
     *                                    no credits
     */
    public function recertify(string $code, #[SensitiveParameter] string $identity, ?Attainment $attainment = null, ?bool $listed = null): Certificate
    {
        $previous = $this->certificates->findByCode(VerificationCode::fromString($code)->value) ?? throw CertificateRefused::because(\sprintf('There is no certificate %s.', $code));
        $now = $this->clock->now();

        if (CertificateStatus::Revoked === $previous->status($now)) {
            throw CertificateRefused::because(\sprintf('The certificate %s is revoked: it is not renewed.', $previous->code->formatted()));
        }

        if ($this->certificates->findRenewalOf($previous) instanceof Certificate) {
            throw CertificateRefused::because(\sprintf('The certificate %s is already renewed.', $previous->code->formatted()));
        }

        if (!$this->recipientOf($previous, $identity)) {
            throw CertificateRefused::because(\sprintf('The identity given is not the recipient of the certificate %s.', $previous->code->formatted()));
        }

        $course = $this->open($previous->course->slug);
        $required = $course->recertificationCredits;

        if (!$required instanceof Credits && !$attainment instanceof Attainment) {
            throw CertificateRefused::because(\sprintf('The course "%s" asks for no credits: a recertification shows an attainment that meets its criteria.', $course->slug));
        }

        if ($attainment instanceof Attainment) {
            $this->checkCriteria($course, $attainment);
        }

        if ($required instanceof Credits) {
            if (null === $previous->holder || !$this->activities instanceof LearningActivityRepository) {
                throw CertificateRefused::because(\sprintf('The course "%s" asks for credits of continuing education, and the certificate %s names no holder they are counted for.', $course->slug, $previous->code->formatted()));
            }

            $gathered = $this->activities->total(MemberId::fromString($previous->holder), new Period($previous->awardedAt, $now->modify('+1 second')), $course);

            if (!$gathered->covers($required)) {
                throw CertificateRefused::because(\sprintf('The holder of the certificate %s gathered %s of the %s credits the course "%s" asks for.', $previous->code->formatted(), $gathered->formatted(), $required->formatted(), $course->slug));
            }
        }

        $holder = null === $previous->holder ? null : MemberId::fromString($previous->holder);
        $certificate = $this->store($course, $previous->recipientName, $identity, $listed ?? $previous->listed, $holder, $previous);
        $this->events?->dispatch(new CertificateRenewed($certificate->id, $certificate->code->value, $course->slug, $previous->id));

        return $certificate;
    }

    /**
     * A course that awards certificates: neither retired nor ended.
     */
    private function open(string $slug): Course
    {
        $course = $this->courses->get($slug);

        if ($course->retired) {
            throw CertificateRefused::because(\sprintf('The course "%s" is retired.', $course->slug));
        }

        if ($course->hasEnded($this->clock->now())) {
            throw CertificateRefused::because(\sprintf('The course "%s" has ended.', $course->slug));
        }

        return $course;
    }

    private function checkCriteria(Course $course, Attainment $attainment): void
    {
        $unmet = $course->criteria()->unmet($attainment);

        if ([] !== $unmet) {
            throw CertificateRefused::because(\sprintf('The criteria of the course "%s" are not met: %s.', $course->slug, implode(', ', $unmet)));
        }
    }

    /**
     * Whether the identity is the one the certificate's credential was issued to. The credential is the one the
     * bundle signed and stored: it is read without its proof being verified again.
     */
    private function recipientOf(Certificate $certificate, #[SensitiveParameter] string $identity): bool
    {
        try {
            $credential = Baked::isJwt($certificate->credential) ? JwtVerifier::peek($certificate->credential)[1] : json_decode($certificate->credential, true, 64, \JSON_THROW_ON_ERROR);
        } catch (InvalidCredential|JsonException) {
            return false;
        }

        $subject = \is_array($credential) ? $credential['credentialSubject'] ?? null : null;

        return \is_array($subject) && Recipient::identifies($subject, $identity);
    }

    private function store(Course $course, string $recipientName, #[SensitiveParameter] string $identity, bool $listed, ?MemberId $holder, ?Certificate $renews): Certificate
    {
        for ($attempt = 1;; ++$attempt) {
            try {
                $certificate = $this->attempt($course, $recipientName, $identity, $listed, $holder, $renews);
            } catch (StatusIndexTaken $statusIndexTaken) {
                if (self::ATTEMPTS === $attempt) {
                    throw $statusIndexTaken;
                }

                // The storage that refused may have let go of what it held: the course, and the certificate renewed,
                // are read again.
                $course = $this->courses->get($course->slug);
                $renews = $renews instanceof Certificate ? $this->certificates->findByCode($renews->code->value) ?? throw CertificateRefused::because(\sprintf('There is no certificate %s.', $renews->code->formatted())) : null;

                continue;
            }

            $this->events?->dispatch(new CertificateAwarded($certificate->id, $certificate->code->value, $course->slug));

            return $certificate;
        }
    }

    private function attempt(Course $course, string $recipientName, #[SensitiveParameter] string $identity, bool $listed, ?MemberId $holder, ?Certificate $renews): Certificate
    {
        $id = Uuid::v7()->toRfc4122();
        $code = VerificationCode::generate();
        $lock = $this->locks?->createLock(self::LOCK, 30.0);
        $lock?->acquire(true);

        try {
            $index = $this->certificates->nextStatusIndex();
            [$list, $listIndex] = $this->layout->locate($index);
            $awardedAt = $this->clock->now();

            $credential = $this->credentials->credential(
                new Achievement($this->urls->course($course->slug), $course->name, $course->description, $course->narrative, $course->image),
                $this->recipient($identity),
                'urn:uuid:'.$id,
                $awardedAt,
                Certificate::validUntil($course, $awardedAt, $renews),
                $this->urls->statusEntry($list, $listIndex),
            );

            $certificate = Certificate::award($id, $code, $course, $recipientName, $awardedAt, $index, $this->securing->secure($credential)->content, $listed, $holder, $renews);
            $this->certificates->save($certificate);
        } finally {
            $lock?->release();
        }

        return $certificate;
    }

    private function recipient(#[SensitiveParameter] string $identity): Recipient
    {
        $salt = sodium_bin2hex(random_bytes(8));

        return match (true) {
            str_starts_with($identity, 'did:') => Recipient::did($identity),
            str_contains($identity, '@') => Recipient::email($identity, $salt),
            default => Recipient::url($identity, $salt),
        };
    }
}
