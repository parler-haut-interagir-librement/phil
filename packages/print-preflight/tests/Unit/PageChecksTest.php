<?php

declare(strict_types=1);

namespace Phil\PrintPreflight\Tests\Unit;

use Phil\PrintPreflight\Check\PageCount;
use Phil\PrintPreflight\Check\PageGeometry;
use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\Length;
use Phil\PrintPreflight\Page;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Size;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageCount::class)]
#[CoversClass(PageGeometry::class)]
final class PageChecksTest extends TestCase
{
    public function testADocumentWithoutPagesHasOnlyThatError(): void
    {
        $findings = new PageCount()->check(new Document([]), new PrintProfile(minimumPages: 4, pageMultiple: 4));

        self::assertSame([['error', 'page-count.empty', 'The document has no page.', null]], self::describe($findings));
        self::assertSame([], new PageGeometry()->check(new Document([]), new PrintProfile()));
    }

    public function testThePageCountMeetsTheMinimumMaximumAndMultiple(): void
    {
        $profile = new PrintProfile(minimumPages: 8, maximumPages: 12, pageMultiple: 4);

        self::assertSame([], new PageCount()->check($this->pages(8), $profile));
        self::assertSame([], new PageCount()->check($this->pages(12), $profile));
        self::assertSame([], new PageCount()->check($this->pages(400), new PrintProfile()));
    }

    public function testTooFewPagesAreReported(): void
    {
        $findings = new PageCount()->check($this->pages(3), new PrintProfile(minimumPages: 4));

        self::assertSame([['error', 'page-count.below-minimum', 'The document has 3 pages; the printer needs at least 4.', null]], self::describe($findings));
        self::assertSame(['count' => 3, 'minimum' => 4], $findings[0]->parameters);
        self::assertSame('The document has 1 page; the printer needs at least 4.', new PageCount()->check(self::pages(1), new PrintProfile(minimumPages: 4))[0]->message);
    }

    public function testTooManyPagesAreReported(): void
    {
        $findings = new PageCount()->check($this->pages(5), new PrintProfile(maximumPages: 4));

        self::assertSame([['error', 'page-count.above-maximum', 'The document has 5 pages; the printer takes at most 4.', null]], self::describe($findings));
        self::assertSame(['count' => 5, 'maximum' => 4], $findings[0]->parameters);
    }

    public function testAPageCountOffTheMultipleSaysHowManyPagesToAdd(): void
    {
        $findings = new PageCount()->check($this->pages(9), new PrintProfile(pageMultiple: 4));

        self::assertSame([['error', 'page-count.multiple', 'The document has 9 pages, not a multiple of 4; add 3 blank pages or remove 1.', null]], self::describe($findings));
        self::assertSame(['count' => 9, 'multiple' => 4, 'missing' => 3], $findings[0]->parameters);
        self::assertSame('The document has 3 pages, not a multiple of 4; add 1 blank page or remove 3.', new PageCount()->check(self::pages(3), new PrintProfile(pageMultiple: 4))[0]->message);
    }

    public function testEveryPageCountRuleIsReported(): void
    {
        $findings = new PageCount()->check($this->pages(2), new PrintProfile(minimumPages: 4, pageMultiple: 4));

        self::assertSame(['page-count.below-minimum', 'page-count.multiple'], array_column(self::describe($findings), 1));
    }

    public function testPagesOfAnAcceptedSizeAndEnoughBleedPass(): void
    {
        $profile = new PrintProfile(trimSizes: [Size::a5()]);
        $document = new Document([new Page(Size::a5(), Length::mm(3)), new Page(Size::mm(148.1, 210), Length::mm(5))]);

        self::assertSame([], new PageGeometry()->check($document, $profile));
    }

    public function testAPageOfASizeThePrinterDoesNotPrintIsReported(): void
    {
        $profile = new PrintProfile(trimSizes: [Size::a5()], bleed: Length::pt(0));
        $findings = new PageGeometry()->check(new Document([new Page(Size::a5()), new Page(Size::a4())]), $profile);

        self::assertSame([['error', 'page-size.not-accepted', 'Page 2 is 210 × 297 mm, a trim size the printer does not print.', 2]], self::describe($findings));
        self::assertSame(['size' => '210 × 297 mm'], $findings[0]->parameters);
    }

    public function testPagesOfMixedSizesAreReported(): void
    {
        $document = new Document([new Page(Size::a4()), new Page(Size::a4()), new Page(Size::a5())]);
        $findings = new PageGeometry()->check($document, new PrintProfile(bleed: Length::pt(0)));

        self::assertSame([['error', 'page-size.mixed', 'Page 3 is 148 × 210 mm, unlike page 1 (210 × 297 mm).', 3]], self::describe($findings));
        self::assertSame(['size' => '148 × 210 mm', 'first' => '210 × 297 mm'], $findings[0]->parameters);
        self::assertSame([], new PageGeometry()->check($document, new PrintProfile(uniformTrimSize: false, bleed: Length::pt(0))));
    }

    public function testTooLittleBleedIsReported(): void
    {
        $findings = new PageGeometry()->check(new Document([new Page(Size::a4(), Length::mm(2))]), new PrintProfile());

        self::assertSame([['error', 'bleed.too-small', 'Page 1 has 2 mm of bleed; the printer needs 3 mm.', 1]], self::describe($findings));
        self::assertSame(['bleed' => '2 mm', 'required' => '3 mm'], $findings[0]->parameters);
    }

    public function testASizeAndABleedAreBothReportedForOnePage(): void
    {
        $findings = new PageGeometry()->check(new Document([new Page(Size::a4())]), new PrintProfile(trimSizes: [Size::a5()]));

        self::assertSame(['page-size.not-accepted', 'bleed.too-small'], array_column(self::describe($findings), 1));
    }

    private function pages(int $count): Document
    {
        return new Document(array_fill(0, $count, new Page(Size::a4())));
    }

    /**
     * @param list<Finding> $findings
     *
     * @return list<array{string, string, string, ?int}>
     */
    public static function describe(array $findings): array
    {
        return array_map(static fn (Finding $finding): array => [$finding->severity->value, $finding->code, $finding->message, $finding->page], $findings);
    }
}
