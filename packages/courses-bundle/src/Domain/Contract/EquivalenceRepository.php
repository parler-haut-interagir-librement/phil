<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Contract;

use Phil\CoursesBundle\Domain\Model\EquivalenceGroup;
use Phil\CoursesBundle\Domain\Model\EquivalentCourse;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\EquivalenceGroupId;

/**
 * The groups of equivalent courses and the course of each language in them. A course belongs to one group at most.
 */
interface EquivalenceRepository
{
    public function save(EquivalenceGroup $group): void;

    public function find(EquivalenceGroupId $id): ?EquivalenceGroup;

    /**
     * @return list<EquivalenceGroup> a page of the groups, by title
     */
    public function all(int $limit = 50, int $offset = 0): array;

    /**
     * Removes the group; its courses stay, in no group.
     */
    public function remove(EquivalenceGroupId $id): void;

    /**
     * Puts the course in its group, or changes its language there.
     */
    public function saveCourse(EquivalentCourse $course): void;

    /**
     * Takes the course out of its group.
     */
    public function removeCourse(CourseId $course): void;

    /**
     * The course in its group, or null when it is in none.
     */
    public function membership(CourseId $course): ?EquivalentCourse;

    /**
     * @return list<EquivalentCourse> the courses of the group, by language
     */
    public function courses(EquivalenceGroupId $group): array;
}
