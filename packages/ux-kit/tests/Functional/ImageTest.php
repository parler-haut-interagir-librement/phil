<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use Phil\UxKit\Tests\Functional\App\SourceType;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Twig\Error\RuntimeError;

/**
 * The two components of the `image` recipe: `Image`, one `img`, and `Picture`, its sources before that `img`.
 */
#[CoversNothing]
final class ImageTest extends KitTestCase
{
    public function testAnImageLoadsLazilyAndDecodesAsynchronously(): void
    {
        self::assertSame(
            '<img class="isImage" src="/photo.jpg" alt="A bridge" width="800" height="600" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/photo.jpg" alt="A bridge" :width="800" :height="600" />'),
        );
    }

    public function testThePriorityLoadsEagerlyAndFetchesFirst(): void
    {
        self::assertSame(
            '<img class="isImage" src="/cover.jpg" srcset="/cover-960.jpg 960w, /cover-1600.jpg 1600w" sizes="100vw" alt="The stage" width="1600" height="900" loading="eager" fetchpriority="high">',
            $this->render('<twig:Image src="/cover.jpg" :srcset="{960: \'/cover-960.jpg\', 1600: \'/cover-1600.jpg\'}" sizes="100vw" alt="The stage" :width="1600" :height="900" :priority="true" />'),
        );
    }

    public function testLoadingAndDecodingCanBeChosen(): void
    {
        self::assertSame(
            '<img class="isImage" src="/a.png" alt="A" width="1" height="1" loading="eager" decoding="sync">',
            $this->render('<twig:Image src="/a.png" alt="A" :width="1" :height="1" loading="eager" decoding="sync" />'),
        );
        self::assertSame(
            '<img class="isImage" src="/a.png" alt="A" width="1" height="1" loading="lazy" decoding="async" fetchpriority="high">',
            $this->render('<twig:Image src="/a.png" alt="A" :width="1" :height="1" :priority="true" loading="lazy" decoding="async" />'),
            'priority, then a choice that overrides it',
        );
        self::assertSame(
            '<img class="isImage" src="/a.png" alt="A" width="1" height="1" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.png" alt="A" :width="1" :height="1" loading="soon" decoding="later" />'),
            'an unknown value falls back to the default',
        );
    }

    public function testADensityMap(): void
    {
        self::assertSame(
            '<img class="isImage" src="/a.png" srcset="/a.png 1x, /a@2x.png 2x, /a@3x.png 3x" alt="A" width="96" height="96" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.png" :srcset="{1: \'/a.png\', 2: \'/a@2x.png\', 3: \'/a@3x.png\'}" alt="A" :width="96" :height="96" />'),
        );
    }

    public function testAWidthMapIsSizedAutomaticallyWhileLazy(): void
    {
        self::assertSame(
            '<img class="isImage" src="/a.jpg" srcset="/a-480.jpg 480w, /a-960.jpg 960w" sizes="auto, (min-width: 48rem) 50vw, 100vw" alt="A" width="960" height="640" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.jpg" :srcset="{480: \'/a-480.jpg\', 960: \'/a-960.jpg\'}" sizes="(min-width: 48rem) 50vw, 100vw" alt="A" :width="960" :height="640" />'),
        );
        self::assertSame(
            '<img class="isImage" src="/a.jpg" srcset="/a-480.jpg 480w" sizes="auto, 100vw" alt="A" width="960" height="640" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.jpg" srcset="/a-480.jpg 480w" sizes="auto, 100vw" alt="A" :width="960" :height="640" />'),
            'sizes already starting with auto is kept',
        );
        self::assertSame(
            '<img class="isImage" src="/a.jpg" alt="A" width="960" height="640" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.jpg" sizes="50vw" alt="A" :width="960" :height="640" />'),
            'no sizes without a srcset',
        );
    }

    public function testADecorativeImageDropsWhatWouldNameIt(): void
    {
        self::assertSame(
            '<img class="isImage" src="/swirl.svg" alt="" width="10" height="10" loading="lazy" decoding="async" id="swirl" data-depth="2">',
            $this->render('<twig:Image src="/swirl.svg" alt="" :width="10" :height="10" id="swirl" title="Swirl" aria-label="Swirl" aria-describedby="note" data-depth="2" />'),
        );
        self::assertSame(
            '<img class="isImage" src="/map.png" alt="The map" width="10" height="10" loading="lazy" decoding="async" title="Map" aria-describedby="note">',
            $this->render('<twig:Image src="/map.png" alt="The map" :width="10" :height="10" title="Map" aria-describedby="note" />'),
            'an image with a text alternative keeps them',
        );
    }

    public function testOnlyTheAllowedAttributesReachTheImage(): void
    {
        self::assertSame(
            '<img class="isImage rounded" src="/a.png" alt="A" width="1" height="1" loading="lazy" decoding="async" id="hero" title="Hero" crossorigin="anonymous" referrerpolicy="no-referrer" itemprop="image" draggable="false" data-controller="zoom" aria-describedby="credit">',
            $this->render('<twig:Image src="/a.png" alt="A" :width="1" :height="1" class="rounded" id="hero" title="Hero" crossorigin="anonymous" referrerpolicy="no-referrer" itemprop="image" draggable="false" data-controller="zoom" aria-describedby="credit" onerror="alert(1)" onload="x()" style="display:none" usemap="#m" hidden fetchpriority="low" />'),
        );
    }

    public function testACaptionPutsTheImageInAFigure(): void
    {
        self::assertSame(
            '<figure><img class="isImage" src="/map.png" alt="The map" width="10" height="10" loading="lazy" decoding="async"><figcaption>Where we meet</figcaption></figure>',
            $this->render('<twig:Image src="/map.png" alt="The map" :width="10" :height="10" caption="Where we meet" />'),
        );
        self::assertSame(
            '<img class="isImage" src="/map.png" alt="The map" width="10" height="10" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/map.png" alt="The map" :width="10" :height="10" caption="" />'),
            'an empty caption writes no figure',
        );
    }

    public function testADecorativeImageKeepsItsCaption(): void
    {
        self::assertSame(
            '<figure><img class="isImage" src="/swirl.svg" alt="" width="10" height="10" loading="lazy" decoding="async"><figcaption>Drawn by Ana</figcaption></figure>',
            $this->render('<twig:Image src="/swirl.svg" alt="" :width="10" :height="10" caption="Drawn by Ana" aria-label="Swirl" />'),
        );
    }

    public function testTheCaptionIsEscaped(): void
    {
        self::assertSame(
            '<figure><img class="isImage" src="/a.png" alt="A" width="1" height="1" loading="lazy" decoding="async"><figcaption>&lt;em&gt;A&lt;/em&gt; &amp; B</figcaption></figure>',
            $this->render('<twig:Image src="/a.png" alt="A" :width="1" :height="1" :caption="caption" />', ['caption' => '<em>A</em> & B']),
        );
        self::assertSame(
            '<figure><picture><source type="image/webp" srcset="/a.webp"><img class="isImage" src="/a.png" alt="A" width="1" height="1" loading="lazy" decoding="async"></picture><figcaption>&lt;script&gt;</figcaption></figure>',
            $this->render('<twig:Picture src="/a.png" :sources="[{type: \'image/webp\', srcset: \'/a.webp\'}]" alt="A" :width="1" :height="1" :caption="caption" />', ['caption' => '<script>']),
        );
    }

    public function testEverythingIsEscaped(): void
    {
        self::assertSame(
            '<img class="isImage" src="/a?b=1&amp;c=&quot;2&quot;" srcset="/a.png?x=&quot;&gt;&lt;script&gt; 2x" alt="&lt;b&gt;&quot;" width="1" height="1" loading="lazy" decoding="async" data-note="&lt;i&gt;">',
            $this->render('<twig:Image :src="src" :alt="alt" :srcset="srcset" :width="1" :height="1" data-note="<i>" />', ['src' => '/a?b=1&c="2"', 'alt' => '<b>"', 'srcset' => '/a.png?x="><script> 2x']),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedSchemes(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'javascript in capitals after spaces' => ['  JaVaScRiPt:alert(1)'];
        yield 'javascript split by a tab and a newline' => ["java\tscr\nipt:alert(1)"];
        yield 'javascript after a control character' => ["\x01javascript:alert(1)"];
        yield 'vbscript' => ['vbscript:msgbox(1)'];
        yield 'data that is not an image' => ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='];
        yield 'data in capitals' => ['DATA:text/html,<script>alert(1)</script>'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedUrls(): iterable
    {
        yield from self::refusedSchemes();
        yield 'empty' => [''];
    }

    #[DataProvider('refusedUrls')]
    public function testARefusedSourceRendersNothing(string $url): void
    {
        self::assertSame('', $this->render('<twig:Image :src="url" alt="A" :width="1" :height="1" />', ['url' => $url]));
        self::assertSame('', $this->render('<twig:Picture :src="url" alt="A" :width="1" :height="1" :sources="[{type: \'image/webp\', srcset: \'/a.webp\'}]" />', ['url' => $url]));
    }

    #[DataProvider('refusedSchemes')]
    public function testARefusedSetIsLeftOut(string $url): void
    {
        self::assertSame(
            '<img class="isImage" src="/a.png" alt="A" width="1" height="1" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.png" :srcset="{1: \'/a.png\', 2: url}" alt="A" :width="1" :height="1" />', ['url' => $url]),
        );
        self::assertSame(
            '<picture><source type="image/webp" srcset="/a.webp"><img class="isImage" src="/a.png" alt="A" width="1" height="1" loading="lazy" decoding="async"></picture>',
            $this->render('<twig:Picture src="/a.png" :sources="[{type: \'image/avif\', srcset: url}, {type: \'image/webp\', srcset: \'/a.webp\'}]" alt="A" :width="1" :height="1" />', ['url' => $url]),
        );
    }

    public function testADataImageIsAllowed(): void
    {
        self::assertSame(
            '<img class="isImage" src="data:image/png;base64,iVBORw0KGgo=" srcset="data:image/png;base64,iVBORw0KGgo= 1x" alt="A dot" width="1" height="1" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="data:image/png;base64,iVBORw0KGgo=" :srcset="{1: \'data:image/png;base64,iVBORw0KGgo=\'}" alt="A dot" :width="1" :height="1" />'),
        );
    }

    public function testASchemeLaterInThePathIsNotRefused(): void
    {
        self::assertSame(
            '<img class="isImage" src="/img/javascript:logo.png" alt="A" width="1" height="1" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/img/javascript:logo.png" alt="A" :width="1" :height="1" />'),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function requiredProps(): iterable
    {
        yield 'src' => ['alt="A" :width="1" :height="1"'];
        yield 'alt' => ['src="/a.png" :width="1" :height="1"'];
        yield 'width' => ['src="/a.png" alt="A" :height="1"'];
        yield 'height' => ['src="/a.png" alt="A" :width="1"'];
    }

    #[DataProvider('requiredProps')]
    public function testAnImageNeedsItsSourceTextAndSize(string $props): void
    {
        $this->expectException(RuntimeError::class);

        $this->render('<twig:Image '.$props.' />');
    }

    #[DataProvider('requiredProps')]
    public function testAPictureNeedsThemToo(string $props): void
    {
        $this->expectException(RuntimeError::class);

        $this->render('<twig:Picture '.$props.' />');
    }

    public function testAPictureOffersItsSourcesInTheirOrderBeforeTheImage(): void
    {
        self::assertSame(
            '<picture><source type="image/avif" srcset="/a.avif"><source type="image/webp" srcset="/a.webp"><img class="isImage" src="/a.jpg" alt="A bridge" width="800" height="600" loading="lazy" decoding="async"></picture>',
            $this->render('<twig:Picture src="/a.jpg" :sources="[{type: \'image/avif\', srcset: \'/a.avif\'}, {type: \'image/webp\', srcset: \'/a.webp\'}]" alt="A bridge" :width="800" :height="600" />'),
        );
    }

    public function testAPictureByDensityLikeTheStaticImagesOfASite(): void
    {
        self::assertSame(
            '<picture><source type="image/avif" srcset="/1x/n.avif 1x, /2x/n.avif 2x, /3x/n.avif 3x"><source type="image/webp" srcset="/1x/n.webp 1x, /2x/n.webp 2x, /3x/n.webp 3x"><img class="isImage" src="/1x/n.png" srcset="/1x/n.png 1x, /2x/n.png 2x, /3x/n.png 3x" alt="Network" width="300" height="200" loading="lazy" decoding="async"></picture>',
            $this->render(
                '<twig:Picture src="/1x/n.png" :srcset="sets.png" :sources="[{type: \'image/avif\', srcset: sets.avif}, {type: \'image/webp\', srcset: sets.webp}]" alt="Network" :width="300" :height="200" />',
                ['sets' => array_map(static fn (string $type): array => [1 => '/1x/n.'.$type, 2 => '/2x/n.'.$type, 3 => '/3x/n.'.$type], ['png' => 'png', 'avif' => 'avif', 'webp' => 'webp'])],
            ),
        );
    }

    public function testAPictureByWidthArtDirectionAndPriority(): void
    {
        self::assertSame(
            '<picture><source type="image/avif" srcset="/w-480.avif 480w, /w-960.avif 960w" sizes="50vw" media="(min-width: 48rem)"><source type="image/webp" srcset="/w-480.webp 480w" sizes="100vw"><img class="isImage hero" src="/w.jpg" srcset="/w-480.jpg 480w" sizes="100vw" alt="Wide" width="960" height="540" loading="eager" fetchpriority="high" id="cover"></picture>',
            $this->render('<twig:Picture src="/w.jpg" srcset="/w-480.jpg 480w" sizes="100vw" :sources="[{type: \'image/avif\', srcset: {480: \'/w-480.avif\', 960: \'/w-960.avif\'}, sizes: \'50vw\', media: \'(min-width: 48rem)\'}, {type: \'image/webp\', srcset: {480: \'/w-480.webp\'}}]" alt="Wide" :width="960" :height="540" :priority="true" class="hero" id="cover" onerror="x()" />'),
        );
    }

    public function testALazyPictureSizesItsSourcesAutomatically(): void
    {
        self::assertSame(
            '<picture><source type="image/webp" srcset="/a-480.webp 480w" sizes="auto, 50vw"><img class="isImage" src="/a.jpg" alt="A" width="1" height="1" loading="lazy" decoding="async"></picture>',
            $this->render('<twig:Picture src="/a.jpg" sizes="50vw" :sources="[{type: \'image/webp\', srcset: {480: \'/a-480.webp\'}}]" alt="A" :width="1" :height="1" />'),
        );
    }

    public function testAPictureLeavesOutASourceOfAnUnknownTypeOrWithoutCandidates(): void
    {
        self::assertSame(
            '<picture><source srcset="/a-wide.png" media="(min-width: 60rem)"><img class="isImage" src="/a.png" alt="A" width="1" height="1" loading="lazy" decoding="async"></picture>',
            $this->render('<twig:Picture src="/a.png" :sources="[{type: \'text/html\', srcset: \'/a.html\'}, {type: \'image/webp\'}, {srcset: \'/a-wide.png\', media: \'(min-width: 60rem)\'}]" alt="A" :width="1" :height="1" />'),
        );
        self::assertSame(
            '<img class="isImage" src="/a.png" alt="" width="1" height="1" loading="lazy" decoding="async">',
            $this->render('<twig:Picture src="/a.png" alt="" :width="1" :height="1" />'),
            'without sources, the image alone',
        );
    }

    public function testACaptionPutsThePictureInAFigure(): void
    {
        self::assertSame(
            '<figure><picture><source type="image/avif" srcset="/a.avif"><img class="isImage" src="/a.jpg" alt="" width="8" height="6" loading="lazy" decoding="async"></picture><figcaption>The bridge at dusk</figcaption></figure>',
            $this->render('<twig:Picture src="/a.jpg" :sources="[{type: \'image/avif\', srcset: \'/a.avif\'}]" alt="" :width="8" :height="6" caption="The bridge at dusk" />'),
        );
        self::assertSame(
            '<figure><img class="isImage" src="/a.jpg" alt="A" width="8" height="6" loading="lazy" decoding="async"><figcaption>Alone</figcaption></figure>',
            $this->render('<twig:Picture src="/a.jpg" alt="A" :width="8" :height="6" caption="Alone" />'),
            'without sources, the image alone in its figure',
        );
        self::assertSame('', $this->render('<twig:Picture src="javascript:x" alt="A" :width="8" :height="6" caption="C" />'), 'a refused src writes no figure');
    }

    public function testAConstrainedLayoutSizesItsWidthMap(): void
    {
        self::assertSame(
            '<img class="isImage" src="/a.jpg" srcset="/a-480.jpg 480w, /a-960.jpg 960w" sizes="auto, (min-width: 960px) 960px, 100vw" alt="A" width="960" height="640" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.jpg" :srcset="{480: \'/a-480.jpg\', 960: \'/a-960.jpg\'}" layout="constrained" alt="A" :width="960" :height="640" />'),
        );
        self::assertSame(
            '<img class="isImage" src="/a.jpg" srcset="/a-480.jpg 480w" sizes="50vw" alt="A" width="960" height="640" loading="eager" fetchpriority="high">',
            $this->render('<twig:Image src="/a.jpg" srcset="/a-480.jpg 480w" sizes="50vw" layout="constrained" alt="A" :width="960" :height="640" :priority="true" />'),
            'a sizes given wins over the layout',
        );
    }

    public function testAFullWidthLayoutFillsItsContainer(): void
    {
        self::assertSame(
            '<img class="isImage hasLayoutFullWidth" src="/a.jpg" srcset="/a-960.jpg 960w, /a-1920.jpg 1920w" sizes="auto, 100vw" alt="A" width="1920" height="1080" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.jpg" :srcset="{960: \'/a-960.jpg\', 1920: \'/a-1920.jpg\'}" layout="full-width" alt="A" :width="1920" :height="1080" />'),
        );
    }

    public function testAFixedLayoutKeepsItsSizeAndReadsDensities(): void
    {
        self::assertSame(
            '<img class="isImage hasLayoutFixed" src="/a.png" srcset="/a.png 1x, /a@2x.png 2x" alt="A" width="96" height="96" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.png" :srcset="{1: \'/a.png\', 2: \'/a@2x.png\'}" layout="fixed" alt="A" :width="96" :height="96" />'),
        );
        self::assertSame(
            '<img class="isImage" src="/a.png" alt="A" width="96" height="96" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.png" layout="stretched" alt="A" :width="96" :height="96" />'),
            'an unknown layout is left out',
        );
    }

    public function testAnAspectRatioCoversByDefault(): void
    {
        self::assertSame(
            '<img class="isImage hasRatio16x9 hasFitCover" src="/a.jpg" alt="A" width="800" height="600" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.jpg" aspect="16/9" alt="A" :width="800" :height="600" />'),
        );
        self::assertSame(
            '<img class="isImage hasRatio1x1 hasFitContain hasPositionTop rounded" src="/a.jpg" alt="A" width="800" height="600" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.jpg" aspect="1 / 1" fit="contain" position="top" class="rounded" alt="A" :width="800" :height="600" />'),
        );
        self::assertSame(
            '<img class="isImage hasRatio hasFitCover hero" src="/a.jpg" alt="A" width="800" height="600" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.jpg" aspect="custom" class="hero" alt="A" :width="800" :height="600" />'),
            'a custom ratio read from --phil-image-ratio, set by a class of the site',
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function ratios(): iterable
    {
        yield '16/9' => ['16/9', 'hasRatio16x9'];
        yield '4/3' => ['4/3', 'hasRatio4x3'];
        yield '3/2' => ['3/2', 'hasRatio3x2'];
        yield '1/1' => ['1/1', 'hasRatio1x1'];
        yield '21/9' => ['21/9', 'hasRatio21x9'];
        yield '3/4' => ['3/4', 'hasRatio3x4'];
        yield '2/3' => ['2/3', 'hasRatio2x3'];
        yield '9/16' => ['9/16', 'hasRatio9x16'];
    }

    #[DataProvider('ratios')]
    public function testEachRatioHasItsClass(string $aspect, string $class): void
    {
        self::assertStringStartsWith('<img class="isImage '.$class.' hasFitCover"', $this->render('<twig:Image src="/a.jpg" :aspect="aspect" alt="A" :width="1" :height="1" />', ['aspect' => $aspect]));
    }

    public function testFitAndPositionWithoutRatioAndUnknownValues(): void
    {
        self::assertSame(
            '<img class="isImage hasFitCover hasPositionBottom" src="/a.jpg" alt="A" width="1" height="1" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.jpg" fit="cover" position="bottom" alt="A" :width="1" :height="1" />'),
        );
        self::assertSame(
            '<img class="isImage" src="/a.jpg" alt="A" width="1" height="1" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.jpg" aspect="5/4" fit="fill" position="middle" alt="A" :width="1" :height="1" />'),
        );
    }

    public function testAPictureCarriesTheLayoutAndTheRatioToItsImageAndSources(): void
    {
        self::assertSame(
            '<picture><source type="image/avif" srcset="/a-480.avif 480w, /a-960.avif 960w" sizes="auto, (min-width: 960px) 960px, 100vw"><img class="isImage hasRatio4x3 hasFitCover hasPositionLeft" src="/a.jpg" alt="A" width="960" height="720" loading="lazy" decoding="async"></picture>',
            $this->render('<twig:Picture src="/a.jpg" :sources="[{type: \'image/avif\', srcset: {480: \'/a-480.avif\', 960: \'/a-960.avif\'}}]" layout="constrained" aspect="4/3" position="left" alt="A" :width="960" :height="720" />'),
        );
    }

    public function testArtDirectionPutsTheMediaSourcesFirstEachInItsTypeOrder(): void
    {
        self::assertSame(
            '<picture>'
            .'<source type="image/avif" srcset="/narrow.avif" media="(max-width: 40rem)" width="400" height="400">'
            .'<source type="image/webp" srcset="/narrow.webp" media="(max-width: 40rem)" width="400" height="400">'
            .'<source type="image/avif" srcset="/medium.avif" media="(max-width: 64rem)">'
            .'<source type="image/avif" srcset="/wide.avif">'
            .'<source type="image/webp" srcset="/wide.webp">'
            .'<source srcset="/wide.jxl">'
            .'<img class="isImage" src="/wide.jpg" alt="A" width="1600" height="900" loading="lazy" decoding="async"></picture>',
            $this->render('<twig:Picture src="/wide.jpg" :sources="[
                {srcset: \'/wide.jxl\'},
                {type: \'image/webp\', srcset: \'/wide.webp\'},
                {type: \'image/webp\', srcset: \'/narrow.webp\', media: \'(max-width: 40rem)\', width: 400, height: 400},
                {type: \'image/avif\', srcset: \'/wide.avif\'},
                {type: \'image/avif\', srcset: \'/narrow.avif\', media: \'(max-width: 40rem)\', width: 400, height: 400},
                {type: \'image/avif\', srcset: \'/medium.avif\', media: \'(max-width: 64rem)\'},
            ]" alt="A" :width="1600" :height="900" />'),
        );
    }

    public function testAMediaOfTheWrongShapeLeavesItsSourceOutAndASizeOfTheWrongShapeIsDropped(): void
    {
        self::assertSame(
            '<picture><source type="image/webp" srcset="/b.webp" media="(min-width: 40rem)"><source type="image/webp" srcset="/c.webp"><img class="isImage" src="/a.png" alt="A" width="1" height="1" loading="lazy" decoding="async"></picture>',
            $this->render('<twig:Picture src="/a.png" :sources="[{type: \'image/webp\', srcset: \'/a.webp\', media: \'screen{}<x>\'}, {type: \'image/webp\', srcset: \'/b.webp\', media: \'(min-width: 40rem)\', width: \'wide\', height: -3}, {type: \'image/webp\', srcset: \'/c.webp\', media: \'\'}]" alt="A" :width="1" :height="1" />'),
        );
    }

    public function testAPictureEscapesItsSources(): void
    {
        self::assertSame(
            '<picture><source type="image/webp" srcset="/a.webp?&quot;&gt;" media="(min-width: 1px) and &quot;x&quot; &amp; y"><img class="isImage" src="/a.png" alt="A" width="1" height="1" loading="lazy" decoding="async"></picture>',
            $this->render('<twig:Picture src="/a.png" :sources="[{type: \'image/webp\', srcset: srcset, media: media}]" alt="A" :width="1" :height="1" />', ['srcset' => '/a.webp?">', 'media' => '(min-width: 1px) and "x" & y']),
        );
    }

    public function testThePriorityPreloadsTheImageThroughWebLink(): void
    {
        self::assertSame(
            [
                '<img class="isImage" src="/cover.jpg" srcset="/cover-960.jpg 960w, /cover-1600.jpg 1600w" sizes="100vw" alt="The stage" width="1600" height="900" loading="eager" fetchpriority="high">',
                [[['preload'], '/cover.jpg', ['as' => 'image', 'imagesrcset' => '/cover-960.jpg 960w, /cover-1600.jpg 1600w', 'imagesizes' => '100vw', 'fetchpriority' => 'high']]],
            ],
            $this->renderInRequest('<twig:Image src="/cover.jpg" :srcset="{960: \'/cover-960.jpg\', 1600: \'/cover-1600.jpg\'}" sizes="100vw" alt="The stage" :width="1600" :height="900" :priority="true" />'),
        );
        self::assertSame(
            [[['preload'], '/cover.jpg', ['as' => 'image', 'fetchpriority' => 'high', 'crossorigin' => 'anonymous', 'referrerpolicy' => 'no-referrer']]],
            $this->renderInRequest('<twig:Image src="/cover.jpg" alt="The stage" :width="16" :height="9" :priority="true" crossorigin="anonymous" referrerpolicy="no-referrer" />')[1],
            'the preload asks as the image does',
        );
    }

    public function testOnlyThePriorityPreloadsAndItCanBeLeftOut(): void
    {
        self::assertSame([], $this->renderInRequest('<twig:Image src="/a.jpg" alt="A" :width="1" :height="1" />')[1]);
        self::assertSame(
            ['<img class="isImage" src="/a.jpg" alt="A" width="1" height="1" loading="eager" fetchpriority="high">', []],
            $this->renderInRequest('<twig:Image src="/a.jpg" alt="A" :width="1" :height="1" :priority="true" :preload="false" />'),
        );
        self::assertSame(
            [[['preload'], '/a.jpg', ['as' => 'image']]],
            $this->renderInRequest('<twig:Image src="/a.jpg" alt="A" :width="1" :height="1" :preload="true" />')[1],
            'a preload without priority',
        );
    }

    public function testAPicturePreloadsItsFirstSourceWithItsType(): void
    {
        self::assertSame(
            [
                '<picture><source type="image/avif" srcset="/a.avif 1x, /a@2x.avif 2x"><source type="image/webp" srcset="/a.webp"><img class="isImage" src="/a.jpg" alt="A" width="8" height="6" loading="eager" fetchpriority="high"></picture>',
                [[['preload'], '/a.jpg', ['as' => 'image', 'imagesrcset' => '/a.avif 1x, /a@2x.avif 2x', 'type' => 'image/avif', 'fetchpriority' => 'high']]],
            ],
            $this->renderInRequest('<twig:Picture src="/a.jpg" :sources="[{type: \'image/webp\', srcset: \'/a.webp\'}, {type: \'image/avif\', srcset: {1: \'/a.avif\', 2: \'/a@2x.avif\'}}]" alt="A" :width="8" :height="6" :priority="true" />'),
        );
        self::assertSame(
            [[['preload'], '/a.jpg', ['as' => 'image', 'fetchpriority' => 'high']]],
            $this->renderInRequest('<twig:Picture src="/a.jpg" alt="A" :width="8" :height="6" :priority="true" />')[1],
            'without sources, the image preloads itself',
        );
    }

    public function testArtDirectionWritesNoPreload(): void
    {
        self::assertSame([], $this->renderInRequest('<twig:Picture src="/a.jpg" :sources="[{type: \'image/avif\', srcset: \'/narrow.avif\', media: \'(max-width: 40rem)\'}]" alt="A" :width="8" :height="6" :priority="true" />')[1]);
        self::assertSame([], $this->renderInRequest('<twig:Picture src="/a.jpg" dark="/a-dark.jpg" alt="A" :width="8" :height="6" :priority="true" />')[1], 'nor does a dark variant');
    }

    public function testADarkVariantIsOfferedFirstWhereTheSystemIsDark(): void
    {
        self::assertSame(
            '<picture><source srcset="/logo-dark.png 1x, /logo-dark@2x.png 2x" media="(prefers-color-scheme: dark)"><source type="image/webp" srcset="/logo.webp"><img class="isImage" src="/logo.png" alt="Studio" width="160" height="40" loading="lazy" decoding="async"></picture>',
            $this->render('<twig:Picture src="/logo.png" :sources="[{type: \'image/webp\', srcset: \'/logo.webp\'}]" :dark="{1: \'/logo-dark.png\', 2: \'/logo-dark@2x.png\'}" alt="Studio" :width="160" :height="40" />'),
        );
        self::assertSame(
            '<picture><source type="image/avif" srcset="/d.avif" media="(prefers-color-scheme: dark)"><source type="image/webp" srcset="/d.webp" media="(prefers-color-scheme: dark)"><img class="isImage" src="/a.png" alt="A" width="1" height="1" loading="lazy" decoding="async"></picture>',
            $this->render('<twig:Picture src="/a.png" :dark="[{type: \'image/webp\', srcset: \'/d.webp\'}, {type: \'image/avif\', srcset: \'/d.avif\', media: \'print\'}]" alt="A" :width="1" :height="1" />'),
            'dark sources of their own types, their media replaced',
        );
    }

    public function testAStillImageIsOfferedFirstWhereMotionIsReduced(): void
    {
        self::assertSame(
            '<picture><source srcset="/spin-still.png" media="(prefers-reduced-motion: reduce)"><source srcset="/spin-dark.gif" media="(prefers-color-scheme: dark)"><source type="image/avif" srcset="/spin.avif"><img class="isImage" src="/spin.gif" alt="A spinning top" width="4" height="4" loading="lazy" decoding="async"></picture>',
            $this->render('<twig:Picture src="/spin.gif" :sources="[{type: \'image/avif\', srcset: \'/spin.avif\'}]" dark="/spin-dark.gif" still="/spin-still.png" alt="A spinning top" :width="4" :height="4" />'),
        );
        self::assertSame(
            '<picture><source type="image/webp" srcset="/still.webp" media="(prefers-reduced-motion: reduce)"><img class="isImage" src="/spin.gif" alt="A" width="4" height="4" loading="lazy" decoding="async"></picture>',
            $this->render('<twig:Picture src="/spin.gif" :still="[{type: \'image/webp\', srcset: \'/still.webp\'}, {type: \'text/plain\', srcset: \'/x\'}]" alt="A" :width="4" :height="4" />'),
        );
    }

    public function testARefusedDarkOrStillVariantIsLeftOut(): void
    {
        self::assertSame(
            '<img class="isImage" src="/a.png" alt="A" width="1" height="1" loading="lazy" decoding="async">',
            $this->render('<twig:Picture src="/a.png" dark="javascript:x" still="https://tracker.example.com/s.png" alt="A" :width="1" :height="1" />'),
        );
    }

    public function testACreditAndALicenseFollowTheCaption(): void
    {
        self::assertSame(
            '<figure><img class="isImage" src="/bridge.jpg" alt="A bridge" width="8" height="6" loading="lazy" decoding="async"><figcaption>The bridge at dusk<small class="isCredit">Photo: Ana Lima</small><small class="isLicense"><a href="https://creativecommons.org/licenses/by/4.0/" rel="license">CC BY 4.0</a></small></figcaption></figure>',
            $this->render('<twig:Image src="/bridge.jpg" alt="A bridge" :width="8" :height="6" caption="The bridge at dusk" credit="Photo: Ana Lima" license="CC BY 4.0" licenseHref="https://creativecommons.org/licenses/by/4.0/" />'),
        );
    }

    public function testACreditAloneMakesAFigureAndALicenseWithoutLinkIsText(): void
    {
        self::assertSame(
            '<figure><img class="isImage" src="/a.jpg" alt="" width="1" height="1" loading="lazy" decoding="async"><figcaption><small class="isCredit">Ana Lima</small><small class="isLicense">All rights reserved</small></figcaption></figure>',
            $this->render('<twig:Image src="/a.jpg" alt="" :width="1" :height="1" credit="Ana Lima" license="All rights reserved" licenseHref="javascript:alert(1)" />'),
        );
        self::assertSame(
            '<figure><picture><source type="image/webp" srcset="/a.webp"><img class="isImage" src="/a.jpg" alt="A" width="1" height="1" loading="lazy" decoding="async"></picture><figcaption><small class="isLicense"><a href="/licence" rel="license">&lt;CC0&gt;</a></small></figcaption></figure>',
            $this->render('<twig:Picture src="/a.jpg" :sources="[{type: \'image/webp\', srcset: \'/a.webp\'}]" alt="A" :width="1" :height="1" license="<CC0>" licenseHref="/licence" />'),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function creations(): iterable
    {
        yield 'a photograph' => ['digitalCapture', 'Digital capture sampled from real life'];
        yield 'edited by people' => ['humanEdits', 'Human-edited media'];
        yield 'edited with generative AI' => ['compositeWithTrainedAlgorithmicMedia', 'Edited using Generative AI'];
        yield 'created with generative AI' => ['trainedAlgorithmicMedia', 'Created using Generative AI'];
        yield 'a retired code' => ['digitalArt', 'Digital art'];
    }

    #[DataProvider('creations')]
    public function testTheCreationIsSaidInTheCaptionInTheWordsOfTheVocabulary(string $code, string $label): void
    {
        self::assertSame(
            '<figure><img class="isImage" src="/a.jpg" alt="A" width="1" height="1" loading="lazy" decoding="async"><figcaption><small class="isCreation" data-digital-source-type="'.$code.'">'.$label.'</small></figcaption></figure>',
            $this->render('<twig:Image src="/a.jpg" alt="A" :width="1" :height="1" :creation="creation" />', ['creation' => $code]),
        );
    }

    public function testTheCreationTakesAnEnumAndALabelOfTheSite(): void
    {
        self::assertSame(
            '<figure><img class="isImage" src="/a.jpg" alt="A" width="1" height="1" loading="lazy" decoding="async"><figcaption>Dusk<small class="isCreation" data-digital-source-type="trainedAlgorithmicMedia">Image générée par IA</small></figcaption></figure>',
            $this->render('<twig:Image src="/a.jpg" alt="A" :width="1" :height="1" caption="Dusk" :creation="creation" creationLabel="Image générée par IA" />', ['creation' => SourceType::Generated]),
        );
        self::assertSame(
            '<img class="isImage" src="/a.jpg" alt="A" width="1" height="1" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.jpg" alt="A" :width="1" :height="1" creation="madeUp" />'),
            'a code outside the vocabulary says nothing',
        );
    }

    public function testAnImageOfAListedHostKeepsTheReferrerToItself(): void
    {
        self::assertSame(
            '<img class="isImage" src="https://images.example.com/a.jpg" srcset="https://images.example.com/a@2x.jpg 2x" alt="A" width="1" height="1" loading="lazy" decoding="async" referrerpolicy="no-referrer">',
            $this->render('<twig:Image src="https://images.example.com/a.jpg" srcset="https://images.example.com/a@2x.jpg 2x" alt="A" :width="1" :height="1" />'),
        );
        self::assertSame(
            '<img class="isImage" src="https://eu.images.example.com/a.jpg" alt="A" width="1" height="1" loading="lazy" decoding="async" referrerpolicy="origin">',
            $this->render('<twig:Image src="https://eu.images.example.com/a.jpg" alt="A" :width="1" :height="1" referrerpolicy="origin" />'),
            'a policy given wins',
        );
    }

    public function testAnImageOfAnotherHostIsRefused(): void
    {
        self::assertSame('', $this->render('<twig:Image src="https://tracker.example.com/p.gif" alt="" :width="1" :height="1" />'));
        self::assertSame('', $this->render('<twig:Image src="//tracker.example.com/p.gif" alt="" :width="1" :height="1" />'));
        self::assertSame('', $this->render('<twig:Picture src="https://tracker.example.com/p.gif" alt="" :width="1" :height="1" caption="C" />'));
        self::assertSame(
            '<img class="isImage" src="/a.jpg" alt="A" width="1" height="1" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.jpg" srcset="/a.jpg 1x, https://tracker.example.com/a.jpg 2x" alt="A" :width="1" :height="1" />'),
            'a set holding another host is left out',
        );
    }

    public function testTheOwnOriginOfThePageIsLocal(): void
    {
        self::assertSame(
            '<img class="isImage" src="https://www.example.org/a.jpg" alt="A" width="1" height="1" loading="lazy" decoding="async">',
            $this->renderInRequest('<twig:Image src="https://www.example.org/a.jpg" alt="A" :width="1" :height="1" />')[0],
        );
    }

    public function testAPictureLeavesOutTheSourcesOfAnotherHostAndKeepsTheReferrerFromListedOnes(): void
    {
        self::assertSame(
            '<picture><source type="image/webp" srcset="https://images.example.com/a.webp"><img class="isImage" src="/a.jpg" alt="A" width="1" height="1" loading="lazy" decoding="async" referrerpolicy="no-referrer"></picture>',
            $this->render('<twig:Picture src="/a.jpg" :sources="[{type: \'image/avif\', srcset: \'https://tracker.example.com/a.avif\'}, {type: \'image/webp\', srcset: \'https://images.example.com/a.webp\'}]" alt="A" :width="1" :height="1" />'),
        );
    }

    public function testImageSourcesReadsTheAssetsOfAssetMapper(): void
    {
        self::assertMatchesRegularExpression(
            '#^<picture><source type="image/webp" srcset="/assets/images/logo-[\w-]+\.webp 1x, /assets/images/logo@2x-[\w-]+\.webp 2x"><img class="isImage" src="/assets/images/logo-[\w-]+\.png" srcset="/assets/images/logo-[\w-]+\.png 1x, /assets/images/logo@2x-[\w-]+\.png 2x" alt="Studio" width="160" height="40" loading="lazy" decoding="async"></picture>$#',
            $this->render('<twig:Picture {{ ...image_sources(\'images/logo\') }} alt="Studio" />'),
        );
    }

    public function testADeferredImageIsFetchedLast(): void
    {
        self::assertSame(
            '<img class="isImage" src="/a.jpg" alt="A" width="1" height="1" loading="lazy" decoding="async" fetchpriority="low">',
            $this->render('<twig:Image src="/a.jpg" alt="A" :width="1" :height="1" :deferred="true" />'),
        );
        self::assertSame(
            '<picture><source type="image/webp" srcset="/a.webp"><img class="isImage" src="/a.jpg" alt="A" width="1" height="1" loading="lazy" decoding="async" fetchpriority="low"></picture>',
            $this->render('<twig:Picture src="/a.jpg" :sources="[{type: \'image/webp\', srcset: \'/a.webp\'}]" alt="A" :width="1" :height="1" :deferred="true" />'),
        );
        self::assertSame(
            '<img class="isImage" src="/a.jpg" alt="A" width="1" height="1" loading="eager" fetchpriority="high">',
            $this->render('<twig:Image src="/a.jpg" alt="A" :width="1" :height="1" :deferred="true" :priority="true" />'),
            'the priority wins',
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function placeholders(): iterable
    {
        yield 'neutral' => ['neutral', 'hasPlaceholderNeutral'];
        yield 'brand' => ['brand', 'hasPlaceholderBrand'];
        yield 'red' => ['red', 'hasPlaceholderRed'];
        yield 'green' => ['green', 'hasPlaceholderGreen'];
        yield 'amber' => ['amber', 'hasPlaceholderAmber'];
        yield 'blue' => ['blue', 'hasPlaceholderBlue'];
        yield 'violet' => ['violet', 'hasPlaceholderViolet'];
        yield 'custom' => ['custom', 'hasPlaceholder'];
    }

    #[DataProvider('placeholders')]
    public function testAPlaceholderPaintsTheBoxWithAColourOfTheTokens(string $placeholder, string $class): void
    {
        self::assertSame(
            '<img class="isImage hasRatio16x9 hasFitCover '.$class.' rounded" src="/a.jpg" alt="A" width="16" height="9" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.jpg" alt="A" :width="16" :height="9" aspect="16/9" :placeholder="placeholder" class="rounded" />', ['placeholder' => $placeholder]),
        );
    }

    public function testAnUnknownPlaceholderPaintsNothingAndAPictureCarriesItToItsImage(): void
    {
        self::assertSame(
            '<img class="isImage" src="/a.jpg" alt="A" width="1" height="1" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.jpg" alt="A" :width="1" :height="1" placeholder="#336699" />'),
        );
        self::assertSame(
            '<picture><source type="image/webp" srcset="/a.webp"><img class="isImage hasPlaceholderBlue" src="/a.jpg" alt="A" width="1" height="1" loading="lazy" decoding="async"></picture>',
            $this->render('<twig:Picture src="/a.jpg" :sources="[{type: \'image/webp\', srcset: \'/a.webp\'}]" alt="A" :width="1" :height="1" placeholder="blue" />'),
        );
    }

    public function testAPreviewIsHandedToItsControllerWithoutAStyle(): void
    {
        self::assertSame(
            '<img class="isImage hasPreview" src="/a.jpg" alt="A" width="8" height="6" loading="lazy" decoding="async" data-controller="image-preview" data-image-preview-src-value="data:image/webp;base64,UklGRhoAAABXRUJQ+/8=">',
            $this->render('<twig:Image src="/a.jpg" alt="A" :width="8" :height="6" preview="data:image/webp;base64,UklGRhoAAABXRUJQ+/8=" />'),
        );
        self::assertSame(
            '<img class="isImage hasPreview" src="/a.jpg" alt="A" width="8" height="6" loading="lazy" decoding="async" id="hero" data-controller="zoom image-preview" data-image-preview-src-value="data:image/png;base64,iVBORw0KGgo=">',
            $this->render('<twig:Image src="/a.jpg" alt="A" :width="8" :height="6" preview="data:image/png;base64,iVBORw0KGgo=" id="hero" data-controller="zoom" />'),
            'beside a controller of the page',
        );
        self::assertSame(
            '<picture><source type="image/webp" srcset="/a.webp"><img class="isImage hasPreview" src="/a.jpg" alt="A" width="8" height="6" loading="lazy" decoding="async" data-controller="image-preview" data-image-preview-src-value="data:image/jpeg;base64,/9j/4AAQ"></picture>',
            $this->render('<twig:Picture src="/a.jpg" :sources="[{type: \'image/webp\', srcset: \'/a.webp\'}]" alt="A" :width="8" :height="6" preview="data:image/jpeg;base64,/9j/4AAQ" />'),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedPreviews(): iterable
    {
        yield 'an address' => ['/a-tiny.jpg'];
        yield 'an address of another host' => ['https://images.example.com/a-tiny.jpg'];
        yield 'an inline SVG' => ['data:image/svg+xml;base64,PHN2Zz4='];
        yield 'not in base 64' => ['data:image/png,abc'];
        yield 'a quote that would end the URL of CSS' => ['data:image/png;base64,ab")c'];
        yield 'a script' => ['javascript:alert(1)'];
        yield 'empty' => [''];
    }

    #[DataProvider('refusedPreviews')]
    public function testAPreviewIsATinyInlineImageOrNothing(string $preview): void
    {
        self::assertSame(
            '<img class="isImage" src="/a.jpg" alt="A" width="8" height="6" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.jpg" alt="A" :width="8" :height="6" :preview="preview" />', ['preview' => $preview]),
        );
    }

    public function testAForcedColoursVariantIsOfferedBeforeEveryOther(): void
    {
        self::assertSame(
            '<picture><source srcset="/map-forced.png" media="(forced-colors: active)"><source srcset="/map-still.png" media="(prefers-reduced-motion: reduce)"><source srcset="/map-dark.png" media="(prefers-color-scheme: dark)"><source type="image/webp" srcset="/map.webp"><img class="isImage" src="/map.png" alt="The map" width="8" height="6" loading="lazy" decoding="async"></picture>',
            $this->render('<twig:Picture src="/map.png" :sources="[{type: \'image/webp\', srcset: \'/map.webp\'}]" dark="/map-dark.png" still="/map-still.png" forced="/map-forced.png" alt="The map" :width="8" :height="6" />'),
        );
        self::assertSame(
            '<picture><source type="image/svg+xml" srcset="/map-forced.svg" media="(forced-colors: active)"><img class="isImage" src="/map.png" alt="The map" width="8" height="6" loading="lazy" decoding="async"></picture>',
            $this->render('<twig:Picture src="/map.png" :forced="[{type: \'image/svg+xml\', srcset: \'/map-forced.svg\'}, {srcset: \'https://tracker.example.com/x.png\'}]" alt="The map" :width="8" :height="6" />'),
            'sources of their own types, one of another host left out',
        );
        self::assertSame([], $this->renderInRequest('<twig:Picture src="/a.jpg" forced="/a-forced.png" alt="A" :width="8" :height="6" :priority="true" />')[1], 'nor is it preloaded');
    }

    public function testADescriptionIsADisclosureTheImageIsDescribedBy(): void
    {
        self::assertSame(
            '<figure><img class="isImage" src="/chart.png" alt="Visits by month" width="8" height="6" loading="lazy" decoding="async" id="visits" aria-describedby="visits-description">'
            .'<details class="isDescription"><summary>Description</summary><p id="visits-description">Visits rose from 12 in January to 30 in February.</p></details></figure>',
            $this->render('<twig:Image src="/chart.png" alt="Visits by month" :width="8" :height="6" id="visits" description="Visits rose from 12 in January to 30 in February." />'),
        );
        self::assertSame(
            '<figure><picture><source type="image/webp" srcset="/chart.webp"><img class="isImage" src="/chart.png" alt="Visits" width="8" height="6" loading="lazy" decoding="async" id="visits" aria-describedby="note visits-description"></picture>'
            .'<details class="isDescription"><summary>Long description</summary><p id="visits-description">&lt;b&gt;Rising&lt;/b&gt;</p></details><figcaption>Visits in 2026</figcaption></figure>',
            $this->render('<twig:Picture src="/chart.png" :sources="[{type: \'image/webp\', srcset: \'/chart.webp\'}]" alt="Visits" :width="8" :height="6" id="visits" aria-describedby="note" caption="Visits in 2026" description="<b>Rising</b>" descriptionLabel="Long description" />'),
            'escaped, before the caption, beside a description of the page',
        );
    }

    public function testWithoutAnIdentifierTheDescriptionHasOneOfItsOwn(): void
    {
        $html = $this->render('<twig:Image src="/chart.png" alt="Visits" :width="8" :height="6" description="Rising" />');

        self::assertMatchesRegularExpression('#^<figure><img class="isImage" src="/chart.png" alt="Visits" width="8" height="6" loading="lazy" decoding="async" aria-describedby="(image-description-\d+)"><details class="isDescription"><summary>Description</summary><p id="\1">Rising</p></details></figure>$#', $html);
        self::assertNotSame($html, $this->render('<twig:Image src="/chart.png" alt="Visits" :width="8" :height="6" description="Rising" />'), 'two images, two identifiers');
    }

    public function testADecorativeImageShowsItsDescriptionWithoutBeingDescribed(): void
    {
        self::assertSame(
            '<figure><img class="isImage" src="/a.png" alt="" width="1" height="1" loading="lazy" decoding="async" id="a"><details class="isDescription"><summary>Description</summary><p id="a-description">Lines</p></details></figure>',
            $this->render('<twig:Image src="/a.png" alt="" :width="1" :height="1" id="a" description="Lines" />'),
        );
    }

    public function testACaptionLaidOverItsImageGivesTheFigureItsClass(): void
    {
        self::assertSame(
            '<figure class="hasOverlayCaption"><img class="isImage" src="/a.jpg" alt="A" width="8" height="6" loading="lazy" decoding="async"><figcaption>Over it<small class="isCredit">Ana Lima</small></figcaption></figure>',
            $this->render('<twig:Image src="/a.jpg" alt="A" :width="8" :height="6" caption="Over it" credit="Ana Lima" captionPosition="overlay" />'),
        );
        self::assertSame(
            '<figure class="hasOverlayCaption"><picture><source type="image/webp" srcset="/a.webp"><img class="isImage" src="/a.jpg" alt="A" width="8" height="6" loading="lazy" decoding="async"></picture><figcaption>Over it</figcaption></figure>',
            $this->render('<twig:Picture src="/a.jpg" :sources="[{type: \'image/webp\', srcset: \'/a.webp\'}]" alt="A" :width="8" :height="6" caption="Over it" captionPosition="overlay" />'),
        );
    }

    public function testACaptionStandsBelowByDefaultAndAnOverlayNeedsACaption(): void
    {
        self::assertSame(
            '<figure><img class="isImage" src="/a.jpg" alt="A" width="8" height="6" loading="lazy" decoding="async"><figcaption>Under it</figcaption></figure>',
            $this->render('<twig:Image src="/a.jpg" alt="A" :width="8" :height="6" caption="Under it" captionPosition="below" />'),
        );
        self::assertSame(
            '<figure><img class="isImage" src="/a.jpg" alt="A" width="8" height="6" loading="lazy" decoding="async"><figcaption>Under it</figcaption></figure>',
            $this->render('<twig:Image src="/a.jpg" alt="A" :width="8" :height="6" caption="Under it" captionPosition="aside" />'),
            'an unknown position stands below',
        );
        self::assertSame(
            '<figure><img class="isImage" src="/a.jpg" alt="A" width="8" height="6" loading="lazy" decoding="async" id="a" aria-describedby="a-description"><details class="isDescription"><summary>Description</summary><p id="a-description">Long</p></details></figure>',
            $this->render('<twig:Image src="/a.jpg" alt="A" :width="8" :height="6" id="a" description="Long" captionPosition="overlay" />'),
            'without a caption, nothing is laid over the image',
        );
    }
}
