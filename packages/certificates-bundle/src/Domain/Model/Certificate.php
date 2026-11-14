<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Domain\Model;

use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CertificatesBundle\Domain\Exception\CertificateRefused;
use Phil\CertificatesBundle\Domain\Exception\InvalidValue;
use Phil\CertificatesBundle\Domain\Status\CertificateStatus;
use Phil\CertificatesBundle\Domain\ValueObject\MemberId;
use Phil\CertificatesBundle\Domain\ValueObject\VerificationCode;

/**
 * A certificate awarded for a course: the code it is found by in the register, the name printed on it, when it was
 * awarded and until when it is valid, its index in the issuer's status lists, and the signed Open Badges credential it
 * carries. It keeps no e-mail address: the credential holds the recipient's identity as a salted hash only.
 *
 * A listed certificate appears on its course's page in the register; any certificate is found by its code. A revoked
 * certificate keeps its reason, and its index is set in the status list; an expired one is not revoked, and its index
 * stays clear. It may name its holder by the stable identifier the application gives the member, by which credits of
 * continuing education are counted, and the certificate it renews, when it was awarded by a recertification. The
 * moment its holder was last reminded of its end is kept, so that each reminder is sent once.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_certificates_certificate')]
#[ORM\UniqueConstraint(columns: ['code'])]
#[ORM\UniqueConstraint(columns: ['status_index'])]
#[ORM\Index(columns: ['course_id', 'listed', 'awarded_at'])]
#[ORM\Index(columns: ['valid_until'])]
#[ORM\Index(columns: ['holder'])]
final class Certificate
{
    #[ORM\Column(name: 'revoked_at', nullable: true)]
    public private(set) ?DateTimeImmutable $revokedAt = null;

    #[ORM\Column(name: 'revocation_reason', length: 500, nullable: true)]
    public private(set) ?string $revocationReason = null;

    #[ORM\Column(name: 'reminded_at', nullable: true)]
    public private(set) ?DateTimeImmutable $remindedAt = null;

    #[ORM\Column(name: 'code', length: 12, options: ['fixed' => true])]
    private readonly string $codeValue;

    public VerificationCode $code {
        get => VerificationCode::fromString($this->codeValue);
    }

    /**
     * @param string $credential the signed credential: a compact JSON Web Token, or the JSON of a document with its proof
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        VerificationCode $code,
        #[ORM\ManyToOne(targetEntity: Course::class)]
        #[ORM\JoinColumn(name: 'course_id', nullable: false)]
        public readonly Course $course,
        #[ORM\Column(name: 'recipient_name', length: 255)]
        public readonly string $recipientName,
        #[ORM\Column(name: 'awarded_at')]
        public readonly DateTimeImmutable $awardedAt,
        #[ORM\Column(name: 'valid_until', nullable: true)]
        public readonly ?DateTimeImmutable $validUntil,
        #[ORM\Column(name: 'status_index')]
        public readonly int $statusIndex,
        #[ORM\Column(name: 'credential', type: 'text')]
        public readonly string $credential,
        #[ORM\Column(name: 'listed')]
        public readonly bool $listed,
        #[ORM\Column(name: 'holder', length: MemberId::MAX_LENGTH, nullable: true)]
        public readonly ?string $holder,
        #[ORM\OneToOne(targetEntity: self::class)]
        #[ORM\JoinColumn(name: 'renews_id', nullable: true)]
        public readonly ?self $renews,
    ) {
        $this->codeValue = $code->value;
    }

    /**
     * The certificate of a course, valid as `validUntil()` says.
     *
     * @param string           $id         the identifier the credential was issued under, `urn:uuid:<id>`
     * @param string           $credential the credential signed for it
     * @param MemberId|null    $holder     the member it is awarded to, when the application names one
     * @param Certificate|null $renews     the certificate this one renews, awarded by a recertification
     */
    public static function award(string $id, VerificationCode $code, Course $course, string $recipientName, DateTimeImmutable $awardedAt, int $statusIndex, string $credential, bool $listed = false, ?MemberId $holder = null, ?self $renews = null): self
    {
        if ('' === trim($recipientName) || mb_strlen($recipientName) > 255) {
            throw InvalidValue::because('The name on a certificate has from 1 to 255 characters.');
        }

        if ($statusIndex < 0) {
            throw InvalidValue::because('The status index of a certificate is not negative.');
        }

        if ($renews instanceof self && $renews->course->id !== $course->id) {
            throw InvalidValue::because('A certificate renews a certificate of its own course.');
        }

        return new self($id, $code, $course, $recipientName, $awardedAt, self::validUntil($course, $awardedAt, $renews), $statusIndex, $credential, $listed, $holder?->value, $renews);
    }

    /**
     * The end of the validity of a certificate of the course awarded at that moment, or null when it does not end: the
     * months of the course from the moment it is awarded — from the end of the certificate it renews, when that end
     * is still to come, so that renewing early loses nothing —, and never past the end of the course.
     */
    public static function validUntil(Course $course, DateTimeImmutable $awardedAt, ?self $renews = null): ?DateTimeImmutable
    {
        $start = $renews?->validUntil instanceof DateTimeImmutable ? max($renews->validUntil, $awardedAt) : $awardedAt;
        $until = null === $course->validityMonths ? null : $start->add(new DateInterval('P'.$course->validityMonths.'M'));

        if (!$course->endsAt instanceof DateTimeImmutable) {
            return $until;
        }

        return $until instanceof DateTimeImmutable ? min($until, $course->endsAt) : $course->endsAt;
    }

    /**
     * How many whole days are left before the certificate ends, from that moment: 0 on its last day, null when it does
     * not end or has ended.
     */
    public function daysLeft(DateTimeImmutable $at): ?int
    {
        if (!$this->validUntil instanceof DateTimeImmutable || $this->validUntil <= $at) {
            return null;
        }

        return intdiv($this->validUntil->getTimestamp() - $at->getTimestamp(), 86_400);
    }

    /**
     * Notes that the holder was reminded of the end of the certificate at that moment.
     */
    public function remind(DateTimeImmutable $at): void
    {
        $this->remindedAt = $at;
    }

    public function revoke(string $reason, DateTimeImmutable $at): void
    {
        if ($this->revokedAt instanceof DateTimeImmutable) {
            throw CertificateRefused::because(\sprintf('The certificate %s is already revoked.', $this->code->formatted()));
        }

        if ('' === trim($reason) || mb_strlen($reason) > 500) {
            throw InvalidValue::because('The reason of a revocation has from 1 to 500 characters.');
        }

        $this->revokedAt = $at;
        $this->revocationReason = $reason;
    }

    public function status(DateTimeImmutable $at): CertificateStatus
    {
        return match (true) {
            $this->revokedAt instanceof DateTimeImmutable => CertificateStatus::Revoked,
            $this->validUntil instanceof DateTimeImmutable && $this->validUntil <= $at => CertificateStatus::Expired,
            default => CertificateStatus::Valid,
        };
    }

    /**
     * Whether the credential is a compact JSON Web Token, rather than a document with its proof.
     */
    public function isToken(): bool
    {
        return !str_starts_with($this->credential, '{');
    }
}
