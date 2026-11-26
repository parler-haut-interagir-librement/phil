<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Catalogue;

use Phil\CoursesBundle\Application\Completion;
use Phil\CoursesBundle\Application\Completions;
use Phil\CoursesBundle\Application\Contract\LearnerUnits;
use Phil\CoursesBundle\Application\Enrollments;
use Phil\CoursesBundle\Domain\Catalogue\CatalogueAudience;
use Phil\CoursesBundle\Domain\Catalogue\CatalogueSort;
use Phil\CoursesBundle\Domain\Catalogue\OfferedCourse;
use Phil\CoursesBundle\Domain\Catalogue\PriceFilter;
use Phil\CoursesBundle\Domain\Contract\AssignmentRepository;
use Phil\CoursesBundle\Domain\Contract\CatalogueRepository;
use Phil\CoursesBundle\Domain\Contract\CourseRepository;
use Phil\CoursesBundle\Domain\Equivalence\LanguageTag;
use Phil\CoursesBundle\Domain\Exception\EnrollmentRefused;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Exception\UnknownRecord;
use Phil\CoursesBundle\Domain\Model\Catalogue;
use Phil\CoursesBundle\Domain\Model\CatalogueEntry;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\ValueObject\CatalogueId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\CoursePrice;
use Phil\CoursesBundle\Domain\ValueObject\EquivalenceGroupId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Psr\Clock\ClockInterface;

/**
 * The catalogues of courses: a public catalogue shown to everyone, and the catalogues of the units of an organization,
 * shown to the members of each unit and of the units below it as `LearnerUnits` names them. A catalogue places its
 * courses in an order — each added at the end or before another —, opens each to self-enrollment or not, sells it at
 * a price or gives it, and tags it. A learner lists a catalogue filtered by words, tag and price, in the order placed,
 * by title, the newest first or by price, a page at a time, a course of a group of equivalent courses listed once in
 * their language; they enroll themselves in a free course open to it, and are enrolled in a course sold once they paid
 * for it. A catalogue not shown to a learner is unknown to them.
 */
