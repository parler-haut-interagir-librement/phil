<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CertificatesBundle\Domain\Exception\InvalidValue;
use Phil\CertificatesBundle\Domain\ValueObject\Credits;
use Phil\CertificatesBundle\Domain\ValueObject\MemberId;
use Symfony\Component\Uid\Uuid;

/**
 * An activity of continuing education a member followed — a workshop, a conference, a course elsewhere —: its title,
 * the credits it earns, when it was completed, the course whose recertification it counts toward — every course when
 * none —, and the source it was recorded from, which the application names so that recording it twice records it once.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_certificates_activity')]
#[ORM\UniqueConstraint(columns: ['member', 'source'])]
#[ORM\Index(columns: ['member', 'completed_at'])]
final class LearningActivity
{
    #[ORM\Column(name: 'credits')]
    private readonly int $hundredths;

    public Credits $credits {
        get => Credits::fromHundredths($this->hundredths);
    }

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'member', length: MemberId::MAX_LENGTH)]
        public readonly string $member,
        #[ORM\Column(name: 'title', length: 255)]
        public readonly string $title,
        Credits $credits,
        #[ORM\Column(name: 'completed_at')]
        public readonly DateTimeImmutable $completedAt,
        #[ORM\ManyToOne(targetEntity: Course::class)]
        #[ORM\JoinColumn(name: 'course_id', nullable: true)]
        public readonly ?Course $course,
        #[ORM\Column(name: 'source', length: 255, nullable: true)]
        public readonly ?string $source,
    ) {
        $this->hundredths = $credits->hundredths;
    }

    /**
     * @param Course|null $course the course whose recertification it counts toward; every course when null
     * @param string|null $source where it was recorded from, unique for the member: an event, a session, a file
     */
    public static function record(MemberId $member, string $title, Credits $credits, DateTimeImmutable $completedAt, ?Course $course = null, ?string $source = null): self
    {
        if ('' === trim($title) || mb_strlen($title) > 255) {
            throw InvalidValue::because('The title of an activity has from 1 to 255 characters.');
        }

        if (0 === $credits->hundredths) {
            throw InvalidValue::because('An activity earns credits.');
        }

        if (null !== $source && ('' === trim($source) || mb_strlen($source) > 255)) {
            throw InvalidValue::because('The source of an activity has from 1 to 255 characters.');
        }

        return new self(Uuid::v7()->toRfc4122(), $member->value, $title, $credits, $completedAt, $course, $source);
    }

    /**
     * Whether it counts toward the recertification of the course: it names that course, or none.
     */
    public function countsToward(Course $course): bool
    {
        return !$this->course instanceof Course || $this->course->id === $course->id;
    }
}
