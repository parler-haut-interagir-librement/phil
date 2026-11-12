<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Positionable;
use Phil\DoctrineBehaviours\PositionableEntity;

/**
 * Identified by its course: a relation as identifier, which no scope and no move can compare.
 */
#[ORM\Entity]
#[ORM\Table(name: 'badge')]
class Badge implements Positionable
{
    use PositionableEntity;

    public function __construct(
        #[ORM\Id]
        #[ORM\OneToOne(targetEntity: Course::class)]
        #[ORM\JoinColumn(name: 'course_id')]
        public Course $course,
    ) {
    }
}
