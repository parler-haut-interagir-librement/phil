<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Equivalence;

use Phil\CoursesBundle\Application\Completions;
use Phil\CoursesBundle\Application\Equivalences;
use Phil\CoursesBundle\Application\Training\Assignments;
use Phil\CoursesBundle\Application\Training\Transcripts;
use Phil\CoursesBundle\Domain\Event\CourseCompleted;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\Model\EquivalenceGroup;
use Phil\CoursesBundle\Domain\Training\AssigneeKind;
use Phil\CoursesBundle\Domain\Training\CreditUnit;
use Phil\CoursesBundle\Domain\ValueObject\Credits;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalEquivalenceRepository;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The bounds, the ties and the neighbours of the groups of equivalent courses that the other tests do not reach.
 */
#[CoversClass(Equivalences::class)]
#[CoversClass(DbalEquivalenceRepository::class)]
#[CoversClass(Completions::class)]
#[CoversClass(Assignments::class)]
#[CoversClass(Transcripts::class)]
final class EquivalenceSurvivorsTest extends TestCase
{
    public function testAPageOfGroupsHoldsFiftyAtMostByTitle(): void
    {
        $store = new Store();

        for ($number = 0; $number < 51; ++$number) {
            $store->equivalences->create(\sprintf('Group %02d', $number));
        }

        self::assertCount(50, $store->equivalences->all());
        self::assertCount(50, $store->equivalenceStore->all());
        self::assertSame('Group 00', $store->equivalenceStore->all()[0]->title);
        self::assertSame('Group 00', $store->equivalences->all()[0]->title);
        self::assertCount(1, $store->equivalenceStore->all(1));
        self::assertCount(2, $store->equivalenceStore->all(2));
    }

    public function testDissolvingAGroupLeavesTheOtherGroupsAlone(): void
    {
        $store = new Store();
        [$english] = $this->course($store, 'Fire safety');
        [$french] = $this->course($store, 'Sécurité incendie');
        [$spanish] = $this->course($store, 'Seguridad');
        $kept = $store->equivalences->create('Kept');
        $dissolved = $store->equivalences->create('Dissolved');
        $store->equivalences->add($kept->id, $english->id, 'en');
        $store->equivalences->add($kept->id, $french->id, 'fr');
        $store->equivalences->add($dissolved->id, $spanish->id, 'es');

        $store->equivalences->dissolve($dissolved->id);

        self::assertNull($store->equivalences->membership($spanish->id));
        self::assertCount(2, $store->equivalences->courses($kept->id));
        self::assertSame(['Kept'], array_map(static fn (EquivalenceGroup $group): string => $group->title, $store->equivalences->all()));
    }

    public function testRemovingACourseLeavesTheOtherMembersAlone(): void
    {
        $store = new Store();
        [$english] = $this->course($store, 'Fire safety');
        [$french] = $this->course($store, 'Sécurité incendie');
        $group = $store->equivalences->create('Fire safety');
        $store->equivalences->add($group->id, $english->id, 'en');
        $store->equivalences->add($group->id, $french->id, 'fr');

        $store->equivalences->remove($english->id);

        self::assertNull($store->equivalences->membership($english->id));
        self::assertNotNull($store->equivalences->membership($french->id));
    }

    public function testOfTwoEquivalentCoursesCompletedTogetherTheFirstByLanguageIsKept(): void
    {
        $store = new Store();
        [$english] = $this->course($store, 'Fire safety');
        [$spanish, $spanishPage] = $this->course($store, 'Seguridad');
        [$french, $frenchPage] = $this->course($store, 'Sécurité incendie');
        $group = $store->equivalences->create('Fire safety');
        $store->equivalences->add($group->id, $english->id, 'en');
        $store->equivalences->add($group->id, $spanish->id, 'es');
        $store->equivalences->add($group->id, $french->id, 'fr');

        $ada = LearnerId::of('ada');
        $this->complete($store, $spanishPage, $ada);
        $this->complete($store, $frenchPage, $ada);

        self::assertEquals($spanish->id, $store->courseCompletions->completion($english->id, $ada)?->course);
    }

    public function testTheLatestOfTheEquivalentCompletionsAndTheOwnOneSetTheObligation(): void
    {
        $store = new Store();
        [$english, $englishPage] = $this->course($store, 'Fire safety');
        [$spanish, $spanishPage] = $this->course($store, 'Seguridad');
        [$french, $frenchPage] = $this->course($store, 'Sécurité incendie');
        $group = $store->equivalences->create('Fire safety');
        $store->equivalences->add($group->id, $english->id, 'en');
        $store->equivalences->add($group->id, $spanish->id, 'es');
        $store->equivalences->add($group->id, $french->id, 'fr');

        $ada = LearnerId::of('ada');
        $bob = LearnerId::of('bob');

        // Ada: Spanish first, French two days later, the latest in the order of the languages.
        $this->complete($store, $spanishPage, $ada);
        $store->clock->modify('+1 day');
        $this->complete($store, $englishPage, $bob);
        $store->clock->modify('+1 day');
        $this->complete($store, $frenchPage, $ada);
        $this->complete($store, $frenchPage, $bob);
        $store->members->groups['crew'] = ['ada', 'bob'];

        $assignment = $store->assignments->assign($english->id, AssigneeKind::Group, 'crew', $store->clock->now()->modify('+30 days'), 6);

        self::assertSame('2026-11-15 10:00', $store->assignmentStore->obligation($assignment->id, $ada)?->completedAt?->format('Y-m-d H:i'));
        // Bob completed the course itself the day before: it answers for itself.
        self::assertSame('2026-11-14 10:00', $store->assignmentStore->obligation($assignment->id, $bob)?->completedAt?->format('Y-m-d H:i'));
    }

    public function testACourseOutsideAnyGroupKeepsItsOwnCredits(): void
    {
        $store = new Store();
        [$course, $page] = $this->course($store, 'Welding');
        $store->transcripts->credit($course->id, Credits::of('3', CreditUnit::Hours));
        $ada = LearnerId::of('ada');

        $this->complete($store, $page, $ada);

        self::assertSame('3', $store->transcripts->of($ada)->entries[0]->credits?->amount());
    }

    /**
     * @return array{Course, Activity}
     */
    private function course(Store $store, string $title): array
    {
        $course = $store->courses->create($title);
        $module = $store->courses->addModule($course->id, 'Basics');

        return [$course, $store->courses->addActivity($module->id, 'Read', 'page')];
    }

    private function complete(Store $store, Activity $page, LearnerId $learner): void
    {
        $store->enrollments->record($store->enrollments->enroll($page->course, $learner), $page, Outcome::completed());
        $events = $store->events->of(CourseCompleted::class);
        $store->completions->__invoke($events[\count($events) - 1]);
    }
}
