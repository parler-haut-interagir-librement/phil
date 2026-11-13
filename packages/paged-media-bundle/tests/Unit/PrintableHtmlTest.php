<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Tests\Unit;

use Phil\PagedMediaBundle\Application\PrintableHtml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrintableHtml::class)]
final class PrintableHtmlTest extends TestCase
{
    public function testAPageIsAWholeDocument(): void
    {
        self::assertSame(
            '<!doctype html><html lang="fr-CA"><head><meta charset="utf-8"><title>Livre &amp; règles</title><style>a{}</style><style>b{}</style></head><body><p>x</p></body></html>',
            new PrintableHtml('<p>x</p>', ['a{}', 'b{}'], 'fr-CA', 'Livre & règles')->document(),
        );
    }

    public function testADefaultPageHasNoStylesheetNorTitle(): void
    {
        self::assertSame('<!doctype html><html lang="en"><head><meta charset="utf-8"></head><body></body></html>', new PrintableHtml('')->document());
    }

    public function testNeitherAStylesheetNorTheLanguageLeavesItsPlace(): void
    {
        $document = new PrintableHtml('', ['a{content:"</style><script>"}'], '"><script>')->document();

        self::assertStringNotContainsString('<script>"}', str_replace('<\/style><script>', '', $document));
        self::assertStringContainsString('a{content:"<\/style><script>"}', $document);
        self::assertStringContainsString('lang="&quot;&gt;&lt;script&gt;"', $document);
    }
}
