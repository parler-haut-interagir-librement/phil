<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Catalogue;

use Phil\CoursesBundle\Application\Catalogue\CatalogueItem;
use Phil\CoursesBundle\Application\Catalogue\CatalogueQuery;
use Phil\CoursesBundle\Application\Catalogue\Catalogues;
use Phil\CoursesBundle\Domain\Catalogue\CatalogueAudience;
use Phil\CoursesBundle\Domain\Catalogue\CatalogueSort;
use Phil\CoursesBundle\Domain\Catalogue\PriceFilter;
use Phil\CoursesBundle\Domain\Exception\EnrollmentRefused;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Exception\UnknownRecord;
use Phil\CoursesBundle\Domain\Model\Catalogue;
use Phil\CoursesBundle\Domain\Model\CatalogueEntry;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\Training\AssigneeKind;
use Phil\CoursesBundle\Domain\ValueObject\CatalogueId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\CoursePrice;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\CoursesBundle\Infrastructure\Doctrine\CourseTables;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalCatalogueRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalCourseRepository;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The catalogues over SQLite: created, shown to everyone or to a unit, their courses placed in order, filtered, sorted
 * and paged, each group of equivalent courses listed once, where the learner stands, self-enrollment and the courses
 * bought.
 */
#[CoversClass(Catalogues::class)]
#[CoversClass(DbalCatalogueRepository::class)]
#[CoversClass(DbalCourseRepository::class)]
final class CataloguesTest extends TestCase
{
    private Store $store;

    private Catalogues $catalogues;

    protected function setUp(): void
    {
        $this->store = new Store();
        $this->catalogues = $this->store->catalogues;
    }

    public function testACatalogueIsCreatedListedRenamedShownAndRemoved(): void
    {
        $open = $this->catalogues->create('  Open  ', ' For everyone. ');
        $sales = $this->catalogues->create('Sales', '', CatalogueAudience::Unit, 'sales');
        $this->catalogues->create('Archive');

        self::assertEquals($open, $this->catalogues->get($open->id));
        self::assertSame(['Open', 'For everyone.'], [$open->title, $open->description]);
        self::assertSame(['Archive', 'Open', 'Sales'], $this->titles($this->catalogues->all()));
        self::assertSame(['Archive'], $this->titles($this->catalogues->all(0)));
        self::assertSame(['Open'], $this->titles($this->catalogues->all(1, 1)));
        self::assertSame(['Archive'], $this->titles($this->catalogues->all(1, -4)));
        self::assertSame([CatalogueAudience::Unit, 'sales'], [$this->catalogues->get($sales->id)->audience, $this->catalogues->get($sales->id)->unit]);

        $this->catalogues->rename($open->id, 'Public', 'All of it.');
        self::assertSame(['Public', 'All of it.'], [$this->catalogues->get($open->id)->title, $this->catalogues->get($open->id)->description]);
        $this->catalogues->showTo($open->id, CatalogueAudience::Unit, 'east');
        self::assertSame('east', $this->catalogues->get($open->id)->unit);

        $course = $this->course('Welding');
        $this->catalogues->offer($open->id, $course->id);
        $this->catalogues->remove($open->id);
        self::assertSame(0, Store::count($this->store->connection, 'SELECT COUNT(*) FROM '.CourseTables::CATALOGUE_ENTRY));
        $this->expectException(UnknownRecord::class);
        $this->catalogues->get($open->id);
    }

    public function testCoursesArePlacedMovedChangedAndWithdrawn(): void
    {
        $catalogue = $this->catalogues->create('All');
        [$a, $b, $c] = [$this->course('A'), $this->course('B'), $this->course('C')];

        $this->catalogues->offer($catalogue->id, $a->id);
        $this->catalogues->offer($catalogue->id, $b->id, false, CoursePrice::of(1_000, 'CAD'), ['Tools']);

        $placed = $this->catalogues->offer($catalogue->id, $c->id, before: $b->id);
        self::assertSame(1, $placed->position);
        self::assertSame([['A', 0], ['C', 1], ['B', 2]], $this->placed($catalogue->id));

        $changed = $this->catalogues->offer($catalogue->id, $b->id, true, null, ['Safety']);
        self::assertSame([2, true, null, ['safety']], [$changed->position, $changed->selfEnrollment, $changed->price, $changed->tags]);
        self::assertSame([['A', 0], ['C', 1], ['B', 2]], $this->placed($catalogue->id));
        self::assertEquals($changed, $this->catalogues->entry($catalogue->id, $b->id));

        $this->catalogues->offer($catalogue->id, $b->id, before: $a->id);
        self::assertSame([['B', 0], ['A', 1], ['C', 2]], $this->placed($catalogue->id));
        self::assertSame(2, $this->catalogues->move($catalogue->id, $b->id)->position);
        self::assertSame([['A', 0], ['C', 1], ['B', 2]], $this->placed($catalogue->id));
        self::assertSame(1, $this->catalogues->move($catalogue->id, $a->id, $b->id)->position);
        self::assertSame([['C', 0], ['A', 1], ['B', 2]], $this->placed($catalogue->id));

        $this->catalogues->withdraw($catalogue->id, $c->id);
        self::assertSame([['A', 0], ['B', 1]], $this->placed($catalogue->id));
        self::assertNull($this->catalogues->entry($catalogue->id, $c->id));

        $this->store->courses->remove($a->id);
        self::assertSame([['B', 1]], $this->placed($catalogue->id));
    }

