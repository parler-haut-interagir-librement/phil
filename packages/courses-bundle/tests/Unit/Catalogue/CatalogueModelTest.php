<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Catalogue;

use DateTimeImmutable;
use Phil\CoursesBundle\Application\Catalogue\CatalogueItem;
use Phil\CoursesBundle\Application\Catalogue\CataloguePage;
use Phil\CoursesBundle\Application\Catalogue\CatalogueQuery;
use Phil\CoursesBundle\Domain\Catalogue\CatalogueAudience;
use Phil\CoursesBundle\Domain\Catalogue\CatalogueSort;
use Phil\CoursesBundle\Domain\Catalogue\OfferedCourse;
use Phil\CoursesBundle\Domain\Catalogue\PriceFilter;
use Phil\CoursesBundle\Domain\Exception\EnrollmentRefused;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Model\Catalogue;
use Phil\CoursesBundle\Domain\Model\CatalogueEntry;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\ValueObject\CatalogueId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\CoursePrice;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The values of the catalogues: a price, a catalogue and who it is shown to, an entry and its tags, the query of a
 * catalogue page read from its query string, an item and a page.
 */
#[CoversClass(CoursePrice::class)]
#[CoversClass(CatalogueId::class)]
#[CoversClass(Catalogue::class)]
#[CoversClass(CatalogueEntry::class)]
#[CoversClass(CatalogueQuery::class)]
#[CoversClass(CatalogueItem::class)]
#[CoversClass(CataloguePage::class)]
#[CoversClass(OfferedCourse::class)]
#[CoversClass(EnrollmentRefused::class)]
final class CatalogueModelTest extends TestCase
{
    public function testAPriceIsAnAmountAboveZeroInACurrency(): void
    {
        $price = CoursePrice::of(1, ' cad ');
        self::assertSame([1, 'CAD'], [$price->minorUnits, $price->currency]);
        self::assertSame(CoursePrice::MAX_MINOR_UNITS, CoursePrice::of(CoursePrice::MAX_MINOR_UNITS, 'JPY')->minorUnits);
        self::assertTrue($price->equals(CoursePrice::of(1, 'CAD')));
        self::assertFalse($price->equals(CoursePrice::of(2, 'CAD')));
        self::assertFalse($price->equals(CoursePrice::of(1, 'USD')));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function wrongPrices(): iterable
    {
        yield 'zero' => [0, 'CAD'];
        yield 'negative' => [-1, 'CAD'];
        yield 'too much' => [CoursePrice::MAX_MINOR_UNITS + 1, 'CAD'];
        yield 'two letters' => [100, 'CA'];
        yield 'four letters' => [100, 'CADX'];
        yield 'digits' => [100, 'C4D'];
        yield 'a line after' => [100, "CAD\nX"];
    }

    #[DataProvider('wrongPrices')]
    public function testAPriceOutOfShapeIsRefused(int $minorUnits, string $currency): void
    {
        $this->expectException(InvalidCourse::class);
        CoursePrice::of($minorUnits, $currency);
    }

    public function testACatalogueIsPublicOrOfAUnit(): void
    {
        $id = CatalogueId::generate();
        $public = new Catalogue($id, '  Everyone  ', new DateTimeImmutable('2026-11-25 08:00:00 America/Toronto'), '  Open courses.  ');
        self::assertSame(['Everyone', 'Open courses.', '2026-11-25 13:00 UTC', CatalogueAudience::Public, null], [$public->title, $public->description, $public->createdAt->format('Y-m-d H:i T'), $public->audience, $public->unit]);
        self::assertTrue($public->isPublic());
        self::assertTrue($public->isShownTo([]));
        self::assertTrue($id->equals(CatalogueId::of($id->value)));
        self::assertFalse($id->equals(CatalogueId::generate()));

        $public->showTo(CatalogueAudience::Unit, 'sales');
        self::assertSame([CatalogueAudience::Unit, 'sales'], [$public->audience, $public->unit]);
        self::assertFalse($public->isPublic());
        self::assertFalse($public->isShownTo([]));
        self::assertFalse($public->isShownTo(['east']));
        self::assertTrue($public->isShownTo(['east', 'sales']));

        $public->rename(' Sales ', ' Ours. ');
        self::assertSame(['Sales', 'Ours.'], [$public->title, $public->description]);
        $public->showTo(CatalogueAudience::Public);
        self::assertSame([CatalogueAudience::Public, null], [$public->audience, $public->unit]);
        self::assertSame(str_repeat('u', 128), new Catalogue($id, 'A', new DateTimeImmutable(), '', CatalogueAudience::Unit, str_repeat('u', 128))->unit);
        self::assertSame(10_000, mb_strlen(new Catalogue($id, 'A', new DateTimeImmutable(), str_repeat('é', 10_000))->description));
    }

    /**
     * @return iterable<string, array{CatalogueAudience, ?string, string}>
     */
    public static function wrongAudiences(): iterable
    {
        yield 'public with a unit' => [CatalogueAudience::Public, 'sales', ''];
        yield 'unit without one' => [CatalogueAudience::Unit, null, ''];
        yield 'unit too long' => [CatalogueAudience::Unit, str_repeat('u', 129), ''];
        yield 'unit with a slash' => [CatalogueAudience::Unit, 'sales/east', ''];
        yield 'description too long' => [CatalogueAudience::Public, null, str_repeat('é', 10_001)];
    }

    #[DataProvider('wrongAudiences')]
    public function testACatalogueOutOfShapeIsRefused(CatalogueAudience $audience, ?string $unit, string $description): void
    {
        $this->expectException(InvalidCourse::class);
        new Catalogue(CatalogueId::generate(), 'A catalogue', new DateTimeImmutable(), $description, $audience, $unit);
    }

    public function testRenamingWithALongDescriptionOrShowingToNoUnitIsRefused(): void
    {
        $catalogue = new Catalogue(CatalogueId::generate(), 'A catalogue', new DateTimeImmutable());

        try {
            $catalogue->rename('A catalogue', str_repeat('x', 10_001));
            self::fail('A description too long is refused.');
        } catch (InvalidCourse) {
        }

        $this->expectException(InvalidCourse::class);
        $catalogue->showTo(CatalogueAudience::Unit);
    }

    public function testAnEntryKeepsItsTagsLowercasedOnceAndNamesItsSellable(): void
    {
        $catalogue = CatalogueId::of('cat-1');
        $course = CourseId::of('course-1');
        $entry = new CatalogueEntry($catalogue, $course, 3, tags: [' Safety ', 'safety', 'Première aide', 'level-2']);

        self::assertSame(['safety', 'première aide', 'level-2'], $entry->tags);
        self::assertTrue($entry->selfEnrollment);
        self::assertTrue($entry->isFree());
        self::assertTrue($entry->hasTag(' SAFETY '));
        self::assertFalse($entry->hasTag('tools'));
        self::assertSame('course:cat-1/course-1', $entry->sellable());

        $sold = new CatalogueEntry($catalogue, $course, 0, false, CoursePrice::of(2_500, 'CAD'), ['a']);
        self::assertFalse($sold->isFree());
        $moved = $sold->at(7);
        self::assertSame([7, false, 2_500, ['a']], [$moved->position, $moved->selfEnrollment, $moved->price?->minorUnits, $moved->tags]);
        self::assertSame(str_repeat('t', 50), CatalogueEntry::tag(str_repeat('T', 50)));
        self::assertCount(10, new CatalogueEntry($catalogue, $course, 0, tags: array_map(static fn (int $tag): string => 'tag '.$tag, range(1, 10)))->tags);
        self::assertCount(10, new CatalogueEntry($catalogue, $course, 0, tags: [...array_map(static fn (int $tag): string => 'tag '.$tag, range(1, 10)), 'TAG 1'])->tags);
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function wrongTags(): iterable
    {
        yield 'empty' => [['  ']];
        yield 'too long' => [[str_repeat('t', 51)]];
        yield 'starting with a dash' => [['-tools']];
        yield 'a sign' => [['c++']];
        yield 'a line inside' => [["tools\nkit"]];
        yield 'too many' => [array_map(static fn (int $tag): string => 'tag '.$tag, range(1, 11))];
    }

    /**
     * @param list<string> $tags
     */
    #[DataProvider('wrongTags')]
    public function testATagOutOfShapeIsRefused(array $tags): void
    {
        $this->expectException(InvalidCourse::class);
        new CatalogueEntry(CatalogueId::of('cat'), CourseId::of('course'), 0, tags: $tags);
    }

    public function testAQueryHasItsDefaults(): void
    {
        $query = CatalogueQuery::of();
        self::assertSame([null, null, PriceFilter::Any, CatalogueSort::Placed, null, 1, []], [$query->text, $query->tag, $query->price, $query->sort, $query->language, $query->page, $query->parameters()]);
        self::assertSame(1, CatalogueQuery::of(page: 0)->page);
        self::assertSame(CatalogueQuery::MAX_PAGE, CatalogueQuery::of(page: 5_000)->page);
        self::assertSame([1, 2, CatalogueQuery::MAX_PAGE], [$query->onPage(-3)->page, $query->onPage(2)->page, $query->onPage(1_001)->page]);
    }

    public function testAQueryIsReadFromTheQueryString(): void
    {
        $query = CatalogueQuery::fromQuery(['q' => '  weld  ', 'tag' => ' Safety ', 'price' => 'paid', 'sort' => 'newest', 'page' => '3'], 'fr_CA');
        self::assertSame(['weld', 'safety', PriceFilter::Paid, CatalogueSort::Newest, 'fr-ca', 3], [$query->text, $query->tag, $query->price, $query->sort, $query->language, $query->page]);
        self::assertSame(['q' => 'weld', 'tag' => 'safety', 'price' => 'paid', 'sort' => 'newest', 'page' => '3'], $query->parameters());
        self::assertSame(['q' => 'weld', 'tag' => 'safety', 'price' => 'paid', 'sort' => 'newest'], $query->onPage(1)->parameters());
        self::assertSame(1_000, CatalogueQuery::fromQuery(['page' => '1000'])->page);
        self::assertSame(1_000, CatalogueQuery::fromQuery(['page' => '9999'])->page);
        self::assertSame(str_repeat('é', 100), CatalogueQuery::fromQuery(['q' => str_repeat('é', 101)])->text);
        self::assertSame(str_repeat('a', 99), CatalogueQuery::fromQuery(['q' => '  '.str_repeat('a', 99).' b'])->text);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function strangeQueries(): iterable
    {
        yield 'nothing' => [[]];
        yield 'arrays' => [['q' => ['weld'], 'tag' => ['a'], 'price' => ['paid'], 'sort' => ['title'], 'page' => ['2']]];
        yield 'unknown values' => [['q' => '   ', 'tag' => '-x', 'price' => 'cheap', 'sort' => 'random', 'page' => 'two']];
        yield 'page zero' => [['page' => '0']];
        yield 'page with a zero first' => [['page' => '02']];
        yield 'page too long' => [['page' => '10000']];
        yield 'page negative' => [['page' => '-2']];
        yield 'page with a line after' => [['page' => "2\n"]];
        yield 'page as an integer' => [['page' => 2]];
    }

    /**
     * @param array<array-key, mixed> $query
     */
    #[DataProvider('strangeQueries')]
    public function testWhatAQueryStringDoesNotSayReadsAsTheDefault(array $query): void
    {
        $read = CatalogueQuery::fromQuery($query, 'not a language');
        self::assertSame([null, null, PriceFilter::Any, CatalogueSort::Placed, null, 1], [$read->text, $read->tag, $read->price, $read->sort, $read->language, $read->page]);
    }

    public function testInvalidUtf8IsScrubbed(): void
    {
        self::assertSame('a?b', CatalogueQuery::fromQuery(['q' => "a\xFFb"])->text);
        self::assertNull(CatalogueQuery::fromQuery(['tag' => "\xFF"])->tag);
    }

    public function testAnItemAndAPageSayWhereTheLearnerStands(): void
    {
        $course = new Course(CourseId::of('course'), 'Welding', new DateTimeImmutable());
        $offered = new OfferedCourse(new CatalogueEntry(CatalogueId::of('cat'), $course->id, 0), $course);
        $item = new CatalogueItem($offered);
        self::assertSame([$course, $offered->entry, false, false, false, null, null], [$item->course(), $item->entry(), $item->isEnrolled(), $item->completed, $item->assigned, $offered->group, $offered->language]);
        self::assertTrue(new CatalogueItem($offered, new Enrollment(EnrollmentId::generate(), $course->id, LearnerId::of('ada'), new DateTimeImmutable()))->isEnrolled());

        $catalogue = new Catalogue(CatalogueId::of('cat'), 'All', new DateTimeImmutable());
        $first = new CataloguePage($catalogue, CatalogueQuery::of(), [$item], 30, 2, []);
        self::assertSame([false, true], [$first->hasPrevious(), $first->hasNext()]);
        $last = new CataloguePage($catalogue, CatalogueQuery::of(page: 2), [$item], 30, 2, []);
        self::assertSame([true, false], [$last->hasPrevious(), $last->hasNext()]);
    }

    public function testARefusalSaysWhenTheCourseIsBought(): void
    {
        self::assertTrue(EnrollmentRefused::purchase()->purchase);
        self::assertSame('This course is bought before the enrollment.', EnrollmentRefused::purchase()->getMessage());
        $refused = EnrollmentRefused::because('Closed.');
        self::assertSame(['Closed.', false], [$refused->getMessage(), $refused->purchase]);
    }
}
