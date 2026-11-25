<?php

declare(strict_types=1);

namespace Phil\UxKitShadcn\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Twig\Error\RuntimeError;

/**
 * Each recipe rendered with its Tailwind classes; the props behave as in the PHIL UX kit.
 */
#[CoversNothing]
final class ShadcnKitTest extends KitTestCase
{
    public function testALogo(): void
    {
        self::assertSame(
            '<a data-slot="logo" href="/" class="inline-block rounded-md outline-none focus-visible:ring-3 focus-visible:ring-ring/50 [&amp;_img]:h-12 [&amp;_img]:w-auto"><picture data-slot="picture"><source type="image/webp" srcset="/logo-dark.webp"><img data-slot="image" class="block h-auto print:max-h-[85vh] max-w-full print:object-contain" src="/logo-dark.png" alt="Studio" loading="eager" decoding="async"></picture></a>',
            $this->render('<twig:Logo src="/logo-{theme}.png" webp="/logo-{theme}.webp" alt="Studio" href="/" theme="dark" />'),
        );
    }

    public function testAQrCodeThroughItsBundle(): void
    {
        $html = $this->render('<twig:QrCode content="https://example.com/t" class="ticket" />');

        self::assertStringStartsWith('<figure class="phil-qr-code m-0 inline-flex max-w-full flex-col items-center gap-2 ', $html);
        self::assertStringContainsString(' ticket"', $html);
        self::assertStringContainsString('data-slot="qr-code"', $html);
        self::assertStringContainsString('<a href="https://example.com/t" rel="noopener">https://example.com/t</a>', $html);
        self::assertStringContainsString('role="img"', $this->render('<twig:QrCode content="HI" alt="Hi" :caption="false" size="{{ 96 }}" level="H" />'));
    }

    public function testACarousel(): void
    {
        $html = $this->render('<twig:Carousel label="Photos" class="max-w-md"><twig:Carousel:Content class="gap-2"><twig:Carousel:Item>One</twig:Carousel:Item><twig:Carousel:Item roleDescription="photo" class="[--carousel-item:50%]">Two</twig:Carousel:Item></twig:Carousel:Content><twig:Carousel:Previous /><twig:Carousel:Next text="Next photo" /></twig:Carousel>');

        self::assertStringStartsWith('<div role="region" aria-roledescription="carousel" tabindex="0" data-slot="carousel" data-orientation="horizontal" data-align="center" data-carousel-orientation-value="horizontal" data-carousel-options-value="{&quot;align&quot;:&quot;center&quot;,&quot;loop&quot;:false}" data-carousel-autoplay-value="0" data-carousel-pause-label-value="Pause" data-carousel-play-label-value="Play" class="group/carousel mb-4 grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-1 rounded-lg outline-none focus-visible:ring-3 focus-visible:ring-ring/50 print:block max-w-md" aria-label="Photos" data-controller="carousel" data-action="keydown-&gt;carousel#handleKeydown mouseenter-&gt;carousel#hold mouseleave-&gt;carousel#release focusin-&gt;carousel#hold focusout-&gt;carousel#release">', $html);
        self::assertStringContainsString('[scroll-snap-type:inline_mandatory] group-data-[orientation=vertical]/carousel:max-h-[var(--carousel-size,24rem)]', $html);
        self::assertStringContainsString('data-slot="carousel-content" data-carousel-target="viewport" data-action="scroll->carousel#update:passive"><div class="flex gap-4 group-data-[orientation=vertical]/carousel:flex-col print:flex-wrap gap-2">', $html);
        self::assertStringContainsString('<div role="group" aria-roledescription="slide" data-slot="carousel-item" class="min-w-0 shrink-0 grow-0 basis-[var(--carousel-item,100%)] snap-start group-data-[align=center]/carousel:snap-center group-data-[align=end]/carousel:snap-end print:break-inside-avoid">One</div>', $html);
        self::assertStringContainsString('<div role="group" aria-roledescription="photo" data-slot="carousel-item" class="min-w-0 shrink-0 grow-0 basis-[var(--carousel-item,100%)] snap-start group-data-[align=center]/carousel:snap-center group-data-[align=end]/carousel:snap-end print:break-inside-avoid [--carousel-item:50%]">Two</div>', $html);
        self::assertStringContainsString('<button type="button" data-slot="carousel-previous" data-variant="outline" data-size="icon-sm" hidden data-carousel-target="previous" class="row-start-1 col-start-1 inline-flex ', $html);
        self::assertStringContainsString(' print:hidden border-border bg-background hover:bg-muted hover:text-foreground dark:border-input dark:bg-input/30 dark:hover:bg-input/50 size-7" data-action="click-&gt;carousel#scrollPrev"><span aria-hidden="true">&lsaquo;</span><span class="sr-only">Previous slide</span></button>', $html);
        self::assertStringContainsString('<button type="button" data-slot="carousel-next" data-variant="outline" data-size="icon-sm" hidden data-carousel-target="next" class="row-start-1 col-start-3 inline-flex ', $html);
        self::assertStringEndsWith('size-7" data-action="click-&gt;carousel#scrollNext"><span aria-hidden="true">&rsaquo;</span><span class="sr-only">Next photo</span></button></div>', $html);
        self::assertStringNotContainsString('carousel-toggle', $html);
    }