    public function testWhatCannotBePlacedIsRefused(): void
    {
        $catalogue = $this->catalogues->create('All');
        $course = $this->course('A');
        $this->catalogues->offer($catalogue->id, $course->id);

        foreach ([
            fn (): CatalogueEntry => $this->catalogues->offer(CatalogueId::generate(), $course->id),
            fn (): CatalogueEntry => $this->catalogues->offer($catalogue->id, CourseId::generate()),
            fn (): CatalogueEntry => $this->catalogues->move($catalogue->id, CourseId::generate()),
            fn (): CatalogueEntry => $this->catalogues->move(CatalogueId::generate(), $course->id),
        ] as $call) {
            try {
                $call();
                self::fail('An unknown catalogue or course is refused.');
            } catch (UnknownRecord) {
            }
        }

        foreach ([
            fn (): CatalogueEntry => $this->catalogues->offer($catalogue->id, $this->course('B')->id, before: CourseId::generate()),
            fn (): CatalogueEntry => $this->catalogues->move($catalogue->id, $course->id, CourseId::generate()),
            fn (): CatalogueEntry => $this->catalogues->offer($catalogue->id, $course->id, tags: ['-']),
        ] as $call) {
            try {
                $call();
                self::fail('A place before a course not in the catalogue, or a wrong tag, is refused.');
            } catch (InvalidCourse) {
            }
        }

        $this->expectException(UnknownRecord::class);
        $this->catalogues->withdraw(CatalogueId::generate(), $course->id);
    }

    public function testAFullCatalogueTakesNoMoreCoursesButChangesItsOwn(): void
    {
        $catalogue = $this->catalogues->create('Full');
        $first = $this->course('First');
        $entries = [new CatalogueEntry($catalogue->id, $first->id, 0)];

        for ($position = 1; $position < Catalogues::MAX_ENTRIES; ++$position) {
            $entries[] = new CatalogueEntry($catalogue->id, CourseId::of('course-'.$position), $position);
        }

        $this->store->catalogueStore->saveEntries($catalogue->id, $entries);
        self::assertSame(['paid'], $this->catalogues->offer($catalogue->id, $first->id, tags: ['paid'])->tags);
        self::assertSame(1, $this->catalogues->offer(CatalogueId::of($catalogue->id->value), $first->id, before: CourseId::of('course-2'))->position);

        $this->expectException(InvalidCourse::class);
        $this->expectExceptionMessageIsOrContains('A catalogue offers 1000 courses at most.');
        $this->catalogues->offer($catalogue->id, $this->course('One more')->id);
    }

