<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application;

use Phil\CoursesBundle\Domain\Contract\CourseRepository;
use Phil\CoursesBundle\Domain\Contract\EquivalenceRepository;
use Phil\CoursesBundle\Domain\Equivalence\LanguageTag;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Exception\UnknownRecord;
use Phil\CoursesBundle\Domain\Model\EquivalenceGroup;
use Phil\CoursesBundle\Domain\Model\EquivalentCourse;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\EquivalenceGroupId;
use Psr\Clock\ClockInterface;

/**
 * The groups of equivalent courses: one training given as several courses, one course per language. A course belongs
 * to one group at most, and a group holds one course per language. The course a learner takes is chosen by their
 * language; a completion of one course of the group is recognised for every other, which `Completions` answers.
 */
final readonly class Equivalences
{
    public const int MAX_COURSES = 50;

    public function __construct(
        private EquivalenceRepository $groups,
        private CourseRepository $courses,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws InvalidCourse when the title is not one a group holds
     */
    public function create(string $title): EquivalenceGroup
    {
        $group = new EquivalenceGroup(EquivalenceGroupId::generate(), $title, $this->clock->now());
        $this->groups->save($group);

        return $group;
    }

    /**
     * @throws UnknownRecord when the group is not known
     */
    public function get(EquivalenceGroupId $id): EquivalenceGroup
    {
        return $this->groups->find($id) ?? throw UnknownRecord::of('equivalence group', $id->value);
    }

    /**
     * @return list<EquivalenceGroup> a page of the groups, by title
     */
    public function all(int $limit = 50, int $offset = 0): array
    {
        return $this->groups->all($limit, $offset);
    }

    /**
     * @throws UnknownRecord when the group is not known
     * @throws InvalidCourse when the title is not one a group holds
     */
    public function rename(EquivalenceGroupId $id, string $title): EquivalenceGroup
    {
        $group = $this->get($id);
        $group->rename($title);

        $this->groups->save($group);

        return $group;
    }

    /**
     * Removes the group; its courses stay, each on its own, and the completions already recognised are not undone.
     */
    public function dissolve(EquivalenceGroupId $id): void
    {
        $this->groups->remove($id);
    }

    /**
     * Puts the course in the group, given in that language, or changes its language there.
     *
     * @throws UnknownRecord when the group or the course is not known
     * @throws InvalidCourse when the language is not a tag, the course is in another group, another course of the
     *                       group has that language, or the group is full
     */
    public function add(EquivalenceGroupId $group, CourseId $course, string $language): EquivalentCourse
    {
        $this->get($group);
        $this->courses->find($course) ?? throw UnknownRecord::of('course', $course->value);
        $member = new EquivalentCourse($group, $course, $language);
        $current = $this->groups->membership($course);

        if ($current instanceof EquivalentCourse && $current->group->value !== $group->value) {
            throw InvalidCourse::because(\sprintf('The course "%s" is already in another group of equivalent courses.', $course->value));
        }

        $others = array_values(array_filter($this->groups->courses($group), static fn (EquivalentCourse $other): bool => !$other->course->equals($course)));

        if (\count($others) >= self::MAX_COURSES) {
            throw InvalidCourse::because(\sprintf('A group holds %d equivalent courses at most.', self::MAX_COURSES));
        }

        if (array_any($others, static fn (EquivalentCourse $other): bool => $other->language === $member->language)) {
            throw InvalidCourse::because(\sprintf('The group already holds a course in "%s".', $member->language));
        }

        $this->groups->saveCourse($member);

        return $member;
    }

    /**
     * Takes the course out of its group; the completions already recognised are not undone.
     */
    public function remove(CourseId $course): void
    {
        $this->groups->removeCourse($course);
    }

    /**
     * The course in its group, or null when it is in none.
     */
    public function membership(CourseId $course): ?EquivalentCourse
    {
        return $this->groups->membership($course);
    }

    /**
     * @return list<EquivalentCourse> the courses of the group, by language
     */
    public function courses(EquivalenceGroupId $group): array
    {
        return $this->groups->courses($group);
    }

    /**
     * @return list<CourseId> the other courses of the course's group, by language; none when it is in no group
     */
    public function equivalents(CourseId $course): array
    {
        $member = $this->groups->membership($course);

        if (!$member instanceof EquivalentCourse) {
            return [];
        }

        return array_values(array_map(
            static fn (EquivalentCourse $other): CourseId => $other->course,
            array_filter($this->groups->courses($member->group), static fn (EquivalentCourse $other): bool => !$other->course->equals($course)),
        ));
    }

    /**
     * The course of the group given in the learner's language: the course of that very tag, else the first course of
     * its primary language, else the course asked for — also when the course is in no group, or the language is not a
     * tag.
     */
    public function courseFor(CourseId $course, ?string $language): CourseId
    {
        $member = $this->groups->membership($course);

        if (!$member instanceof EquivalentCourse || null === $language) {
            return $course;
        }

        try {
            $tag = LanguageTag::normalize($language);
        } catch (InvalidCourse) {
            return $course;
        }

        $members = $this->groups->courses($member->group);
        $chosen = array_find($members, static fn (EquivalentCourse $other): bool => $other->language === $tag)
            ?? array_find($members, static fn (EquivalentCourse $other): bool => LanguageTag::primary($other->language) === LanguageTag::primary($tag));

        return $chosen->course ?? $course;
    }
}