    public function testACarouselThatPlaysHasItsPauseButton(): void
    {
        $html = $this->render('<twig:Carousel orientation="vertical" align="end" :loop="true" :options="{duration: 20}" :autoplay="4000" label="News" roleDescription="slideshow" pauseLabel="Stop" playLabel="Go"><twig:Carousel:Content><twig:Carousel:Item>One</twig:Carousel:Item></twig:Carousel:Content></twig:Carousel>');

        self::assertStringStartsWith('<div role="region" aria-roledescription="slideshow" tabindex="0" data-slot="carousel" data-orientation="vertical" data-align="end" data-carousel-orientation-value="vertical" data-carousel-options-value="{&quot;align&quot;:&quot;end&quot;,&quot;loop&quot;:true,&quot;duration&quot;:20}" data-carousel-autoplay-value="4000" data-carousel-pause-label-value="Stop" data-carousel-play-label-value="Go" class="group/carousel ', $html);
        self::assertStringEndsWith('<button type="button" class="col-span-full inline-flex h-7 shrink-0 cursor-pointer items-center justify-center gap-1 justify-self-center rounded-[min(var(--radius-md),12px)] border border-transparent px-2.5 text-[0.8rem] font-medium whitespace-nowrap transition-all outline-none select-none hover:bg-muted hover:text-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:hover:bg-muted/50 print:hidden" hidden data-slot="carousel-toggle" data-carousel-target="toggle" data-action="click->carousel#toggleAutoplay">Stop</button></div>', $html);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function carouselButtons(): iterable
    {
        yield 'default' => ['variant="default" size="default"', 'border-transparent bg-primary text-primary-foreground hover:bg-primary/80', 'h-8 gap-1.5 px-2.5'];
        yield 'secondary' => ['variant="secondary" size="xs"', 'border-transparent bg-secondary text-secondary-foreground hover:bg-secondary/80', 'h-6 gap-1 px-2 text-xs'];
        yield 'destructive' => ['variant="destructive" size="sm"', 'border-transparent bg-destructive/10 text-destructive hover:bg-destructive/20 dark:bg-destructive/20 dark:hover:bg-destructive/30', 'h-7 gap-1 px-2.5 text-[0.8rem]'];
        yield 'ghost' => ['variant="ghost" size="lg"', 'border-transparent hover:bg-muted hover:text-foreground dark:hover:bg-muted/50', 'h-9 gap-1.5 px-2.5'];
        yield 'link' => ['variant="link" size="icon"', 'border-transparent text-primary underline-offset-4 hover:underline', 'size-8'];
        yield 'outline' => ['variant="outline" size="icon-xs"', 'border-border bg-background hover:bg-muted hover:text-foreground dark:border-input dark:bg-input/30 dark:hover:bg-input/50', 'size-6'];
        yield 'icon-lg' => ['size="icon-lg"', 'border-border bg-background hover:bg-muted hover:text-foreground dark:border-input dark:bg-input/30 dark:hover:bg-input/50', 'size-9'];
        yield 'unknown' => ['variant="loud" size="huge"', 'border-border bg-background hover:bg-muted hover:text-foreground dark:border-input dark:bg-input/30 dark:hover:bg-input/50', 'size-7'];
    }

    #[DataProvider('carouselButtons')]
    public function testTheButtonsOfACarouselTakeTheVariantsAndSizesOfTheKit(string $props, string $variant, string $size): void
    {
        foreach (['Previous', 'Next'] as $button) {
            $html = $this->render(\sprintf('<twig:Carousel:%s %s />', $button, $props));

            self::assertStringContainsString(' print:hidden '.$variant.' '.$size.'" data-action=', $html, $button);
        }
    }

    public function testALogoNeedsItsTextAlternative(): void
    {
        $this->expectException(RuntimeError::class);

        $this->render('<twig:Logo src="/logo.svg" />');
    }

    public function testAnAnimatedHeadline(): void
    {
        $html = $this->render('<twig:AnimatedHeadline text="We make" :words="[\'sites\', \'apps\']" :level="2" />');

        self::assertStringStartsWith('<h2 data-slot="animated-headline" data-animated-headline-pause-value="2500" data-animated-headline-duration-value="600" data-animated-headline-loop-value="false" class="scroll-m-20 ', $html);
        self::assertStringContainsString('data-controller="animated-headline"', $html);
        self::assertStringContainsString('<span class="sr-only">We make sites, apps</span><span aria-hidden="true">We make <span class="inline-grid ', $html);
        self::assertStringContainsString('aria-live="off" data-animated-headline-target="reveal">', $html);
        self::assertStringContainsString('<span class="isCurrent invisible col-start-1 row-start-1 justify-self-start whitespace-nowrap [&.isCurrent]:visible" data-animated-headline-target="word">sites</span>', $html);
        self::assertStringContainsString('<span class="invisible col-start-1 row-start-1 justify-self-start whitespace-nowrap [&.isCurrent]:visible" data-animated-headline-target="word">apps</span>', $html);
        self::assertStringEndsWith('</span></span></h2>', $html);
        self::assertStringNotContainsString('<button', $html);
    }

    public function testACyclingHeadlineHasItsPauseButton(): void
    {
        $html = $this->render('<twig:AnimatedHeadline :words="[\'sites\', \'apps\']" :loop="true" pauseLabel="Pause" playLabel="Play" />');

        self::assertMatchesRegularExpression('~data-animated-headline-loop-value="true" data-animated-headline-pause-label-value="Pause" data-animated-headline-play-label-value="Play" aria-labelledby="(animated-headline-\\d+)" class="scroll-m-20 .*<span id="\\1" class="sr-only">sites, apps</span>~', $html);
        self::assertMatchesRegularExpression('~</span></span><button type="button" data-slot="animated-headline-toggle" class="ms-3 [^"]*" hidden data-animated-headline-target="toggle" data-action="animated-headline#toggle">Pause</button></h1>$~', $html);
    }

    public function testAnIllustrationChosenBySeed(): void
    {
        self::assertSame(
            '<figure data-slot="illustration" class="m-0"><img class="h-auto w-full" src="/b.png" alt="B" loading="lazy" decoding="async"><figcaption class="mt-2 text-sm text-muted-foreground">Soon</figcaption></figure>',
            $this->render('<twig:Illustration :candidates="[\'/a.png\', {src: \'/b.png\', alt: \'B\'}]" alt="" :seed="3" caption="Soon" />'),
        );
        self::assertSame('', $this->render('<twig:Illustration :candidates="[]" alt="" />'));
    }

    public function testAPictureWithItsSourcesBeforeItsImage(): void
    {
        self::assertSame(
            '<picture data-slot="picture"><source type="image/avif" srcset="/a-1.avif 1x, /a-2.avif 2x"><source type="image/webp" srcset="/a.webp"><img data-slot="image" class="block h-auto print:max-h-[85vh] max-w-full print:object-contain rounded-md" src="/a.png" alt="The square" width="640" height="480" loading="eager" fetchpriority="high" id="hero"></picture>',
            $this->render('<twig:Picture src="/a.png" :sources="[{type: \'image/avif\', srcset: {1: \'/a-1.avif\', 2: \'/a-2.avif\'}}, {type: \'image/webp\', srcset: \'/a.webp\'}]" alt="The square" :width="640" :height="480" :priority="true" class="rounded-md" id="hero" onerror="alert(1)" />'),
        );
    }

    public function testAnImageByWidthDecorativeOrRefused(): void
    {
        self::assertSame(
            '<img data-slot="image" class="block h-auto print:max-h-[85vh] max-w-full print:object-contain" src="/a.jpg" srcset="/a-480.jpg 480w, /a-960.jpg 960w" sizes="auto, 50vw" alt="" width="960" height="640" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.jpg" :srcset="{480: \'/a-480.jpg\', 960: \'/a-960.jpg\'}" sizes="50vw" alt="" :width="960" :height="640" title="T" aria-label="L" />'),
        );
        self::assertSame('', $this->render('<twig:Image src="javascript:alert(1)" alt="A" :width="1" :height="1" />'));
        self::assertSame(
            '<img data-slot="image" class="block h-auto print:max-h-[85vh] max-w-full print:object-contain" src="/a.png" alt="A" width="1" height="1" loading="lazy" decoding="async">',
            $this->render('<twig:Picture src="/a.png" :sources="[{type: \'image/avif\', srcset: \'data:text/html,x\'}]" alt="A" :width="1" :height="1" />'),
        );
    }