    public function testTheCataloguesOfTheUnitsAreShownToTheirMembersOnly(): void
    {
        $public = $this->catalogues->create('Public');
        $sales = $this->catalogues->create('Sales', '', CatalogueAudience::Unit, 'sales');
        $east = $this->catalogues->create('East', '', CatalogueAudience::Unit, 'east');
        $this->store->units->units = ['ada' => ['sales', 'sales'], 'eve' => []];

        self::assertSame(['Public'], $this->titles($this->catalogues->shownTo(null)));
        self::assertSame(0, $this->store->units->asked);
        self::assertSame(['Public', 'Sales'], $this->titles($this->catalogues->shownTo(LearnerId::of('ada'))));
        self::assertSame(['Public'], $this->titles($this->catalogues->shownTo(LearnerId::of('eve'))));
        self::assertSame(2, $this->store->units->asked);

        self::assertSame('Public', $this->catalogues->shown($public->id, null)->title);
        self::assertSame('Public', $this->catalogues->shown($public->id, LearnerId::of('ada'))->title);
        self::assertSame(2, $this->store->units->asked);
        self::assertSame('Sales', $this->catalogues->shown($sales->id, LearnerId::of('ada'))->title);

        foreach ([[$east->id, LearnerId::of('ada')], [$sales->id, null], [$sales->id, LearnerId::of('eve')], [CatalogueId::generate(), null]] as [$catalogue, $learner]) {
            try {
                $this->catalogues->browse($catalogue, $learner, CatalogueQuery::of());
                self::fail('A catalogue not shown is unknown.');
            } catch (UnknownRecord) {
            }
        }

        $this->store->units->units['ada'] = array_map(static fn (int $unit): string => 'unit-'.$unit, range(1, Catalogues::MAX_UNITS + 1));
        $last = $this->catalogues->create('Last', '', CatalogueAudience::Unit, 'unit-'.(Catalogues::MAX_UNITS + 1));
        $this->catalogues->create('First', '', CatalogueAudience::Unit, 'unit-1');
        self::assertSame(['First', 'Public'], $this->titles($this->catalogues->shownTo(LearnerId::of('ada'))));
        $this->expectException(UnknownRecord::class);
        $this->catalogues->shown($last->id, LearnerId::of('ada'));
    }

    public function testACatalogueIsFilteredSortedAndPaged(): void
    {
        $catalogue = $this->catalogues->create('All');
        $courses = [];

        foreach (['Course 10' => [null, ['tools']], 'course 9' => [CoursePrice::of(500, 'USD'), ['tools', 'safety']], 'Welding' => [CoursePrice::of(900, 'CAD'), []], 'Painting' => [CoursePrice::of(400, 'CAD'), ['safety']], 'Drawing' => [null, []]] as $title => [$price, $tags]) {
            $courses[$title] = $this->course($title, 'Drawing' === $title ? 'With charcoal and WELDING rods.' : '');
            $this->catalogues->offer($catalogue->id, $courses[$title]->id, true, $price, $tags);
            $this->store->clock->modify('+1 minute');
        }

        $page = $this->catalogues->browse($catalogue->id, null, CatalogueQuery::of());
        self::assertSame(['Course 10', 'course 9', 'Welding', 'Painting', 'Drawing'], $this->listed($page->items));
        self::assertSame([5, 1, ['safety', 'tools']], [$page->total, $page->pages, $page->tags]);
        self::assertSame($catalogue->id->value, $page->catalogue->id->value);

        self::assertSame(['course 9', 'Course 10', 'Drawing', 'Painting', 'Welding'], $this->listed($this->catalogues->browse($catalogue->id, null, CatalogueQuery::of(sort: CatalogueSort::Title))->items));
        self::assertSame(['Drawing', 'Painting', 'Welding', 'course 9', 'Course 10'], $this->listed($this->catalogues->browse($catalogue->id, null, CatalogueQuery::of(sort: CatalogueSort::Newest))->items));
        self::assertSame(['Course 10', 'Drawing', 'Painting', 'Welding', 'course 9'], $this->listed($this->catalogues->browse($catalogue->id, null, CatalogueQuery::of(sort: CatalogueSort::Price))->items));
        self::assertSame(['Course 10', 'Drawing'], $this->listed($this->catalogues->browse($catalogue->id, null, CatalogueQuery::of(price: PriceFilter::Free))->items));
        self::assertSame(['course 9', 'Welding', 'Painting'], $this->listed($this->catalogues->browse($catalogue->id, null, CatalogueQuery::of(price: PriceFilter::Paid))->items));
        self::assertSame(['course 9', 'Painting'], $this->listed($this->catalogues->browse($catalogue->id, null, CatalogueQuery::of(tag: 'Safety'))->items));
        self::assertSame(['Welding', 'Drawing'], $this->listed($this->catalogues->browse($catalogue->id, null, CatalogueQuery::of(text: 'weld'))->items));
        self::assertSame(['course 9'], $this->listed($this->catalogues->browse($catalogue->id, null, CatalogueQuery::of(text: 'COURSE', tag: 'safety', price: PriceFilter::Paid))->items));
        self::assertSame([], $this->listed($this->catalogues->browse($catalogue->id, null, CatalogueQuery::of(text: 'painting', price: PriceFilter::Free))->items));
        $none = $this->catalogues->browse($catalogue->id, null, CatalogueQuery::of(text: 'nothing like it', page: 4));
        self::assertSame([0, 1, 1], [$none->total, $none->pages, $none->query->page]);
    }

