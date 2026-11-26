<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Catalogue;

use Phil\CoursesBundle\Application\Catalogue\CatalogueItem;
use Phil\CoursesBundle\Application\Catalogue\CatalogueQuery;
use Phil\CoursesBundle\Application\Catalogue\Catalogues;
use Phil\CoursesBundle\Domain\Catalogue\CatalogueSort;
use Phil\CoursesBundle\Domain\Course\CourseOrigin;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Exception\UnknownRecord;
use Phil\CoursesBundle\Domain\Model\Catalogue;
use Phil\CoursesBundle\Domain\Model\CatalogueEntry;
use Phil\CoursesBundle\Domain\ValueObject\CatalogueId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\CoursePrice;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Infrastructure\Doctrine\CourseTables;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalCatalogueRepository;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The corners of the catalogues the other tests leave to chance: the defaults of a page, what is removed with a
 * catalogue only, the order of a sort on text and prices, accents in a search, a tag and a currency, and a catalogue
 * read without the assignments.
 */
#[CoversClass(Catalogues::class)]
#[CoversClass(CatalogueEntry::class)]
#[CoversClass(DbalCatalogueRepository::class)]
final class CatalogueSurvivorsTest extends TestCase
{
    private Store $store;

    protected function setUp(): void
    {
        $this->store = new Store();
    }

    public function testThePagesOfEveryCatalogueAreFiftyByDefault(): void
    {
        for ($number = 1; $number <= 52; ++$number) {
            $this->store->catalogues->create(\sprintf('Catalogue %02d', $number));
        }

        self::assertCount(50, $this->store->catalogues->all());
        self::assertCount(50, $this->store->catalogueStore->all());
        self::assertSame('Catalogue 01', $this->store->catalogueStore->all()[0]->title);
        self::assertSame(['Catalogue 51', 'Catalogue 52'], array_map(static fn (Catalogue $catalogue): string => $catalogue->title, $this->store->catalogueStore->all(50, 50)));
    }

    public function testAnEntryOfferedAgainKeepsItsPlace(): void
    {
        $catalogue = $this->store->catalogues->create('All');
        [$a, $b, $c] = [$this->store->courses->create('A'), $this->store->courses->create('B'), $this->store->courses->create('C')];

        foreach ([$a, $b, $c] as $course) {
            $this->store->catalogues->offer($catalogue->id, $course->id);
        }

        $again = $this->store->catalogues->offer($catalogue->id, $a->id, false);
        self::assertSame([0, false], [$again->position, $again->selfEnrollment]);
        self::assertSame([['A', 0], ['B', 1], ['C', 2]], array_map(fn (CatalogueEntry $entry): array => [$this->store->courses->get($entry->course)->title, $entry->position], $this->store->catalogues->entries($catalogue->id)));
    }

    public function testMovingInAnUnknownCatalogueNamesTheCatalogue(): void
    {
        $unknown = CatalogueId::generate();

        try {
            $this->store->catalogues->move($unknown, CourseId::generate());
            self::fail('An unknown catalogue is refused.');
        } catch (UnknownRecord $unknownRecord) {
            self::assertStringContainsString($unknown->value, $unknownRecord->getMessage());
            self::assertStringNotContainsString('entry', $unknownRecord->getMessage());
        }
    }

    public function testRemovingACatalogueKeepsTheOthersAndTheirEntries(): void
    {
        $gone = $this->store->catalogues->create('Gone');
        $kept = $this->store->catalogues->create('Kept');
        $course = $this->store->courses->create('Welding');
        $this->store->catalogues->offer($gone->id, $course->id);
        $this->store->catalogues->offer($kept->id, $course->id);

        $this->store->catalogues->remove($gone->id);

        self::assertSame(['Kept'], array_map(static fn (Catalogue $catalogue): string => $catalogue->title, $this->store->catalogues->all()));
        self::assertCount(1, $this->store->catalogues->entries($kept->id));
        self::assertSame(1, Store::count($this->store->connection, 'SELECT COUNT(*) FROM '.CourseTables::CATALOGUE_ENTRY));
    }

    public function testTheTagsKeptAreReadWithinTheirDepth(): void
    {
        $catalogue = $this->store->catalogues->create('All');
        $course = $this->store->courses->create('Welding');
        $this->store->catalogues->offer($catalogue->id, $course->id, tags: ['tools']);

        $this->store->connection->update(CourseTables::CATALOGUE_ENTRY, ['tags' => '["kept",["deep"]]'], ['catalogue_id' => $catalogue->id->value]);
        self::assertSame([], $this->store->catalogueStore->entry($catalogue->id, $course->id)?->tags);
    }

    public function testACourseKeepsTheOriginItWasImportedFrom(): void
    {
        $catalogue = $this->store->catalogues->create('All');
        $course = $this->store->courses->create('Imported', origin: CourseOrigin::Cmi5, source: 'package');
        $this->store->catalogues->offer($catalogue->id, $course->id);

        self::assertSame(CourseOrigin::Cmi5, $this->store->catalogueStore->offered($catalogue->id)[0]->course->origin);
    }

    public function testAGroupIsListedOnceWhateverTheCoursesBeforeIt(): void
    {
        $catalogue = $this->store->catalogues->create('All');
        $group = $this->store->equivalences->create('Safety');

        foreach (['Alone' => null, 'Safety' => 'en', 'Sécurité' => 'fr'] as $title => $language) {
            $course = $this->store->courses->create($title);
            $this->store->catalogues->offer($catalogue->id, $course->id);

            if (null !== $language) {
                $this->store->equivalences->add($group->id, $course->id, $language);
            }
        }

        self::assertSame(['Alone', 'Sécurité'], $this->listed($this->store->catalogues->browse($catalogue->id, null, CatalogueQuery::of(language: 'fr'))->items));
    }

