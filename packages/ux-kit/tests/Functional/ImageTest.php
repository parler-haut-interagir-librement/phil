<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

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

    public function testAPictureEscapesItsSources(): void
    {
        self::assertSame(
            '<picture><source type="image/webp" srcset="/a.webp?&quot;&gt;" media="&quot;&gt;&lt;x&gt;"><img class="isImage" src="/a.png" alt="A" width="1" height="1" loading="lazy" decoding="async"></picture>',
            $this->render('<twig:Picture src="/a.png" :sources="[{type: \'image/webp\', srcset: srcset, media: media}]" alt="A" :width="1" :height="1" />', ['srcset' => '/a.webp?">', 'media' => '"><x>']),
        );
    }
}