final readonly class Catalogues
{
    public const int MAX_ENTRIES = 1_000;

    public const int PER_PAGE = 20;

    /** How many units of a learner the catalogues shown to them are looked up by. */
    public const int MAX_UNITS = 500;

    public function __construct(
        private CatalogueRepository $catalogues,
        private CourseRepository $courses,
        private Enrollments $enrollments,
        private Completions $completions,
        private ClockInterface $clock,
        private ?LearnerUnits $units = null,
        private ?AssignmentRepository $assignments = null,
    ) {
    }

    /**
     * @throws InvalidCourse when the title or the description is not one a catalogue holds, or the unit does not match
     *                       the audience
     */
    public function create(string $title, string $description = '', CatalogueAudience $audience = CatalogueAudience::Public, ?string $unit = null): Catalogue
    {
        $catalogue = new Catalogue(CatalogueId::generate(), $title, $this->clock->now(), $description, $audience, $unit);
        $this->catalogues->save($catalogue);

        return $catalogue;
    }

    /**
     * @throws UnknownRecord when the catalogue is not known
     */
    public function get(CatalogueId $id): Catalogue
    {
        return $this->catalogues->find($id) ?? throw UnknownRecord::of('catalogue', $id->value);
    }

    /**
     * @return list<Catalogue> a page of every catalogue, by title, for their editors
     */
    public function all(int $limit = 50, int $offset = 0): array
    {
        return $this->catalogues->all(max(1, min($limit, 200)), max(0, $offset));
    }

    /**
     * @throws UnknownRecord when the catalogue is not known
     * @throws InvalidCourse when the title or the description is not one a catalogue holds
     */
    public function rename(CatalogueId $id, string $title, string $description): Catalogue
    {
        $catalogue = $this->get($id);
        $catalogue->rename($title, $description);

        $this->catalogues->save($catalogue);

        return $catalogue;
    }

    /**
     * Shows the catalogue to everyone, or to the members of the unit and of the units below it.
     *
     * @throws UnknownRecord when the catalogue is not known
     * @throws InvalidCourse when the unit does not match the audience
     */
    public function showTo(CatalogueId $id, CatalogueAudience $audience, ?string $unit = null): Catalogue
    {
        $catalogue = $this->get($id);
        $catalogue->showTo($audience, $unit);

        $this->catalogues->save($catalogue);

        return $catalogue;
    }

    /**
     * Removes the catalogue with its entries; the learners stay enrolled in its courses.
     */
    public function remove(CatalogueId $id): void
    {
        $this->catalogues->remove($id);
    }

    /**
     * @return list<CatalogueEntry> the courses of the catalogue, in their order
     */
    public function entries(CatalogueId $catalogue): array
    {
        return $this->catalogues->entries($catalogue);
    }

    public function entry(CatalogueId $catalogue, CourseId $course): ?CatalogueEntry
    {
        return $this->catalogues->entry($catalogue, $course);
    }

    /**
     * Offers the course in the catalogue, or changes how it offers it: added after the other courses, or before the
     * course `$before`; a course offered already keeps its place unless `$before` names another.
     *
     * @param list<string> $tags
     *
     * @throws UnknownRecord when the catalogue or the course is not known
     * @throws InvalidCourse when a tag is not one an entry holds, `$before` is not in the catalogue, or the catalogue is
     *                       full
     */
    public function offer(CatalogueId $catalogue, CourseId $course, bool $selfEnrollment = true, ?CoursePrice $price = null, array $tags = [], ?CourseId $before = null): CatalogueEntry
    {
        $this->get($catalogue);
        $this->courses->find($course) ?? throw UnknownRecord::of('course', $course->value);
        $entries = $this->catalogues->entries($catalogue);
        $at = $this->index($entries, $course);
        $entry = new CatalogueEntry($catalogue, $course, $at ?? \count($entries), $selfEnrollment, $price, $tags);

        if (null !== $at && !$before instanceof CourseId) {
            $this->catalogues->saveEntries($catalogue, array_map(static fn (CatalogueEntry $other): CatalogueEntry => $other->course->equals($course) ? $entry : $other, $entries));

            return $entry;
        }

        if (null === $at && \count($entries) >= self::MAX_ENTRIES) {
            throw InvalidCourse::because(\sprintf('A catalogue offers %d courses at most.', self::MAX_ENTRIES));
        }

        return $this->place($entries, $entry, $before);
    }

    /**
     * Moves the course of the catalogue before the course `$before`, or after every other when it is null.
     *
     * @throws UnknownRecord when the catalogue is not known, or the course is not in it
     * @throws InvalidCourse when `$before` is not in the catalogue
     */
    public function move(CatalogueId $catalogue, CourseId $course, ?CourseId $before = null): CatalogueEntry
    {
        $this->get($catalogue);
        $entries = $this->catalogues->entries($catalogue);
        $at = $this->index($entries, $course) ?? throw UnknownRecord::of('catalogue entry', $course->value);

        return $this->place($entries, $entries[$at], $before);
    }

    /**
     * Takes the course out of the catalogue; the learners stay enrolled in it.
     *
     * @throws UnknownRecord when the catalogue is not known
     */
    public function withdraw(CatalogueId $catalogue, CourseId $course): void
    {
        $this->get($catalogue);
        $entries = array_values(array_filter($this->catalogues->entries($catalogue), static fn (CatalogueEntry $entry): bool => !$entry->course->equals($course)));
        $this->catalogues->saveEntries($catalogue, $this->renumber($entries));
    }

    /**
     * @return list<Catalogue> the catalogues shown to the learner, by title: the public ones, and the ones of their
     *                         units; the public ones only to a visitor signed out
     */
    public function shownTo(?LearnerId $learner): array
    {
        return $this->catalogues->shownTo($this->unitsOf($learner));
    }

    /**
     * The catalogue, when it is shown to the learner.
     *
     * @throws UnknownRecord when the catalogue is not known, or not shown to the learner
     */
    public function shown(CatalogueId $id, ?LearnerId $learner): Catalogue
    {
        $catalogue = $this->get($id);

        if (!$catalogue->isPublic() && !$catalogue->isShownTo($this->unitsOf($learner))) {
            throw UnknownRecord::of('catalogue', $id->value);
        }

        return $catalogue;
    }

    /**
     * A page of the catalogue as the query lists it, with where the learner stands in each course of the page.
     *
     * @throws UnknownRecord when the catalogue is not known, or not shown to the learner
     */
    public function browse(CatalogueId $id, ?LearnerId $learner, CatalogueQuery $query): CataloguePage
    {
        $catalogue = $this->shown($id, $learner);
        $offered = $this->catalogues->offered($id);
        $tags = array_values(array_unique(array_merge(...array_map(static fn (OfferedCourse $course): array => $course->entry->tags, $offered))));
        sort($tags, \SORT_STRING);
        $kept = $this->sort($this->filter($this->once($offered, $query->language), $query), $query->sort);
        $total = \count($kept);
        $pages = max(1, intdiv($total + self::PER_PAGE - 1, self::PER_PAGE));
        $query = $query->onPage(min($query->page, $pages));
        $items = $this->items(\array_slice($kept, ($query->page - 1) * self::PER_PAGE, self::PER_PAGE), $learner);

        return new CataloguePage($catalogue, $query, $items, $total, $pages, $tags);
    }

    /**
     * Enrolls the learner in a course of a catalogue shown to them, open to self-enrollment and free there, or gives
     * back the enrollment they have.
     *
     * @throws UnknownRecord     when the catalogue is not known, or not shown to the learner
     * @throws EnrollmentRefused when the course is not in the catalogue or not open to self-enrollment, or is sold there
     */
    public function enroll(CatalogueId $catalogue, CourseId $course, LearnerId $learner): Enrollment
    {
        $this->shown($catalogue, $learner);
        $entry = $this->catalogues->entry($catalogue, $course) ?? throw EnrollmentRefused::because('This course is not in the catalogue.');
        $enrollment = $this->enrollments->of($course, $learner);

        if ($enrollment instanceof Enrollment) {
            return $enrollment;
        }

        if (!$entry->selfEnrollment) {
            throw EnrollmentRefused::because('This course is not open to self-enrollment.');
        }

        if (!$entry->isFree()) {
            throw EnrollmentRefused::purchase();
        }

        return $this->enrollments->enroll($course, $learner);
    }

    /**
     * Enrolls the learner in a course the catalogue sells, once they paid for it, or gives back the enrollment they
     * have: what the store's announcement of a paid order calls.
     *
     * @throws EnrollmentRefused when the course is not in the catalogue, or is free there
     * @throws UnknownRecord     when the course is not known
     */
    public function grantPurchase(CatalogueId $catalogue, CourseId $course, LearnerId $learner): Enrollment
    {
        $entry = $this->catalogues->entry($catalogue, $course) ?? throw EnrollmentRefused::because('This course is not in the catalogue.');

        if ($entry->isFree()) {
            throw EnrollmentRefused::because('This course is not sold in the catalogue.');
        }

        return $this->enrollments->enroll($course, $learner);
    }

    /**
     * @return list<string>
     */
    private function unitsOf(?LearnerId $learner): array
    {
        if (!$learner instanceof LearnerId || !$this->units instanceof LearnerUnits) {
            return [];
        }

        return \array_slice(array_values(array_unique($this->units->units($learner))), 0, self::MAX_UNITS);
    }

    /**
     * The courses with each group of equivalent courses listed once: the course of the language's very tag, else the
     * first of its primary language, else the first placed. Without a language, every course.
     *
     * @param list<OfferedCourse> $offered
     *
     * @return list<OfferedCourse>
     */
    private function once(array $offered, ?string $language): array
    {
        if (null === $language) {
            return $offered;
        }

        $chosen = [];

        foreach ($offered as $course) {
            if (null === $course->group) {
                continue;
            }

            $group = $course->group->value;
            $rank = match (true) {
                $course->language === $language => 0,
                null !== $course->language && LanguageTag::primary($course->language) === LanguageTag::primary($language) => 1,
                default => 2,
            };

            if (!isset($chosen[$group]) || $rank < $chosen[$group][0]) {
                $chosen[$group] = [$rank, $course->course->id->value];
            }
        }

        return array_values(array_filter($offered, static fn (OfferedCourse $course): bool => !$course->group instanceof EquivalenceGroupId || $chosen[$course->group->value][1] === $course->course->id->value));
    }

    /**
     * @param list<OfferedCourse> $offered
     *
     * @return list<OfferedCourse>
     */
    private function filter(array $offered, CatalogueQuery $query): array
    {
        $text = null === $query->text ? null : mb_strtolower($query->text);

        return array_values(array_filter($offered, static fn (OfferedCourse $course): bool => match (true) {
            null !== $query->tag && !$course->entry->hasTag($query->tag) => false,
            PriceFilter::Free === $query->price && !$course->entry->isFree() => false,
            PriceFilter::Paid === $query->price && $course->entry->isFree() => false,
            null !== $text => str_contains(mb_strtolower($course->course->title), $text) || str_contains(mb_strtolower($course->course->description), $text),
            default => true,
        }));
    }

    /**
     * Sorts the courses, those that compare equal kept in the order placed.
     *
     * @param list<OfferedCourse> $offered
     *
     * @return list<OfferedCourse>
     */
    private function sort(array $offered, CatalogueSort $sort): array
    {
        $compare = match ($sort) {
            CatalogueSort::Placed => null,
            CatalogueSort::Title => static fn (OfferedCourse $a, OfferedCourse $b): int => strnatcasecmp(mb_strtolower($a->course->title), mb_strtolower($b->course->title)),
            CatalogueSort::Newest => static fn (OfferedCourse $a, OfferedCourse $b): int => $b->course->createdAt <=> $a->course->createdAt,
            CatalogueSort::Price => static fn (OfferedCourse $a, OfferedCourse $b): int => [$a->entry->price->currency ?? '', $a->entry->price->minorUnits ?? 0] <=> [$b->entry->price->currency ?? '', $b->entry->price->minorUnits ?? 0],
        };

        if (null !== $compare) {
            usort($offered, $compare);
        }

        return $offered;
    }

    /**
     * @param list<OfferedCourse> $offered
     *
     * @return list<CatalogueItem>
     */
    private function items(array $offered, ?LearnerId $learner): array
    {
        if (!$learner instanceof LearnerId) {
            return array_map(static fn (OfferedCourse $course): CatalogueItem => new CatalogueItem($course), $offered);
        }

        $assigned = [];

        foreach ($this->assignments?->obligationsOf($learner) ?? [] as $obligation) {
            if (null === $obligation->completedAt) {
                $assigned[$obligation->course->value] = true;
            }
        }

        return array_map(fn (OfferedCourse $course): CatalogueItem => new CatalogueItem(
            $course,
            $this->enrollments->of($course->course->id, $learner),
            $this->completions->completion($course->course->id, $learner) instanceof Completion,
            isset($assigned[$course->course->id->value]),
        ), $offered);
    }

    /**
     * @param list<CatalogueEntry> $entries
     *
     * @throws InvalidCourse when `$before` is not in the catalogue
     */
    private function place(array $entries, CatalogueEntry $entry, ?CourseId $before): CatalogueEntry
    {
        $others = array_values(array_filter($entries, static fn (CatalogueEntry $other): bool => !$other->course->equals($entry->course)));
        $at = $before instanceof CourseId
            ? $this->index($others, $before) ?? throw InvalidCourse::because(\sprintf('The course "%s" is not in the catalogue.', $before->value)) : \count($others);
        array_splice($others, $at, 0, [$entry]);
        $placed = $this->renumber($others);
        $this->catalogues->saveEntries($entry->catalogue, $placed);

        return $placed[$at];
    }

    /**
     * @param list<CatalogueEntry> $entries
     *
     * @return ?int<0, max>
     */
    private function index(array $entries, CourseId $course): ?int
    {
        return array_find_key($entries, static fn (CatalogueEntry $entry): bool => $entry->course->equals($course));
    }

    /**
     * @param list<CatalogueEntry> $entries
     *
     * @return list<CatalogueEntry>
     */
    private function renumber(array $entries): array
    {
        $placed = [];

        foreach ($entries as $position => $entry) {
            $placed[] = $entry->at($position);
        }

        return $placed;
    }
}
