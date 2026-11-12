<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Positionable;
use Phil\DoctrineBehaviours\Contract\Sluggable;
use Phil\DoctrineBehaviours\PositionableEntity;
use Phil\DoctrineBehaviours\ScopedSluggableEntity;

/**
 * Ordered within its course and level, its slug unique within its course and locale; the fields of both scopes can be
 * changed by a test.
 */
#[ORM\Entity]
#[ORM\Table(name: 'lesson')]
#[ORM\UniqueConstraint(columns: ['course_id', 'locale', 'slug'])]
class Lesson implements Positionable, Sluggable
{
    use PositionableEntity {
        positionGroup as private noGroup;
    }
    use ScopedSluggableEntity;

    /** @var list<string> */
    public array $group = ['course', 'level'];

    /** @var list<string> */
    public array $scope = ['course', 'locale'];

    /** A relation held by two columns, which no group can name. */
    #[ORM\ManyToOne(targetEntity: Seat::class)]
    #[ORM\JoinColumn(name: 'seat_aisle', referencedColumnName: 'aisle', nullable: true)]
    #[ORM\JoinColumn(name: 'seat_number', referencedColumnName: 'number', nullable: true)]
    public ?Seat $seat = null;

    /** A relation to a record whose identifier is only given as it is stored. */
    #[ORM\ManyToOne(targetEntity: Ticket::class)]
    #[ORM\JoinColumn(name: 'ticket_id', nullable: true)]
    public ?Ticket $ticket = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\Column]
        public string $title,
        #[ORM\ManyToOne(targetEntity: Course::class, inversedBy: 'lessons')]
        #[ORM\JoinColumn(name: 'course_id', nullable: true)]
        public ?Course $course = null,
        #[ORM\Column(nullable: true, enumType: Level::class)]
        public ?Level $level = null,
        #[ORM\Column(length: 5)]
        public string $locale = 'en',
        #[ORM\ManyToOne(targetEntity: Badge::class)]
        #[ORM\JoinColumn(name: 'badge_id', referencedColumnName: 'course_id', nullable: true)]
        public ?Badge $badge = null,
    ) {
    }

    public function positionGroup(): array
    {
        return $this->group;
    }

    public function slugScope(): array
    {
        return $this->scope;
    }

    public function slugSource(): string
    {
        return $this->title;
    }
}
