<?php

declare(strict_types=1);

namespace Phil\TypographyBundle\Tests\Unit;

use Phil\Typography\Typographers;
use Phil\TypographyBundle\Infrastructure\Twig\TypographyExtension;
use Phil\TypographyBundle\Infrastructure\Ujo\TypographyHtmlFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Markup;

#[CoversClass(TypographyExtension::class)]
#[CoversClass(TypographyHtmlFilter::class)]
final class IntegrationTest extends TestCase
{
    public function testTheTwigFilters(): void
    {
        $extension = new TypographyExtension(new Typographers('en', [], ['code'], false));

        self::assertSame('<p>“a” <code>"b"</code></p>', $extension->html('<p>"a" <code>"b"</code></p>'));
        self::assertSame('<p>«&nbsp;a&nbsp;»</p>', $extension->html('<p>"a"</p>', 'fr'));
        self::assertSame('<b>“a”</b>', $extension->html(new Markup('<b>"a"</b>', 'UTF-8')));
        self::assertSame('', $extension->html(null));
        self::assertSame('“a” <b>', $extension->text('"a" <b>'));
        self::assertSame('„a“', $extension->text('"a"', 'de'));
        self::assertSame('“a”', $extension->text(new Markup('"a"', 'UTF-8')));
        self::assertSame('', $extension->text(null));
    }

    public function testTheTwigFiltersFollowTheCurrentLocale(): void
    {
        $extension = new TypographyExtension(new Typographers('en', [], ['code'], false), new CurrentLocale('fr'));

        self::assertSame('<p>«&nbsp;a&nbsp;»</p>', $extension->html('<p>"a"</p>'));
        self::assertSame('„a“', $extension->html('"a"', 'de'));
        self::assertSame("«\u{A0}a\u{A0}»", $extension->text('"a"'));
        self::assertSame('„a“', $extension->text('"a"', 'de'));
    }

    public function testTheUjoFilter(): void
    {
        $typographers = new Typographers('en', [], ['code'], false);

        self::assertSame('<p>“a”…</p>', new TypographyHtmlFilter($typographers)->filter('<p>"a"...</p>'));
        self::assertSame('<p>«&nbsp;a&nbsp;»…</p>', new TypographyHtmlFilter($typographers, 'fr')->filter('<p>"a"...</p>'));
        self::assertSame('<p>«&nbsp;a&nbsp;»…</p>', new TypographyHtmlFilter($typographers, null, new CurrentLocale('fr'))->filter('<p>"a"...</p>'));
        self::assertSame('<p>„a“…</p>', new TypographyHtmlFilter($typographers, 'de', new CurrentLocale('fr'))->filter('<p>"a"...</p>'));
    }
}
