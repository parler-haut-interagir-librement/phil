<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CertificatesBundle\Domain\Exception\InvalidValue;
use Phil\CertificatesBundle\Domain\ValueObject\Credits;
use Phil\CertificatesBundle\Domain\ValueObject\OpaqueId;
use Phil\CertificatesBundle\Domain\ValueObject\PassCriteria;
use Symfony\Component\Uid\Uuid;

/**
 * A course or a training a certificate recognizes: its slug in the register's URLs, its name and description, the
 * narrative of its criteria as the certificate's credential states them, the criteria a learner's attainment is
 * checked against, how many months a certificate stays valid — forever when none —, the image of its badge, the
 * credits of continuing education a holder gathers to be recertified, the moment the course itself ends, and the
 * completion source its certificates are awarded on.
 *
 * A retired course awards no more certificates, and neither does a course past its end; those already awarded stay as
 * they are. No certificate of a course that ends is valid past that end.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_certificates_course')]
#[ORM\UniqueConstraint(columns: ['slug'])]
final class Course
{
    public const string SLUG = '[a-z0-9]+(?:-[a-z0-9]+)*';

    #[ORM\Column(name: 'name', length: 255)]
    public private(set) string $name = '';

    #[ORM\Column(name: 'description', type: 'text')]
    public private(set) string $description = '';

    #[ORM\Column(name: 'narrative', type: 'text')]
    public private(set) string $narrative = '';

    #[ORM\Column(name: 'minimum_score', type: 'smallint', nullable: true)]
    private ?int $minimumScore = null;

    #[ORM\Column(name: 'minimum_minutes', nullable: true)]
    private ?int $minimumMinutes = null;

    /** @var list<string> */
    #[ORM\Column(name: 'required_modules', type: 'json')]
    private array $requiredModules = [];

    #[ORM\Column(name: 'validity_months', type: 'smallint', nullable: true)]
    public private(set) ?int $validityMonths = null;

    #[ORM\Column(name: 'image', length: 2048, nullable: true)]
    public private(set) ?string $image = null;

    #[ORM\Column(name: 'retired')]
    public private(set) bool $retired = false;

    #[ORM\Column(name: 'recertification_credits', nullable: true)]
    private ?int $recertificationHundredths = null;

    /**
     * The credits of continuing education a holder gathers over the validity of a certificate to be recertified;
     * null when recertification asks for an attainment instead.
     */
    public ?Credits $recertificationCredits {
        get => null === $this->recertificationHundredths ? null : Credits::fromHundredths($this->recertificationHundredths);
    }

    /**
     * The moment the course ends: no certificate is awarded from then on, and none is valid past it.
     */
    #[ORM\Column(name: 'ends_at', type: 'datetime_utc_immutable', nullable: true)]
    public private(set) ?DateTimeImmutable $endsAt = null;

    /**
     * The key of the course in another system that knows who completed it, asked through `CourseCompletions` before
     * a certificate is awarded; null when the application alone decides who passed.
     */
    #[ORM\Column(name: 'completion_source', length: OpaqueId::MAX_LENGTH, nullable: true)]
    public private(set) ?string $completionSource = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'slug', length: 64)]
        public readonly string $slug,
    ) {
    }

    /**
     * @param int|null    $validityMonths   how many months a certificate stays valid, from 1 to 1200; forever when null
     * @param string|null $image            the absolute URL of the badge's image
     * @param string|null $completionSource the key of the course in the system its completions are asked of
     */
    public static function define(string $slug, string $name, string $description, string $narrative, PassCriteria $criteria = new PassCriteria(), ?int $validityMonths = null, ?string $image = null, ?Credits $recertificationCredits = null, ?DateTimeImmutable $endsAt = null, ?string $completionSource = null): self
    {
        if (\strlen($slug) > 64 || 1 !== preg_match('/^'.self::SLUG.'$/D', $slug)) {
            throw InvalidValue::because(\sprintf('The slug of a course is up to 64 lower-case letters, digits and single dashes; "%s" is not.', $slug));
        }

        $course = new self(Uuid::v7()->toRfc4122(), $slug);
        $course->revise($name, $description, $narrative, $criteria, $validityMonths, $image, $recertificationCredits, $endsAt);
        $course->changeCompletionSource($completionSource);

        return $course;
    }

    /**
     * Changes what the course says and asks; certificates already awarded keep the credential they were given. The
     * completion source stays as it is.
     */
    public function revise(string $name, string $description, string $narrative, PassCriteria $criteria = new PassCriteria(), ?int $validityMonths = null, ?string $image = null, ?Credits $recertificationCredits = null, ?DateTimeImmutable $endsAt = null): void
    {
        if ('' === trim($name) || mb_strlen($name) > 255) {
            throw InvalidValue::because('The name of a course has from 1 to 255 characters.');
        }

        if ('' === trim($narrative)) {
            throw InvalidValue::because('A course states the narrative of its criteria.');
        }

        if (null !== $validityMonths && ($validityMonths < 1 || $validityMonths > 1200)) {
            throw InvalidValue::because(\sprintf('A certificate is valid from 1 to 1200 months; %d is not.', $validityMonths));
        }

        if (0 === $recertificationCredits?->hundredths) {
            throw InvalidValue::because('A recertification asks for more than 0 credits, or for none at all.');
        }

        if (null !== $image && (\strlen($image) > 2048 || 1 !== preg_match('#^https?://[^\s/]+\S*$#D', $image))) {
            throw InvalidValue::because(\sprintf('The image of a course is an absolute HTTP URL; "%s" is not.', $image));
        }

        $this->name = $name;
        $this->description = $description;
        $this->narrative = $narrative;
        $this->minimumScore = $criteria->minimumScore;
        $this->minimumMinutes = $criteria->minimumMinutes;
        $this->requiredModules = $criteria->requiredModules;
        $this->validityMonths = $validityMonths;
        $this->image = $image;
        $this->recertificationHundredths = $recertificationCredits?->hundredths;
        $this->endsAt = $endsAt;
    }

    /**
     * Names the key of the course in the system its completions are asked of, or none: from then on, a certificate is
     * awarded only to a member that system says completed it. Certificates already awarded stay as they are.
     */
    public function changeCompletionSource(?string $source): void
    {
        $this->completionSource = null === $source ? null : OpaqueId::fromString($source, 'completion source of a course')->value;
    }

    /**
     * Whether the course has ended at that moment.
     */
    public function hasEnded(DateTimeImmutable $at): bool
    {
        return $this->endsAt instanceof DateTimeImmutable && $this->endsAt <= $at;
    }

    public function criteria(): PassCriteria
    {
        return new PassCriteria($this->minimumScore, $this->minimumMinutes, $this->requiredModules);
    }

    public function retire(): void
    {
        $this->retired = true;
    }

    public function reopen(): void
    {
        $this->retired = false;
    }
}