    public function testACaptionedDecorativeImage(): void
    {
        self::assertSame(
            '<figure data-slot="image-figure" class="m-0 print:break-inside-avoid"><img data-slot="image" class="block h-auto print:max-h-[85vh] max-w-full print:object-contain" src="/a.png" alt="" width="1" height="1" loading="lazy" decoding="async"><figcaption class="mt-2 text-sm text-muted-foreground print:break-before-avoid">&lt;b&gt;Drawn&lt;/b&gt;</figcaption></figure>',
            $this->render('<twig:Image src="/a.png" alt="" :width="1" :height="1" :caption="caption" />', ['caption' => '<b>Drawn</b>']),
        );
        self::assertSame(
            '<figure data-slot="image-figure" class="m-0 print:break-inside-avoid"><picture data-slot="picture"><source type="image/webp" srcset="/a.webp"><img data-slot="image" class="block h-auto print:max-h-[85vh] max-w-full print:object-contain" src="/a.png" alt="A" width="1" height="1" loading="lazy" decoding="async"></picture><figcaption class="mt-2 text-sm text-muted-foreground print:break-before-avoid">C</figcaption></figure>',
            $this->render('<twig:Picture src="/a.png" :sources="[{type: \'image/webp\', srcset: \'/a.webp\'}]" alt="A" :width="1" :height="1" caption="C" />'),
        );
    }

    public function testALogoByDensityThroughThePicture(): void
    {
        self::assertSame(
            '<span data-slot="logo" class="inline-block rounded-md outline-none focus-visible:ring-3 focus-visible:ring-ring/50 [&amp;_img]:h-12 [&amp;_img]:w-auto"><picture data-slot="picture"><source type="image/avif" srcset="/1x/l.avif 1x, /2x/l.avif 2x"><img data-slot="image" class="block h-auto print:max-h-[85vh] max-w-full print:object-contain" src="/1x/l.png" srcset="/1x/l.png 1x, /2x/l.png 2x" alt="S" width="160" height="40" loading="eager" decoding="async"></picture></span>',
            $this->render('<twig:Logo src="/1x/l.png" :srcset="{1: \'/1x/l.png\', 2: \'/2x/l.png\'}" :avif="{1: \'/1x/l.avif\', 2: \'/2x/l.avif\'}" alt="S" :width="160" :height="40" />'),
        );
    }

