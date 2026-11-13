<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Unit;

use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Form\Check;
use Phil\PagedMedia\Pdf\Form\Fields;
use Phil\PagedMedia\Pdf\Form\FormWriter;
use Phil\PagedMedia\Pdf\Form\TextField;
use Phil\PagedMedia\Pdf\Form\Widget;
use Phil\PagedMedia\Template\AddressLabels;
use Phil\PagedMedia\Template\Calendar;
use Phil\PagedMedia\Template\DownloadCards;
use Phil\PagedMedia\Template\GiftCards;
use Phil\PagedMedia\Template\LargePrint;
use Phil\PagedMedia\Template\Layout;
use Phil\PagedMedia\Template\NameBadges;
use Phil\PagedMedia\Template\ProductLabels;
use Phil\PagedMedia\Template\ShelfLabels;
use Phil\PagedMedia\Tests\Unit\Fixture\TemplateScenarios;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Document::class)]
#[CoversClass(Fields::class)]
#[CoversClass(FormWriter::class)]
#[CoversClass(Widget::class)]
#[CoversClass(Check::class)]
#[CoversClass(TextField::class)]
#[CoversClass(Layout::class)]
#[CoversClass(AddressLabels::class)]
#[CoversClass(ProductLabels::class)]
#[CoversClass(NameBadges::class)]
#[CoversClass(ShelfLabels::class)]
#[CoversClass(GiftCards::class)]
#[CoversClass(DownloadCards::class)]
#[CoversClass(Calendar::class)]
#[CoversClass(LargePrint::class)]
final class TemplateScenariosTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function families(): iterable
    {
        foreach (TemplateScenarios::families() as $family) {
            yield $family => [$family];
        }
    }

    #[DataProvider('families')]
    public function testEveryTemplateWritesTheSameContentOnEverySheetAndWithEveryText(string $family): void
    {
        $scenarios = array_map(static fn (string $content): string => hash('sha256', $content), TemplateScenarios::of($family));
        $expected = TemplateScenarios::expected($family);

        self::assertNotSame([], $expected);
        self::assertSame(array_keys($expected), array_keys($scenarios));

        foreach ($expected as $name => $hash) {
            self::assertSame($hash, $scenarios[$name], 'The scenario '.$family.'/'.$name.' changed.');
        }
    }
}
