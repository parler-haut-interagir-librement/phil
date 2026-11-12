<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What the frames add to the images: the frame drawn from the tokens, the token of a game, pixel art and the name of a view
 * transition, as classes and a data attribute, never a style; carried by `Picture`, `Gallery`, `Lightbox` and `Hotspots`.
 */
#[CoversNothing]
final class ImageFramesTest extends KitTestCase
{
    public function testEachWordOfTheFrameIsAClass(): void
    {
        self::assertSame(
            '<img class="isImage hasRounded hasFramed hasRaised" src="/a.png" alt="A" width="1" height="1" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.png" alt="A" :width="1" :height="1" frame="rounded  framed raised" />'),
        );
        self::assertSame(
            '<img class="isImage hasToken" src="/pawn.png" alt="The knight" width="64" height="64" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/pawn.png" alt="The knight" :width="64" :height="64" frame="token" />'),
        );
    }

    public function testAnUnknownWordOfTheFrameIsLeftOut(): void
    {
        self::assertSame(
            '<img class="isImage hasRaised" src="/a.png" alt="A" width="1" height="1" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.png" alt="A" :width="1" :height="1" frame="golden raised hasRounded" />'),
        );
        self::assertSame(
            '<img class="isImage" src="/a.png" alt="A" width="1" height="1" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.png" alt="A" :width="1" :height="1" frame="" />'),
        );
    }

    public function testPixelArtIsAClass(): void
    {
        self::assertSame(
            '<img class="isImage hasRatio1x1 hasFitCover hasPixelated" src="/map.png" alt="The map" width="32" height="32" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/map.png" alt="The map" :width="32" :height="32" aspect="1/1" :pixelated="true" />'),
        );
    }

    public function testTheNameOfAViewTransitionIsAClassAndADataAttribute(): void
    {
        self::assertSame(
            '<img class="isImage hasTransition" src="/a.png" alt="A" width="1" height="1" loading="lazy" decoding="async" data-transition="poster-42">',
            $this->render('<twig:Image src="/a.png" alt="A" :width="1" :height="1" transition="poster-42" />'),
        );
        self::assertSame(
            '<img class="isImage hasTransition" src="/a.png" alt="A" width="1" height="1" loading="lazy" decoding="async" data-transition="-_cover">',
            $this->render('<twig:Image src="/a.png" alt="A" :width="1" :height="1" transition="-_cover" />'),
        );
    }

    #[DataProvider('refusedNames')]
    public function testANameThatIsNoIdentifierOrAKeywordIsLeftOut(string $name): void
    {
        self::assertSame(
            '<img class="isImage" src="/a.png" alt="A" width="1" height="1" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.png" alt="A" :width="1" :height="1" :transition="name" />', ['name' => $name]),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedNames(): iterable
    {
        yield 'a digit first' => ['42'];
        yield 'a space' => ['the poster'];
        yield 'a quote' => ['a"b'];
        yield 'a style' => ['a;color:red'];
        yield 'two dashes' => ['--poster'];
        yield 'none' => ['none'];
        yield 'auto, in capitals' => ['AUTO'];
        yield 'match-element' => ['match-element'];
        yield 'inherit' => ['inherit'];
        yield 'initial' => ['initial'];
        yield 'unset' => ['unset'];
        yield 'revert' => ['revert'];
        yield 'revert-layer' => ['revert-layer'];
        yield 'default' => ['default'];
        yield 'empty' => [''];
    }

    public function testAPictureHandsThemToItsImage(): void
    {
        self::assertSame(
            '<picture><source type="image/webp" srcset="/a.webp"><img class="isImage hasRounded hasPixelated hasTransition" src="/a.png" alt="A" width="1" height="1" loading="lazy" decoding="async" data-transition="hero"></picture>',
            $this->render('<twig:Picture src="/a.png" :sources="[{type: \'image/webp\', srcset: \'/a.webp\'}]" alt="A" :width="1" :height="1" frame="rounded" :pixelated="true" transition="hero" />'),
        );
    }

    public function testAGalleryFramesEveryImageAndNamesEachByItsOwnTransition(): void
    {
        $html = $this->render('<twig:Gallery label="Pawns" frame="token raised" :pixelated="true" :images="[{src: \'/a.png\', alt: \'A\', width: 8, height: 8, transition: \'pawn-a\'}, {src: \'/b.png\', alt: \'B\', width: 8, height: 8}]" />');

        self::assertSame(2, substr_count($html, 'class="isImage hasToken hasRaised hasPixelated'));
        self::assertSame(1, substr_count($html, 'hasTransition'));
        self::assertStringContainsString('data-transition="pawn-a"', $html);
    }

    public function testInALightboxTheThumbnailIsFramedAndNamedAndBothArePixelArt(): void
    {
        $html = $this->render('<twig:Gallery id="maps" label="Maps" frame="rounded" :pixelated="true" :lightbox="true" :images="[{src: \'/a.png\', alt: \'A\', width: 8, height: 8, transition: \'map-a\'}]" />');

        self::assertSame(1, substr_count($html, 'hasRounded'));
        self::assertSame(2, substr_count($html, 'hasPixelated'));
        self::assertMatchesRegularExpression('#<button[^>]*isLightboxTrigger.*?<img class="isImage hasRounded hasPixelated hasTransition"[^>]*data-transition="map-a"#s', $html);
        self::assertMatchesRegularExpression('#<dialog[^>]*>.*?<img class="isImage hasFitContain hasPixelated"#s', $html);
    }

    public function testALightboxAloneTakesThemToo(): void
    {
        $html = $this->render('<twig:Lightbox id="one" src="/a.png" alt="A" :width="8" :height="8" frame="framed" :pixelated="true" transition="one" />');

        self::assertSame(1, substr_count($html, 'class="isImage hasFramed hasPixelated hasTransition"'));
        self::assertSame(1, substr_count($html, 'class="isImage hasFitContain hasPixelated"'));
    }

    public function testThePointsStandOnAFramedImageOfPixelArtButNeverOnAToken(): void
    {
        $html = $this->render('<twig:Hotspots src="/board.png" alt="The board" :width="64" :height="32" label="Squares" frame="token rounded" :pixelated="true" :spots="[{x: 10, y: 10, label: \'Start\', href: \'#start\'}]" />');

        self::assertStringContainsString('<img class="isImage hasRounded hasPixelated" src="/board.png"', $html);
        self::assertStringNotContainsString('hasToken', $html);
    }

    public function testNoneOfThemIsAStyle(): void
    {
        $html = $this->render('<twig:Image src="/a.png" alt="A" :width="1" :height="1" frame="token raised framed rounded" :pixelated="true" transition="a" />');

        self::assertStringNotContainsString('style', $html);
    }
}
