<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * The group of lessons.
 */
#[ORM\Entity]
#[ORM\Table(name: 'course')]
class Course
{
    /** @var Collection<int, Lesson> */
    #[ORM\OneToMany(targetEntity: Lesson::class, mappedBy: 'course')]
    public Collection $lessons;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
    ) {
        $this->lessons = new ArrayCollection();
    }
}