    public function testALongCatalogueIsReadAPageAtATime(): void
    {
        $catalogue = $this->catalogues->create('Long');

        for ($number = 1; $number <= 2 * Catalogues::PER_PAGE + 1; ++$number) {
            $this->catalogues->offer($catalogue->id, $this->course(\sprintf('Course %02d', $number))->id);
        }

        $first = $this->catalogues->browse($catalogue->id, null, CatalogueQuery::of());
        self::assertSame([41, 3, 1, Catalogues::PER_PAGE, 'Course 01', 'Course 20'], [$first->total, $first->pages, $first->query->page, \count($first->items), $this->listed($first->items)[0], $this->listed($first->items)[19]]);
        $second = $this->catalogues->browse($catalogue->id, null, CatalogueQuery::of(page: 2));
        self::assertSame(['Course 21', 'Course 40'], [$this->listed($second->items)[0], $this->listed($second->items)[19]]);
        $beyond = $this->catalogues->browse($catalogue->id, null, CatalogueQuery::of(page: 9));
        self::assertSame([3, ['Course 41']], [$beyond->query->page, $this->listed($beyond->items)]);

        $this->catalogues->withdraw($catalogue->id, $this->catalogues->entries($catalogue->id)[40]->course);
        $exact = $this->catalogues->browse($catalogue->id, null, CatalogueQuery::of(page: 9));
        self::assertSame([40, 2, 2, 'Course 40'], [$exact->total, $exact->pages, $exact->query->page, $this->listed($exact->items)[19]]);
    }

    public function testAGroupOfEquivalentCoursesIsListedOnceInTheLearnersLanguage(): void
    {
        $catalogue = $this->catalogues->create('All');
        $group = $this->store->equivalences->create('Safety');
        $other = $this->store->equivalences->create('Tools');

        foreach (['Safety' => 'en', 'Sécurité' => 'fr', 'Sécurité au Québec' => 'fr-CA', 'Tools' => null, 'Outils' => null, 'Werkzeuge' => null] as $title => $language) {
            $course = $this->course($title);
            $this->catalogues->offer($catalogue->id, $course->id);

            if (null !== $language) {
                $this->store->equivalences->add($group->id, $course->id, $language);
            } elseif ('Werkzeuge' !== $title) {
                $this->store->equivalences->add($other->id, $course->id, 'Tools' === $title ? 'en' : 'fr');
            }
        }

        self::assertSame(['Safety', 'Sécurité', 'Sécurité au Québec', 'Tools', 'Outils', 'Werkzeuge'], $this->listed($this->catalogues->browse($catalogue->id, null, CatalogueQuery::of())->items));
        self::assertSame(['Sécurité au Québec', 'Outils', 'Werkzeuge'], $this->listed($this->catalogues->browse($catalogue->id, null, CatalogueQuery::of(language: 'fr-CA'))->items));
        self::assertSame(['Sécurité', 'Outils', 'Werkzeuge'], $this->listed($this->catalogues->browse($catalogue->id, null, CatalogueQuery::of(language: 'fr-BE'))->items));
        self::assertSame(['Safety', 'Tools', 'Werkzeuge'], $this->listed($this->catalogues->browse($catalogue->id, null, CatalogueQuery::of(language: 'de'))->items));
        $page = $this->catalogues->browse($catalogue->id, null, CatalogueQuery::of(language: 'en'));
        self::assertSame(['Safety', 'Tools', 'Werkzeuge'], $this->listed($page->items));
        self::assertSame(['en', 'en', null], array_map(static fn (CatalogueItem $item): ?string => $item->offered->language, $page->items));
        self::assertSame($group->id->value, $page->items[0]->offered->group?->value);
        self::assertNull($page->items[2]->offered->group);
    }