    public function testASearchReadsAccentedCapitals(): void
    {
        $catalogue = $this->store->catalogues->create('All');

        foreach ([['École', ''], ['Atelier', 'Une ÉCOLE de soudure.'], ['Autre', '']] as [$title, $description]) {
            $this->store->catalogues->offer($catalogue->id, $this->store->courses->create($title, $description)->id);
        }

        self::assertSame(['École', 'Atelier'], $this->listed($this->store->catalogues->browse($catalogue->id, null, CatalogueQuery::of(text: 'école'))->items));
        self::assertSame(['École', 'Atelier'], $this->listed($this->store->catalogues->browse($catalogue->id, null, CatalogueQuery::of(text: 'ÉCOLE'))->items));
        self::assertSame(['Atelier'], $this->listed($this->store->catalogues->browse($catalogue->id, null, CatalogueQuery::of(text: 'de soudure'))->items));
    }

    public function testTitlesAreSortedWithoutTheCaseOfAccents(): void
    {
        foreach ([['Éb', 'éa'], ['éa', 'Éb'], ['Éz', 'éa', 'Éb', 'ébb']] as $number => $titles) {
            $catalogue = $this->store->catalogues->create('All '.$number);

            foreach ($titles as $title) {
                $this->store->catalogues->offer($catalogue->id, $this->store->courses->create($title)->id);
            }

            $sorted = $titles;
            usort($sorted, static fn (string $a, string $b): int => strnatcasecmp(mb_strtolower($a), mb_strtolower($b)));
            self::assertSame($sorted, $this->listed($this->store->catalogues->browse($catalogue->id, null, CatalogueQuery::of(sort: CatalogueSort::Title))->items));
        }
    }

    public function testPricesAreSortedByCurrencyThenAmountAndTheFreeFirstInTheirOrder(): void
    {
        $prices = [
            'A' => null, 'B' => CoursePrice::of(900, 'USD'), 'C' => CoursePrice::of(100, 'CAD'), 'D' => null, 'E' => CoursePrice::of(500, 'CAD'),
            'F' => CoursePrice::of(300, 'USD'), 'G' => null, 'H' => CoursePrice::of(300, 'CAD'), 'I' => CoursePrice::of(100, 'EUR'), 'J' => null,
        ];
        $orders = [array_keys($prices), array_reverse(array_keys($prices)), ['H', 'J', 'B', 'A', 'I', 'E', 'G', 'F', 'C', 'D']];

        foreach ($orders as $number => $order) {
            $catalogue = $this->store->catalogues->create('All '.$number);

            foreach ($order as $title) {
                $this->store->catalogues->offer($catalogue->id, $this->store->courses->create($title)->id, price: $prices[$title]);
            }

            $expected = $order;
            usort($expected, static fn (string $a, string $b): int => [$prices[$a]->currency ?? '', $prices[$a]->minorUnits ?? 0] <=> [$prices[$b]->currency ?? '', $prices[$b]->minorUnits ?? 0]);
            self::assertSame($expected, $this->listed($this->store->catalogues->browse($catalogue->id, null, CatalogueQuery::of(sort: CatalogueSort::Price))->items));
        }
    }

    public function testFreeCoursesKeepTheOrderTheyArePlacedInWhateverTheirNumber(): void
    {
        $catalogue = $this->store->catalogues->create('All');
        $titles = [];

        for ($number = 1; $number <= 40; ++$number) {
            $titles[] = \sprintf('Free %02d', 41 - $number);
            $this->store->catalogues->offer($catalogue->id, $this->store->courses->create(end($titles))->id);
        }

        $listed = [];

        foreach (range(1, 2) as $page) {
            $listed = [...$listed, ...$this->listed($this->store->catalogues->browse($catalogue->id, null, CatalogueQuery::of(sort: CatalogueSort::Price, page: $page))->items)];
        }

        self::assertSame($titles, $listed);
    }

    public function testACatalogueIsReadWithoutTheAssignments(): void
    {
        $catalogues = new Catalogues($this->store->catalogueStore, $this->store->courseStore, $this->store->enrollments, $this->store->courseCompletions, $this->store->clock);
        $catalogue = $catalogues->create('All');
        $catalogues->offer($catalogue->id, $this->store->courses->create('Welding')->id);

        $items = $catalogues->browse($catalogue->id, LearnerId::of('ada'), CatalogueQuery::of())->items;

        self::assertSame([false], array_map(static fn (CatalogueItem $item): bool => $item->assigned, $items));
    }

    public function testATagOfTheLongestLengthMadeOfAccentedLettersIsKept(): void
    {
        $tag = str_repeat('é', CatalogueEntry::TAG_LENGTH);

        self::assertSame($tag, CatalogueEntry::tag(mb_strtoupper($tag)));
        self::assertSame(['école'], new CatalogueEntry(CatalogueId::generate(), CourseId::generate(), 0, tags: ['ÉCOLE'])->tags);
        self::assertTrue(new CatalogueEntry(CatalogueId::generate(), CourseId::generate(), 0, tags: ['École'])->hasTag(' ÉCOLE '));

        $this->expectException(InvalidCourse::class);
        CatalogueEntry::tag($tag.'é');
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
}