    public function testAnImageLaidOutWithItsRatioFitAndCrop(): void
    {
        self::assertSame(
            '<img data-slot="image" class="block h-auto print:max-h-[85vh] max-w-full w-full aspect-video object-cover object-top" src="/a.jpg" srcset="/a-960.jpg 960w" sizes="auto, 100vw" alt="A" width="1920" height="1080" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.jpg" :srcset="{960: \'/a-960.jpg\'}" layout="full-width" aspect="16/9" position="top" alt="A" :width="1920" :height="1080" />'),
        );
        self::assertSame(
            '<img data-slot="image" class="block h-auto print:max-h-[85vh] max-w-none aspect-(--image-ratio) object-contain" src="/a.jpg" alt="A" width="96" height="96" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.jpg" layout="fixed" aspect="custom" fit="contain" alt="A" :width="96" :height="96" />'),
        );
    }

    public function testAPictureWithArtDirection(): void
    {
        self::assertSame(
            '<picture data-slot="picture"><source type="image/avif" srcset="/n.avif" media="(max-width: 40rem)" width="400" height="400"><source type="image/webp" srcset="/n.webp" media="(max-width: 40rem)" width="400" height="400"><source type="image/avif" srcset="/w.avif"><img data-slot="image" class="block h-auto print:max-h-[85vh] max-w-full print:object-contain" src="/w.jpg" alt="A" width="1600" height="900" loading="lazy" decoding="async"></picture>',
            $this->render('<twig:Picture src="/w.jpg" :sources="[{type: \'image/avif\', srcset: \'/w.avif\'}, {type: \'image/webp\', srcset: \'/n.webp\', media: \'(max-width: 40rem)\', width: 400, height: 400}, {type: \'image/avif\', srcset: \'/n.avif\', media: \'(max-width: 40rem)\', width: 400, height: 400}]" alt="A" :width="1600" :height="900" />'),
        );
    }

    public function testAPictureWithItsDarkAndStillVariantsCreditLicenseAndCreation(): void
    {
        self::assertSame(
            '<figure data-slot="image-figure" class="m-0 print:break-inside-avoid"><picture data-slot="picture"><source srcset="/still.png" media="(prefers-reduced-motion: reduce)"><source srcset="/dark.gif" media="(prefers-color-scheme: dark)"><img data-slot="image" class="block h-auto print:max-h-[85vh] max-w-full print:object-contain" src="/a.gif" alt="A" width="1" height="1" loading="lazy" decoding="async"></picture>'
            .'<figcaption class="mt-2 text-sm text-muted-foreground print:break-before-avoid">Dusk<small data-slot="image-credit" class="block">Ana Lima</small><small data-slot="image-license" class="block"><a href="/licence" rel="license" class="underline underline-offset-4">CC0</a></small><small data-slot="image-creation" class="block" data-digital-source-type="trainedAlgorithmicMedia">Created using Generative AI</small></figcaption></figure>',
            $this->render('<twig:Picture src="/a.gif" dark="/dark.gif" still="/still.png" alt="A" :width="1" :height="1" caption="Dusk" credit="Ana Lima" license="CC0" licenseHref="/licence" creation="trainedAlgorithmicMedia" />'),
        );
    }

    public function testAnImageOfAnotherHostIsRefusedAndOneOfAListedHostKeepsTheReferrer(): void
    {
        self::assertSame('', $this->render('<twig:Image src="https://tracker.example.com/p.gif" alt="" :width="1" :height="1" />'));
        self::assertSame(
            '<img data-slot="image" class="block h-auto print:max-h-[85vh] max-w-full print:object-contain" src="https://images.example.com/a.jpg" alt="A" width="1" height="1" loading="lazy" decoding="async" referrerpolicy="no-referrer">',
            $this->render('<twig:Image src="https://images.example.com/a.jpg" alt="A" :width="1" :height="1" />'),
        );
    }

    public function testAStructuredImageAndPictureAreDescribedInJsonLdBeforeThem(): void
    {
        self::assertStringStartsWith(
            '<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"ImageObject","contentUrl":"https://images.example.com/a.jpg","caption":"A bridge","width":800,"height":600,"creditText":"Photo: Ana Lima"}]}</script><figure',
            $this->render('<twig:Image src="https://images.example.com/a.jpg" alt="A" :width="800" :height="600" caption="A bridge" credit="Photo: Ana Lima" :structured="true" />'),
        );
        self::assertStringStartsWith(
            '<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"ImageObject","contentUrl":"https://images.example.com/b.jpg","width":1,"height":1}]}</script><picture data-slot="picture">',
            $this->render('<twig:Picture src="https://images.example.com/b.jpg" :sources="[{type: \'image/avif\', srcset: \'https://images.example.com/b.avif\'}]" alt="B" :width="1" :height="1" :structured="true" />'),
        );
        self::assertStringNotContainsString('ld+json', $this->render('<twig:Image src="https://images.example.com/a.jpg" alt="A" :width="1" :height="1" />'));
    }

    public function testALogoForADarkSystem(): void
    {
        self::assertStringContainsString(
            '<picture data-slot="picture"><source srcset="/logo-dark.svg" media="(prefers-color-scheme: dark)"><img',
            $this->render('<twig:Logo src="/logo.svg" dark="/logo-dark.svg" alt="Studio" />'),
        );
    }

    public function testAGalleryOnAContainerQuery(): void
    {
        self::assertSame(
            '<div data-slot="gallery" class="@container"><ul role="list" aria-label="Photos" class="m-0 grid list-none grid-cols-[repeat(auto-fill,minmax(min(100%,12rem),1fr))] gap-2 p-0 @3xl:gap-4 [&_figure]:m-0 [&_img]:w-full"><li class="min-w-0 print:break-inside-avoid"><img data-slot="image" class="block h-auto print:max-h-[85vh] max-w-full aspect-square object-cover" src="/a.jpg" alt="A" width="4" height="3" loading="lazy" decoding="async"></li></ul></div>',
            $this->render('<twig:Gallery label="Photos" :images="[{src: \'/a.jpg\', alt: \'A\', width: 4, height: 3}]" aspect="1/1" />'),
        );
    }

    public function testALightboxOnTheNativeDialog(): void
    {
        $html = $this->render('<twig:Lightbox id="bridge" src="/b.jpg" alt="A bridge" :width="4" :height="3" openLabel="Enlarge" />');

        self::assertStringStartsWith('<div data-slot="lightbox" class="block print:break-inside-avoid" data-controller="lightbox"><button type="button" data-slot="lightbox-trigger" class="block cursor-zoom-in ', $html);
        self::assertStringContainsString('command="show-modal" commandfor="lightbox-bridge" aria-haspopup="dialog" data-action="click->lightbox#open">', $html);
        self::assertStringContainsString('<span class="sr-only">Enlarge</span>', $html);
        self::assertStringContainsString('<dialog id="lightbox-bridge" data-slot="lightbox-dialog" class="m-auto ', $html);
        self::assertStringContainsString('closedby="any" aria-label="A bridge" data-lightbox-target="dialog" data-action="click->lightbox#closeOnClickOutside">', $html);
        self::assertStringContainsString('command="close" commandfor="lightbox-bridge" data-action="click->lightbox#close"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button></dialog></div>', $html);
    }

    public function testADeferredImageWithAPlaceholderAndAPreviewInTailwind(): void
    {
        self::assertSame(
            '<img data-slot="image" class="block h-auto print:max-h-[85vh] max-w-full bg-blue-100 dark:bg-blue-950 bg-cover bg-center bg-no-repeat print:object-contain" src="/a.jpg" alt="A" width="8" height="6" loading="lazy" decoding="async" fetchpriority="low" data-controller="image-preview" data-image-preview-src-value="data:image/png;base64,iVBORw0KGgo=">',
            $this->render('<twig:Image src="/a.jpg" alt="A" :width="8" :height="6" placeholder="blue" preview="data:image/png;base64,iVBORw0KGgo=" :deferred="true" />'),
        );
        self::assertSame(
            '<img data-slot="image" class="block h-auto print:max-h-[85vh] max-w-full object-contain bg-muted bg-contain bg-center bg-no-repeat" src="/a.jpg" alt="A" width="8" height="6" loading="lazy" decoding="async" data-controller="image-preview" data-image-preview-src-value="data:image/png;base64,iVBORw0KGgo=">',
            $this->render('<twig:Image src="/a.jpg" alt="A" :width="8" :height="6" fit="contain" placeholder="neutral" preview="data:image/png;base64,iVBORw0KGgo=" />'),
        );
        self::assertStringContainsString('class="block h-auto print:max-h-[85vh] max-w-full bg-(--image-placeholder) print:object-contain"', $this->render('<twig:Image src="/a.jpg" alt="A" :width="8" :height="6" placeholder="custom" />'));
    }

    public function testAForcedColoursVariantAndALongDescription(): void
    {
        self::assertSame(
            '<figure data-slot="image-figure" class="m-0 print:break-inside-avoid"><picture data-slot="picture"><source srcset="/forced.png" media="(forced-colors: active)"><img data-slot="image" class="block h-auto print:max-h-[85vh] max-w-full print:object-contain" src="/a.png" alt="A chart" width="8" height="6" loading="lazy" decoding="async" id="chart" aria-describedby="chart-description"></picture>'
            .'<details data-slot="image-description" class="mt-2 text-sm"><summary class="cursor-pointer font-medium">Description</summary><p id="chart-description" class="mt-1 text-muted-foreground">Rising</p></details></figure>',
            $this->render('<twig:Picture src="/a.png" forced="/forced.png" alt="A chart" :width="8" :height="6" id="chart" description="Rising" />'),
        );
        self::assertStringContainsString(
            '<source srcset="/logo-forced.svg" media="(forced-colors: active)">',
            $this->render('<twig:Logo src="/logo.svg" forced="/logo-forced.svg" alt="Studio" />'),
        );
    }

    public function testADeferredGalleryInTailwind(): void
    {
        self::assertStringContainsString(
            '<li data-width="4" data-height="3" class="-m-[3px] box-content min-w-0 p-[3px] [content-visibility:auto] [contain-intrinsic-size:auto_12rem] print:[content-visibility:visible] print:break-inside-avoid supports-[aspect-ratio:attr(data-width_type(&lt;number&gt;),1)/1]:[aspect-ratio:attr(data-width_type(&lt;number&gt;),1)/attr(data-height_type(&lt;number&gt;),1)] supports-[aspect-ratio:attr(data-width_type(&lt;number&gt;),1)/1]:[contain-intrinsic-size:auto_none]"><img data-slot="image" class="block h-auto print:max-h-[85vh] max-w-full print:object-contain" src="/a.jpg" alt="A" width="4" height="3" loading="lazy" decoding="async" fetchpriority="low"></li>',
            $this->render('<twig:Gallery label="Photos" :images="[{src: \'/a.jpg\', alt: \'A\', width: 4, height: 3}]" :deferred="true" />'),
        );
    }

    public function testALightboxDescribedByItsDescription(): void
    {
        $html = $this->render('<twig:Lightbox id="bridge" src="/b.jpg" alt="A bridge" :width="4" :height="3" description="Stone arches" />');

        self::assertStringContainsString('aria-haspopup="dialog" aria-describedby="lightbox-bridge-description" data-action="click->lightbox#open">', $html);
        self::assertStringContainsString('</button><details data-slot="image-description" class="mt-2 text-sm"><summary class="cursor-pointer font-medium">Description</summary><p id="lightbox-bridge-description" class="mt-1 text-muted-foreground">Stone arches</p></details></figure>', $html);
    }

    public function testAnImageNeedsItsTextAlternative(): void
    {
        $this->expectException(RuntimeError::class);

        $this->render('<twig:Image src="/a.png" :width="1" :height="1" />');
    }

    public function testAnAutosaveStatus(): void
    {
        $html = $this->render('<twig:AutosaveStatus dismissLabel="Garder" />');
        $c = 'phil--forms-autosave--autosave';

        self::assertStringStartsWith('<div data-slot="autosave-status" class="grid gap-2"><p class="min-h-5 text-sm text-muted-foreground" role="status" aria-live="polite" data-'.$c.'-target="status"></p>', $html);
        self::assertStringContainsString('role="group" hidden data-'.$c.'-target="notice">', $html);
        self::assertMatchesRegularExpression('~<button type="button" class="[^"]*" hidden data-'.$c.'-target="useServer" data-action="'.$c.'#useServer">Use the newer draft</button>~', $html);
        self::assertMatchesRegularExpression('~<button type="button" class="[^"]*" data-'.$c.'-target="dismiss" data-action="'.$c.'#dismiss">Garder</button></div></div>$~', $html);
    }

    public function testAReactionButton(): void
    {
        $html = $this->render('<twig:ReactionButton label="Like" emoji="👍" :count="3" countLabel="3 reactions" :pressed="true" name="withdraw" />');

        self::assertMatchesRegularExpression('~^<button type="submit" aria-pressed="true" data-slot="reaction-button" class="[^"]*min-h-11 min-w-11[^"]*" name="withdraw">~', $html);
        self::assertStringContainsString('<span aria-hidden="true">👍</span><span class="sr-only">Like</span><span aria-hidden="true">3</span><span class="sr-only">3 reactions</span></button>', $html);
        self::assertStringStartsWith('<span data-slot="reaction-button" class="', $this->render('<twig:ReactionButton label="Like" emoji="👍" :interactive="false" />'));
    }

    public function testAReactionPicker(): void
    {
        $html = $this->render('<twig:ReactionPicker label="More" :groups="groups" />', ['groups' => [['label' => 'Emojis', 'options' => [['label' => 'Spark', 'emoji' => '✨', 'image' => null, 'imageAlt' => null, 'attributes' => ['value' => 'spark']], ['label' => 'Gnome', 'emoji' => null, 'image' => '/g.webp', 'imageAlt' => 'A gnome', 'attributes' => []]]]]]);
        $c = 'phil--reactions-bundle--picker';

        self::assertStringStartsWith('<details data-slot="reaction-picker" class="relative" data-controller="'.$c.'"', $html);
        self::assertMatchesRegularExpression('~<input class="[^"]*" type="search" hidden~', $html);
        self::assertStringContainsString('data-search="Spark" value="spark">', $html);
        self::assertStringContainsString('alt="A gnome"', $html);
    }

    public function testAReactionPickerWritesOnlyTheAttributesOfAFormButton(): void
    {
        $html = $this->render('<twig:ReactionPicker label="More" :groups="groups" />', ['groups' => [['label' => 'Emojis', 'options' => [['label' => 'Spark', 'emoji' => '✨', 'image' => null, 'imageAlt' => null, 'attributes' => ['value' => 'spark', 'data-live-code-param' => 'spark', 'onclick=alert(1) x' => 'y', 'formaction' => 'javascript:alert(2)']]]]]]);

        self::assertStringContainsString('data-search="Spark" value="spark" data-live-code-param="spark">', $html);
        self::assertStringNotContainsString('alert', $html);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function ownRecipes(): iterable
    {
        yield 'copy button' => ['<twig:CopyButton text="x" />', 'copy-button'];
        yield 'celebration' => ['<twig:Celebration message="Won" :autoplay="true"><button type="button">Go</button></twig:Celebration>', 'celebration'];
        yield 'animated button' => ['<twig:AnimatedButton effect="arrow">Go</twig:AnimatedButton>', 'animated-button'];
        yield 'stepper' => ['<twig:Stepper><twig:Stepper:Item state="done">A</twig:Stepper:Item><twig:Stepper:Item state="current">B</twig:Stepper:Item></twig:Stepper>', 'stepper'];
        yield 'activity calendar' => ['<twig:ActivityCalendar label="Y" :offset="1" :days="[{level: 2, label: \'Two\'}]" lessLabel="L" moreLabel="M" />', 'activity-calendar'];
        yield 'tree' => ['<twig:Tree label="F"><twig:Tree:Item label="A" :branch="true"><twig:Tree:Item label="B" href="/b" /></twig:Tree:Item></twig:Tree>', 'tree'];
        yield 'rating' => ['<twig:Rating label="N" name="n" :value="2" clearLabel="None" />', 'rating'];
        yield 'rating read-only' => ['<twig:Rating label="N" :value="2.5" />', 'rating'];
        yield 'number input' => ['<twig:NumberInput label="G" :value="2" />', 'number-input'];
        yield 'file input' => ['<twig:FileInput name="f" label="Add" hint="PNG" />', 'file-input'];
        yield 'tag' => ['<twig:Tag label="A" :removable="true" />', 'tag'];
        yield 'relative time' => ['<twig:RelativeTime datetime="2026-01-01" />', 'relative-time'];
        yield 'progress ring' => ['<twig:ProgressRing label="U" :value="40" />', 'progress-ring'];
        yield 'image' => ['<twig:Image src="/a.png" alt="A" :width="1" :height="1" />', 'image'];
        yield 'picture' => ['<twig:Picture src="/a.png" :sources="[{type: \'image/webp\', srcset: \'/a.webp\'}]" alt="A" :width="1" :height="1" />', 'picture'];
        yield 'sparkline' => ['<twig:Sparkline label="U" :values="[1, 2]" :area="true" />', 'sparkline'];
        yield 'before-after' => ['<twig:BeforeAfter label="C"><twig:block name="before">B</twig:block><twig:block name="after">A</twig:block></twig:BeforeAfter>', 'before-after'];
        yield 'colour picker' => ['<twig:ColorPicker label="A" :swatches="[\'#112233\']" />', 'color-picker'];
        yield 'carousel' => ['<twig:Carousel :autoplay="10"><twig:Carousel:Content><twig:Carousel:Item>A</twig:Carousel:Item></twig:Carousel:Content><twig:Carousel:Previous /><twig:Carousel:Next /></twig:Carousel>', 'carousel'];
        yield 'bar chart' => ['<twig:Chart label="L" :labels="[\'A\', \'B\']" :series="[{label: \'X\', values: [1, 2]}, {label: \'Y\', values: [2, 1]}]" tableLabel="T" />', 'chart'];
        yield 'line chart' => ['<twig:Chart type="line" label="L" :labels="[\'A\', \'B\']" :series="[{label: \'X\', values: [1, null]}, {label: \'Y\', values: [2, 1]}]" />', 'chart'];
        yield 'Chart.js chart' => ['<twig:ChartJs :chart="{data: {labels: [\'A\'], datasets: [{label: \'X\', data: [1]}]}}" label="L" tableLabel="T" />', 'chart'];
        yield 'login form' => ['<twig:LoginForm action="/l" title="T" usernameLabel="U" passwordLabel="P" submitLabel="S" error="E" rememberLabel="R" forgotHref="/f" forgotLabel="F" signupHref="/s" signupLabel="J" csrfToken="t" />', 'login-form'];
        yield 'signup form' => ['<twig:SignupForm action="/s" title="T" nameLabel="N" emailLabel="E" passwordLabel="P" confirmLabel="C" passwordHint="H" submitLabel="S" :errors="{email: [\'Taken\']}" loginHref="/l" loginLabel="L" />', 'signup-form'];
    }

    #[DataProvider('ownRecipes')]
    public function testAnOwnRecipeIsWrittenInTailwindAlone(string $template, string $slot): void
    {
        $html = $this->render($template);

        self::assertStringContainsString('data-slot="'.$slot.'"', $html);
        self::assertDoesNotMatchRegularExpression('/class="[^"]*\b(?:is|has)[A-Z]/', $html);
        self::assertDoesNotMatchRegularExpression('/-class="[^"]*\b(?:is|has)[A-Z]/', $html);
    }

    public function testAComingSoonPage(): void
    {
        $html = $this->render(<<<'TWIG'
            <twig:ComingSoon
                logoSrc="/logo.svg"
                logoAlt="Studio"
                eyebrow="Coming soon"
                words="Studio"
                :paragraphs="['One.']"
                :illustrations="['/main.png']"
                illustrationAlt=""
            ><p>More.</p></twig:ComingSoon>
            TWIG);

        self::assertStringStartsWith('<div data-slot="coming-soon" class="grid min-h-svh grid-cols-1 bg-background text-foreground md:grid-cols-2"><div class="flex flex-col gap-4 p-6 md:p-10"><header><span data-slot="logo"', $html);
        self::assertStringContainsString('<main class="grid flex-1 place-items-center"><div class="max-w-[36ch]"><hgroup class="mb-4"><p class="mb-1 text-sm font-medium text-muted-foreground">Coming soon</p><h1 data-slot="animated-headline"', $html);
        self::assertStringContainsString('</hgroup><p class="mt-4 leading-7">One.</p><p>More.</p></div></main></div><figure data-slot="illustration" class="m-0 relative min-h-[40svh] ', $html);
        self::assertStringContainsString('<img class="h-auto w-full" src="/main.png" alt="" loading="lazy" decoding="async"></figure></div>', $html);
    }

    public function testAComingSoonPageCanPreloadItsLogoAndLoadItsIllustrationAtOnce(): void
    {
        $html = $this->render('<twig:ComingSoon logoSrc="/logo.png" logoAlt="Studio" :logoWidth="160" :logoHeight="40" :logoPriority="true" logoFit="contain" eyebrow="Soon" words="Studio" :illustrations="[\'/main.png\']" illustrationLoading="eager" />');

        self::assertStringContainsString('<img data-slot="image" class="block h-auto print:max-h-[85vh] max-w-full object-contain" src="/logo.png" alt="Studio" width="160" height="40" loading="eager" fetchpriority="high">', $html);
        self::assertStringContainsString('<img class="h-auto w-full" src="/main.png" alt="" loading="eager" decoding="async">', $html);
    }

    /**
     * The series take the chart tokens of the Shadcn kit, and from the second on, each line a dash of its own.
     */
    public function testAChartColoursItsSeriesWithTheChartTokens(): void
    {
        $html = $this->render('<twig:Chart type="line" label="Two" :labels="[\'A\', \'B\']" :series="[{label: \'X\', values: [1, 3]}, {label: \'Y\', values: [2, 1]}]" :width="200" :height="100" tableLabel="Values" />');

        self::assertStringStartsWith('<figure data-slot="chart" data-type="line" class="m-0 grid max-w-full gap-2"><figcaption class="font-medium">Two</figcaption><svg class="h-auto w-full max-w-full" viewBox="0 0 200 100"', $html);
        self::assertStringContainsString('<path class="fill-none stroke-2 [stroke-linecap:round] [stroke-linejoin:round] stroke-chart-1" data-series="1" d="M84 50.67 l0 0 L156 8"></path>', $html);
        self::assertStringContainsString('<path class="fill-none stroke-2 [stroke-linecap:round] [stroke-linejoin:round] stroke-chart-2 [stroke-dasharray:8_4]" data-series="2" d="M84 29.33 l0 0 L156 50.67"></path>', $html);
        self::assertStringContainsString('<li class="inline-flex items-center gap-1" data-series="2"><span class="h-1 w-4 rounded-full bg-chart-2"></span>Y</li>', $html);
        self::assertStringContainsString('<details class="text-sm"><summary class="cursor-pointer">Values</summary>', $html);
        self::assertStringContainsString('<g class="fill-chart-1" data-series="1"><rect x="52.4" y="8" width="35.2" height="64"></rect></g>', $this->render('<twig:Chart label="Bars" :labels="[\'A\']" :series="[{label: \'X\', values: [2]}]" :width="100" :height="100" />'));
        self::assertStringContainsString('<div class="sr-only"><table', $this->render('<twig:ChartJs :chart="{data: {labels: [\'Jan\'], datasets: [{label: \'V\', data: [1]}]}}" label="Visits" />'));
    }

    public function testTheFormBlocksKeepTheirFieldsAndErrors(): void
    {
        $login = $this->render('<twig:LoginForm action="/login" title="Sign in" usernameLabel="Name" passwordLabel="Password" submitLabel="Go" error="Bad." />');

        self::assertStringContainsString('<p id="login-error" role="alert" class="rounded-lg border border-destructive/50 px-4 py-3 text-sm text-destructive">Bad.</p>', $login);
        self::assertStringContainsString('name="_username" value="" autocomplete="username" required autofocus aria-invalid="true" aria-describedby="login-error">', $login);

        $signup = $this->render('<twig:SignupForm action="/s" title="Join" emailLabel="Email" passwordLabel="Password" submitLabel="Go" :errors="{email: [\'Taken\']}" />');

        self::assertStringContainsString('<div class="grid gap-2" data-invalid="true"><label class="text-sm font-medium" for="signup-email">Email</label>', $signup);
        self::assertStringContainsString('<ul id="signup-email-error" class="m-0 list-none p-0 text-sm text-destructive"><li>Taken</li></ul></div><div class="grid gap-2"><label', $signup);
    }

    public function testACaptionOverItsImageStandsInTheSameCellOnAVeil(): void
    {
        self::assertSame(
            '<figure data-slot="image-figure" class="m-0 grid w-fit max-w-full has-[.w-full]:w-auto [&>:first-child]:[grid-area:1/1] print:break-inside-avoid"><img data-slot="image" class="block h-auto print:max-h-[85vh] max-w-full print:object-contain" src="/a.jpg" alt="A" width="8" height="6" loading="lazy" decoding="async"><figcaption class="z-[1] m-0 self-end bg-black/70 px-3 py-2 text-sm text-white [grid-area:1/1] [&_a]:text-inherit print:break-before-avoid print:[print-color-adjust:exact]">Over it</figcaption></figure>',
            $this->render('<twig:Image src="/a.jpg" alt="A" :width="8" :height="6" caption="Over it" captionPosition="overlay" />'),
        );
        self::assertStringStartsWith('<figure data-slot="image-figure" class="m-0 print:break-inside-avoid"><picture', $this->render('<twig:Picture src="/a.jpg" :sources="[{type: \'image/webp\', srcset: \'/a.webp\'}]" alt="A" :width="8" :height="6" caption="Under it" />'));
    }

    public function testAGalleryLaidOutAsACarousel(): void
    {
        $html = $this->render('<twig:Gallery label="The strip" layout="carousel" previousLabel="Back" nextLabel="On" captionPosition="overlay" :images="[{src: \'/a.jpg\', alt: \'The stage\', width: 8, height: 6, caption: \'Opening\'}, {src: \'/b.jpg\', alt: \'\', width: 8, height: 6}]" />');

        self::assertStringStartsWith('<div data-slot="gallery" class="@container relative"><ul role="list" aria-label="The strip" tabindex="0" data-previous="Back" data-next="On" class="m-0 grid list-none auto-cols-[minmax(min(85%,12rem),1fr)] grid-flow-col ', $html);
        self::assertStringContainsString("[&::scroll-button(inline-end)]:content-['›'_/_attr(data-next)]", $html);
        self::assertStringContainsString('<li data-marker="The stage" class="min-w-0 print:break-inside-avoid snap-start ', $html);
        self::assertStringContainsString('<li data-marker="2" class="min-w-0 print:break-inside-avoid snap-start ', $html);
        self::assertStringContainsString('<figure data-slot="image-figure" class="m-0 grid w-fit', $html);
    }

    public function testAnImageWithItsPoints(): void
    {
        $html = $this->render('<twig:Hotspots id="plan" src="/plan.png" alt="Plan" :width="8" :height="4" label="Rooms" caption="The hall" :spots="[{x: 72.5, y: 64, label: \'Bar\', href: \'#bar\'}, {x: 1, y: 1, label: \'Bad\', href: \'javascript:x\'}]" />');

        self::assertStringStartsWith('<figure data-slot="image-figure" class="m-0 print:break-inside-avoid"><div data-slot="hotspots" id="plan" class="relative w-fit max-w-full print:break-inside-avoid"><img data-slot="image" ', $html);
        self::assertStringContainsString('<ul role="list" data-slot="hotspots-list" class="m-0 list-none p-0 [direction:ltr] ', $html);
        self::assertStringContainsString(' start-[75%] top-[65%]" data-x="72.5" data-y="64"><a href="#bar" class="inline-flex ', $html);
        self::assertSame(1, substr_count($html, '<li '));
        self::assertStringEndsWith('<figcaption class="mt-2 text-sm text-muted-foreground print:break-before-avoid">The hall</figcaption></figure>', $html);
    }

    public function testAHeroItsImageAndItsContent(): void
    {
        self::assertSame(
            '<div data-slot="hero" id="top" class="relative grid overflow-hidden p-6 print:break-inside-avoid min-h-[70svh] content-center"><div data-slot="hero-media" class="absolute inset-0 [&_img]:size-full [&_img]:max-w-none"><img data-slot="image" class="block h-auto print:max-h-[85vh] max-w-full w-full object-cover" src="/h.jpg" alt="" width="16" height="9" loading="eager" fetchpriority="high"></div><div data-slot="hero-content" class="relative max-w-[40rem] rounded-md bg-black/70 p-6 text-white empty:hidden [&_a]:text-inherit print:[print-color-adjust:exact]"><h1>Hi</h1></div></div>',
            $this->render('<twig:Hero id="top" src="/h.jpg" alt="" :width="16" :height="9" size="large" align="center"><h1>Hi</h1></twig:Hero>'),
        );
        self::assertStringStartsWith('<div data-slot="hero" class="relative grid overflow-hidden p-6 print:break-inside-avoid min-h-80 content-start">', $this->render('<twig:Hero src="/h.jpg" alt="" :width="16" :height="9" size="auto" align="start" :priority="false" />'));
    }

    public function testAVideoThatWaitsForItsReader(): void
    {
        self::assertSame(
            '<figure data-slot="image-figure" class="m-0 print:break-inside-avoid"><video data-slot="video" id="clip" class="block h-auto max-w-full supports-[aspect-ratio:attr(width_type(&lt;number&gt;),16)/9]:[aspect-ratio:attr(width_type(&lt;number&gt;),16)/attr(height_type(&lt;number&gt;),9)]" controls="" preload="none" playsinline="" width="8" height="6" poster="/p.jpg" aria-describedby="clip-transcript"><source src="/c.webm"><track kind="captions" src="/c.vtt" srclang="en" label="English" default=""><a href="/c.webm">Download the video</a></video>'
            .'<details data-slot="image-description" class="mt-2 text-sm"><summary class="cursor-pointer font-medium">Transcript</summary><p id="clip-transcript" class="mt-1 text-muted-foreground">Words</p></details></figure>',
            $this->render('<twig:Video id="clip" src="/c.webm" poster="/p.jpg" :width="8" :height="6" :tracks="[{src: \'/c.vtt\', srclang: \'en\', label: \'English\', default: true}]" transcript="Words" />'),
        );
        self::assertSame('', $this->render('<twig:Video src="data:video/mp4;base64,AA" :width="8" :height="6" />'));
    }

    public function testSeveralImagesUnderOneCaption(): void
    {
        self::assertSame(
            '<figure data-slot="image-figure" class="m-0 print:break-inside-avoid"><div data-slot="figure-group" class="grid items-start gap-2 [&amp;_img]:w-full grid-cols-3"><img data-slot="image" class="block h-auto print:max-h-[85vh] max-w-full print:object-contain" src="/a.jpg" alt="A" width="1" height="1" loading="lazy" decoding="async"></div><figcaption class="mt-2 text-sm text-muted-foreground print:break-before-avoid">C</figcaption></figure>',
            $this->render('<twig:Figure caption="C" :columns="3"><twig:Image src="/a.jpg" alt="A" :width="1" :height="1" /></twig:Figure>'),
        );
        self::assertStringStartsWith('<div data-slot="figure-group" class="grid items-start gap-2 [&amp;_img]:w-full grid-cols-[repeat(auto-fit,minmax(min(100%,10rem),1fr))]">', $this->render('<twig:Figure>x</twig:Figure>'));
    }

    public function testACelebrationInTailwindWithTheChartTokens(): void
    {
        self::assertSame(
            '<div data-celebration-message-value="You won" data-celebration-messages-value="[]" data-celebration-effect-value="fireworks" data-celebration-autoplay-value="true" data-celebration-duration-value="2500" data-celebration-focus-value="" data-celebration-colors-value="--primary --chart-1 --chart-2 --chart-3 --chart-4 --chart-5" data-slot="celebration" class="flex flex-col items-start gap-2" data-controller="celebration">'
            .'<p class="m-0 text-lg font-semibold" role="status" aria-live="polite" data-celebration-target="message">You won</p>'
            .'<template data-celebration-target="stage"><canvas class="pointer-events-none fixed inset-0 z-50 size-full motion-reduce:hidden in-data-[motion=reduce]:hidden print:hidden" aria-hidden="true"></canvas></template></div>',
            $this->render('<twig:Celebration message="You won" effect="fireworks" :autoplay="true" />'),
        );
        self::assertStringContainsString('data-celebration-effect-value="confetti"', $this->render('<twig:Celebration effect="other" />'));
        self::assertStringContainsString('data-celebration-effect-value="lasers"', $this->render('<twig:Celebration effect="lasers" />'));
        self::assertStringContainsString('data-celebration-target="message">Bravo</p>', $this->render('<twig:Celebration :messages="[\'Bravo\']" :autoplay="true" />'));
        self::assertStringContainsString('data-celebration-target="message"></p>', $this->render('<twig:Celebration message="Later" />'));
    }

    public function testTheFrameTheTokenPixelArtAndAViewTransitionAreUtilities(): void
    {
        self::assertSame(
            '<img data-slot="image" class="block h-auto print:max-h-[85vh] max-w-full rounded-lg border border-border shadow-md print:object-contain" src="/a.png" alt="A" width="1" height="1" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/a.png" alt="A" :width="1" :height="1" frame="rounded framed golden raised" />'),
        );
        self::assertSame(
            '<img data-slot="image" class="block h-auto print:max-h-[85vh] max-w-full object-top aspect-square object-cover rounded-full border-4 border-primary shadow-md" src="/pawn.png" alt="The knight" width="64" height="64" loading="lazy" decoding="async">',
            $this->render('<twig:Image src="/pawn.png" alt="The knight" :width="64" :height="64" aspect="16/9" fit="contain" position="top" frame="rounded token framed raised" />'),
        );
        self::assertSame(
            '<img data-slot="image" class="block h-auto print:max-h-[85vh] max-w-full print:object-contain [image-rendering:pixelated] [view-transition-name:var(--image-transition,none)] supports-[view-transition-name:attr(data-transition_type(&lt;custom-ident&gt;),none)]:[view-transition-name:attr(data-transition_type(&lt;custom-ident&gt;),var(--image-transition,none))]" src="/map.png" alt="The map" width="8" height="8" loading="lazy" decoding="async" data-transition="map-1">',
            $this->render('<twig:Image src="/map.png" alt="The map" :width="8" :height="8" :pixelated="true" transition="map-1" />'),
        );
        self::assertStringNotContainsString('transition', $this->render('<twig:Image src="/a.png" alt="A" :width="1" :height="1" transition="match-element" />'));
        self::assertStringNotContainsString('style', $this->render('<twig:Image src="/a.png" alt="A" :width="1" :height="1" frame="token raised" :pixelated="true" transition="a" />'));
    }

    public function testTheGalleryTheLightboxAndThePointsCarryThem(): void
    {
        $gallery = $this->render('<twig:Gallery id="maps" label="Maps" frame="rounded" :pixelated="true" :lightbox="true" :images="[{src: \'/a.png\', alt: \'A\', width: 8, height: 8, transition: \'map-a\'}]" />');

        self::assertSame(1, substr_count($gallery, 'max-w-full rounded-lg'));
        self::assertSame(2, substr_count($gallery, '[image-rendering:pixelated]'));
        self::assertSame(1, substr_count($gallery, 'data-transition="map-a"'));
        self::assertStringContainsString('print:hidden" command="close"', $gallery);

        $points = $this->render('<twig:Hotspots src="/board.png" alt="The board" :width="64" :height="32" label="Squares" frame="token rounded" :pixelated="true" :spots="[{x: 10, y: 10, label: \'Start\', href: \'#start\'}]" />');

        self::assertStringContainsString('max-w-full rounded-lg print:object-contain [image-rendering:pixelated]', $points);
        self::assertStringNotContainsString('aspect-square', $points);
        self::assertStringContainsString('print:[print-color-adjust:exact]', $points);
    }

    public function testACarouselPointsItsButtonsRightToLeftAndPrintsAsAGrid(): void
    {
        $html = $this->render('<twig:Gallery label="Strip" layout="carousel" :images="[{src: \'/a.png\', alt: \'A\', width: 8, height: 8}]" />');

        self::assertStringContainsString("rtl:[&::scroll-button(inline-start)]:content-['›'_/_attr(data-previous)] rtl:[&::scroll-button(inline-end)]:content-['‹'_/_attr(data-next)]", $html);
        self::assertStringContainsString('print:auto-cols-auto print:grid-flow-row print:grid-cols-[repeat(auto-fill,minmax(min(100%,12rem),1fr))] print:overflow-visible print:[scroll-marker-group:none] print:[&::scroll-button(*)]:content-none', $html);
    }

    public function testAThemeSwitcherInTailwindAndItsScript(): void
    {
        $html = $this->render('<twig:ThemeSwitcher />');

        self::assertStringStartsWith('<fieldset hidden data-slot="theme-switcher" data-theme-switcher-key-value="theme" class="m-0 inline-flex min-w-0 flex-wrap gap-1 border-0 p-0" data-controller="theme-switcher"><legend class="sr-only">Theme</legend>', $html);
        self::assertStringContainsString('<input type="radio" class="sr-only" name="theme" value="system" checked data-theme-switcher-target="option" data-action="theme-switcher#choose">System</label></fieldset>', $html);
        self::assertSame(3, substr_count($html, 'has-checked:bg-accent'));
        self::assertStringStartsWith('<script data-theme-key="theme" nonce="n">try{', $this->render('<twig:ThemeSwitcher:Script nonce="n" />'));
    }

    public function testAStickyHeaderInTailwind(): void
    {
        self::assertSame(
            '<header data-sticky-header-hide-on-scroll-value="false" data-sticky-header-threshold-value="8" data-slot="sticky-header" class="sticky top-0 z-10 flex flex-wrap items-center gap-3 border-b bg-background px-4 py-2 [:root:has(&amp;)]:scroll-pt-[calc(var(--phil-sticky-header-size,3.25rem)+0.5rem)]" data-controller="sticky-header">Home</header>',
            $this->render('<twig:StickyHeader>Home</twig:StickyHeader>'),
        );
        self::assertStringContainsString('data-hidden:-translate-y-full motion-reduce:transition-none', $this->render('<twig:StickyHeader :hideOnScroll="true">Home</twig:StickyHeader>'));
    }

    public function testATimelineInTailwind(): void
    {
        $html = $this->render('<twig:Timeline label="History"><twig:Timeline:Item datetime="2026-03-01">First</twig:Timeline:Item></twig:Timeline>');

        self::assertStringStartsWith('<ol data-slot="timeline" class="m-0 grid list-none gap-6 ps-0" aria-label="History"><li data-slot="timeline-item" class="relative grid gap-1 ps-6 ', $html);
        self::assertStringEndsWith('<time class="text-sm font-medium text-muted-foreground" datetime="2026-03-01">2026-03-01</time><div class="min-w-0 wrap-anywhere [&>:last-child]:mb-0">First</div></li></ol>', $html);
    }

    public function testAFloatingLabelInTailwind(): void
    {
        $html = $this->render('<twig:FloatingLabel label="Email" id="email" type="email" :required="true" />');

        self::assertStringStartsWith('<div data-slot="floating-label" class="relative"><input type="email" class="peer ', $html);
        self::assertStringContainsString(' h-12" id="email" placeholder=" " required><label for="email" class="pointer-events-none absolute start-3 top-1 ', $html);
        self::assertStringContainsString(' min-h-24" id="m" placeholder=" "></textarea>', $this->render('<twig:FloatingLabel label="M" id="m" type="textarea" />'));
    }
}