    public function testEachCourseSaysWhereTheLearnerStands(): void
    {
        $catalogue = $this->catalogues->create('All');
        $group = $this->store->equivalences->create('Safety');
        [$english, $french, $assigned, $done, $open] = [$this->course('Safety'), $this->course('Sécurité'), $this->course('Assigned'), $this->course('Done'), $this->course('Open')];
        $this->store->equivalences->add($group->id, $english->id, 'en');
        $this->store->equivalences->add($group->id, $french->id, 'fr');

        foreach ([$english, $french, $assigned, $done, $open] as $course) {
            $this->catalogues->offer($catalogue->id, $course->id);
        }

        $ada = LearnerId::of('ada');
        $this->complete($french, $ada);
        $this->store->assignments->assign($assigned->id, AssigneeKind::Learner, 'ada', $this->store->clock->now()->modify('+1 week'));
        $this->store->assignments->assign($done->id, AssigneeKind::Learner, 'ada', $this->store->clock->now()->modify('+1 week'));

        $obligation = $this->store->assignmentStore->obligationsFor($done->id, $ada)[0];
        $obligation->complete($this->store->clock->now(), null);

        $this->store->assignmentStore->saveObligation($obligation);
        $this->store->assignments->assign($open->id, AssigneeKind::Learner, 'eve', $this->store->clock->now()->modify('+1 week'));

        $items = $this->catalogues->browse($catalogue->id, $ada, CatalogueQuery::of())->items;
        self::assertSame([
            'Safety' => [false, true, false],
            'Sécurité' => [true, true, false],
            'Assigned' => [true, false, true],
            'Done' => [true, false, false],
            'Open' => [false, false, false],
        ], array_combine($this->listed($items), array_map(static fn (CatalogueItem $item): array => [$item->isEnrolled(), $item->completed, $item->assigned], $items)));

        $anonymous = $this->catalogues->browse($catalogue->id, null, CatalogueQuery::of())->items;
        self::assertSame([false, false, false], [$anonymous[1]->isEnrolled(), $anonymous[1]->completed, $anonymous[2]->assigned]);
    }

    public function testALearnerEnrollsInAFreeCourseOpenToItAndIsEnrolledInABoughtOne(): void
    {
        $catalogue = $this->catalogues->create('All');
        $hidden = $this->catalogues->create('East', '', CatalogueAudience::Unit, 'east');
        [$free, $closed, $sold, $elsewhere] = [$this->course('Free'), $this->course('Closed'), $this->course('Sold'), $this->course('Elsewhere')];
        $this->catalogues->offer($catalogue->id, $free->id);
        $this->catalogues->offer($catalogue->id, $closed->id, false);
        $this->catalogues->offer($catalogue->id, $sold->id, true, CoursePrice::of(2_000, 'CAD'));
        $this->catalogues->offer($hidden->id, $elsewhere->id);

        $ada = LearnerId::of('ada');

        $enrollment = $this->catalogues->enroll($catalogue->id, $free->id, $ada);
        self::assertTrue($enrollment->course->equals($free->id));
        self::assertSame($enrollment->id->value, $this->catalogues->enroll($catalogue->id, $free->id, $ada)->id->value);

        foreach ([[$closed, 'This course is not open to self-enrollment.', false], [$sold, 'This course is bought before the enrollment.', true], [$elsewhere, 'This course is not in the catalogue.', false]] as [$course, $message, $purchase]) {
            try {
                $this->catalogues->enroll($catalogue->id, $course->id, $ada);
                self::fail('The enrollment is refused.');
            } catch (EnrollmentRefused $enrollmentRefused) {
                self::assertSame([$message, $purchase], [$enrollmentRefused->getMessage(), $enrollmentRefused->purchase]);
            }
        }

        self::assertNull($this->store->enrollments->of($sold->id, $ada));
        $bought = $this->catalogues->grantPurchase($catalogue->id, $sold->id, $ada);
        self::assertTrue($bought->course->equals($sold->id));
        self::assertSame($bought->id->value, $this->catalogues->enroll($catalogue->id, $sold->id, $ada)->id->value);
        $this->store->enrollments->enroll($closed->id, LearnerId::of('eve'));
        self::assertSame('eve', $this->catalogues->enroll($catalogue->id, $closed->id, LearnerId::of('eve'))->learner->value);

        foreach ([[$catalogue->id, $free->id, 'This course is not sold in the catalogue.'], [$catalogue->id, $elsewhere->id, 'This course is not in the catalogue.']] as [$in, $course, $message]) {
            try {
                $this->catalogues->grantPurchase($in, $course, $ada);
                self::fail('Only a course sold is granted by a purchase.');
            } catch (EnrollmentRefused $enrollmentRefused) {
                self::assertSame($message, $enrollmentRefused->getMessage());
            }
        }

        $this->expectException(UnknownRecord::class);
        $this->catalogues->enroll($hidden->id, $elsewhere->id, $ada);
    }

