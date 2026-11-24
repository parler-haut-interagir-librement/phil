<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Tests\Unit\Markup;

use Phil\SeoCheck\Markup\MarkupReader;
use Phil\SeoCheck\Markup\RobotsDirective;
use Phil\SeoCheck\Markup\RobotsDirectives;
use Phil\SeoCheck\Rendering;
use Phil\SeoCheck\Tests\Support\Pages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RobotsDirectives::class)]
#[CoversClass(RobotsDirective::class)]
#[CoversClass(Rendering::class)]
final class RobotsDirectivesTest extends TestCase
{
    /**
     * @param array<string, string|list<string>> $headers
     *
     * @return list<string>
     */
    private function read(string $head, array $headers = [], RobotsDirectives $directives = new RobotsDirectives()): array
    {
        $page = Pages::rendered(Pages::html($head), headers: $headers);

        return array_map(
            static fn (RobotsDirective $directive): string => $directive->source.' '.($directive->agent ?? '*').' '.$directive->name.($directive->forbidsIndexing() ? ' !' : ''),
            $directives->read(new Rendering($page, new MarkupReader()->read($page))),
        );
    }

    public function testItReadsTheMetaTagsAndTheHeaders(): void
    {
        self::assertSame([
            'meta * noindex !',
            'meta * nofollow',
            'meta googlebot none !',
            'header * max-snippet',
            'header * unavailable_after',
            'header bingbot noindex !',
            'header bingbot index',
        ], $this->read('<meta name="robots" content=" NoIndex ,, nofollow"><meta name="googlebot" content="none"><meta name="description" content="noindex">', ['X-Robots-Tag' => ['max-snippet: 20, unavailable_after: 2030-01-01T00:00:00Z', 'BingBot :noindex, index']]));
    }

    public function testTheCrawlersAreChosen(): void
    {
        self::assertSame(['meta ourbot noindex !'], $this->read('<meta name="googlebot" content="noindex"><meta name="ourbot" content="noindex">', [], new RobotsDirectives(['OurBot'])));
    }

    public function testEveryDirectiveWithAValueIsKnown(): void
    {
        self::assertSame(
            ['header * max-image-preview', 'header * max-video-preview'],
            $this->read('', ['X-Robots-Tag' => ['max-image-preview: large', 'MAX-VIDEO-PREVIEW: -1']]),
        );
    }

    public function testAtMostSoManyDirectivesAreRead(): void
    {
        self::assertCount(RobotsDirectives::MAX_DIRECTIVES, $this->read('<meta name="robots" content="'.str_repeat('index, ', 150).'">', ['X-Robots-Tag' => str_repeat('follow, ', 150)]));
        self::assertCount(RobotsDirectives::MAX_DIRECTIVES, $this->read('', ['X-Robots-Tag' => str_repeat('follow,', 300)]));
    }

    public function testAHeaderNamesACrawlerOnlyAtItsStart(): void
    {
        self::assertSame(['header * noarchive', 'header * googlebot'], $this->read('', ['X-Robots-Tag' => 'noarchive, googlebot: noindex']));
        self::assertSame(['header googlebot noindex !', 'header googlebot nofollow'], $this->read('', ['X-Robots-Tag' => "googlebot: noindex\n, nofollow"]));
    }
}