    public function testTheStoreReadsBackWhatItKeeps(): void
    {
        $catalogue = $this->catalogues->create('All', '', CatalogueAudience::Unit, 'sales');
        $course = $this->course('Welding');
        $this->catalogues->offer($catalogue->id, $course->id, false, CoursePrice::of(12_345, 'EUR'), ['Métal', 'tools']);

        $entry = $this->store->catalogueStore->entries($catalogue->id)[0];
        self::assertSame([false, 12_345, 'EUR', ['métal', 'tools']], [$entry->selfEnrollment, $entry->price?->minorUnits, $entry->price?->currency, $entry->tags]);
        self::assertSame('["métal","tools"]', $this->store->connection->fetchOne('SELECT tags FROM '.CourseTables::CATALOGUE_ENTRY));
        $offered = $this->store->catalogueStore->offered($catalogue->id)[0];
        self::assertSame(['Welding', $course->createdAt->format('c'), false], [$offered->course->title, $offered->course->createdAt->format('c'), $offered->course->ordered]);

        foreach (['not json', '{"a": "b"}', '[1, "kept"]', '"text"'] as $tags) {
            $this->store->connection->update(CourseTables::CATALOGUE_ENTRY, ['tags' => $tags], ['catalogue_id' => $catalogue->id->value]);
            $read = $this->store->catalogueStore->entry($catalogue->id, $course->id)?->tags;
            self::assertSame('[1, "kept"]' === $tags ? ['kept'] : ('{"a": "b"}' === $tags ? ['b'] : []), $read);
        }

        $this->store->connection->update(CourseTables::CATALOGUE_ENTRY, ['price_currency' => null], ['catalogue_id' => $catalogue->id->value]);
        self::assertNull($this->store->catalogueStore->entry($catalogue->id, $course->id)?->price);
        $this->store->connection->update(CourseTables::CATALOGUE, ['audience' => 'unknown'], ['id' => $catalogue->id->value]);
        self::assertSame(CatalogueAudience::Unit, $this->store->catalogueStore->find($catalogue->id)?->audience);
        self::assertNull($this->store->catalogueStore->find(CatalogueId::generate()));
        self::assertNull($this->store->catalogueStore->entry($catalogue->id, CourseId::generate()));
    }

    public function testTheCataloguesShownAreBounded(): void
    {
        for ($number = 1; $number <= DbalCatalogueRepository::MAX_SHOWN + 1; ++$number) {
            $this->store->catalogueStore->save(new Catalogue(CatalogueId::generate(), \sprintf('Catalogue %03d', $number), $this->store->clock->now()));
        }

        $shown = $this->store->catalogueStore->shownTo(['sales']);
        self::assertCount(DbalCatalogueRepository::MAX_SHOWN, $shown);
        self::assertSame('Catalogue 001', $shown[0]->title);
        self::assertCount(DbalCatalogueRepository::MAX_SHOWN, $this->store->catalogueStore->shownTo([]));
        self::assertCount(200, $this->catalogues->all(500));
    }

    public function testTheUnitsOfALearnerAreCountedOnce(): void
    {
        $this->store->units->units = ['ada' => [...array_fill(0, Catalogues::MAX_UNITS + 100, 'sales'), 'east']];
        $this->catalogues->create('East', '', CatalogueAudience::Unit, 'east');
        $this->catalogues->create('Sales', '', CatalogueAudience::Unit, 'sales');

        self::assertSame(['East', 'Sales'], $this->titles($this->catalogues->shownTo(LearnerId::of('ada'))));
    }

    private function course(string $title, string $description = ''): Course
    {
        return $this->store->courses->create($title, $description);
    }

    private function complete(Course $course, LearnerId $learner): void
    {
        $module = $this->store->courses->addModule($course->id, 'Module');
        $activity = $this->store->courses->addActivity($module->id, 'Read', 'page');
        $this->store->enrollments->record($this->store->enrollments->enroll($course->id, $learner), $activity, Outcome::completed());
    }

    /**
     * @param list<Catalogue> $catalogues
     *
     * @return list<string>
     */
    private function titles(array $catalogues): array
    {
        return array_map(static fn (Catalogue $catalogue): string => $catalogue->title, $catalogues);
    }

    /**
     * @param list<CatalogueItem> $items
     *
     * @return list<string>
     */
    private function listed(array $items): array
    {
        return array_map(static fn (CatalogueItem $item): string => $item->course()->title, $items);
    }

    /**
     * @return list<array{string, int}>
     */
    private function placed(CatalogueId $catalogue): array
    {
        return array_map(fn (CatalogueEntry $entry): array => [$this->store->courses->get($entry->course)->title, $entry->position], $this->catalogues->entries($catalogue));
    }
}
