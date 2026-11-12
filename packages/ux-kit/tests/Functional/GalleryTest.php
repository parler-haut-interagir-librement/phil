<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use Twig\Error\RuntimeError;

/**
 * A grid of figures, the images of a list, enlarged in a lightbox when asked.
 */
#[CoversNothing]
final class GalleryTest extends KitTestCase
{
    private const string IMAGES = "[{src: '/a.jpg', alt: 'The stage', width: 800, height: 600, caption: 'Opening'}, {src: '/b.jpg', alt: 'The crowd', width: 600, height: 800, sources: [{type: 'image/webp', srcset: '/b.webp'}], credit: 'Ana Lima'}]";

    public function testAListOfFiguresNamedByItsLabel(): void
    {
        self::assertSame(
            '<div class="isGallery"><ul role="list" aria-label="The festival">'
            .'<li><figure><img class="isImage hasRatio1x1 hasFitCover" src="/a.jpg" alt="The stage" width="800" height="600" loading="lazy" decoding="async"><figcaption>Opening</figcaption></figure></li>'
            .'<li><figure><picture><source type="image/webp" srcset="/b.webp"><img class="isImage hasRatio1x1 hasFitCover" src="/b.jpg" alt="The crowd" width="600" height="800" loading="lazy" decoding="async"></picture><figcaption><small class="isCredit">Ana Lima</small></figcaption></figure></li>'
            .'</ul></div>',
            $this->render('<twig:Gallery label="The festival" :images="'.self::IMAGES.'" aspect="1/1" />'),
        );
    }

    public function testEachImageOpensInALightbox(): void
    {
        $html = $this->render('<twig:Gallery id="festival" label="The festival" :images="'.self::IMAGES.'" :lightbox="true" openLabel="Enlarge" class="isWide" />');

        self::assertStringStartsWith('<div id="festival" class="isGallery isWide"><ul role="list" aria-label="The festival"><li><div class="isLightbox" data-controller="lightbox"><figure><button type="button" class="isLightboxTrigger" command="show-modal" commandfor="lightbox-festival-1"', $html);
        self::assertStringContainsString('<dialog id="lightbox-festival-2"', $html);
        self::assertSame(2, substr_count($html, '<span class="isVisuallyHidden">Enlarge</span>'));
    }

    public function testTheSizesOfTheGridReachEachImage(): void
    {
        self::assertStringContainsString(
            'srcset="/a-400.jpg 400w, /a-800.jpg 800w" sizes="auto, (min-width: 48rem) 33vw, 100vw"',
            $this->render('<twig:Gallery label="L" :images="[{src: \'/a.jpg\', srcset: {400: \'/a-400.jpg\', 800: \'/a-800.jpg\'}, alt: \'A\', width: 800, height: 600}]" sizes="(min-width: 48rem) 33vw, 100vw" />'),
        );
    }

    public function testAnImageOfAnotherHostLeavesItsPlaceEmptyAndNoListWithoutImages(): void
    {
        self::assertSame(
            '<div class="isGallery"><ul role="list" aria-label="L"><li><img class="isImage" src="/a.jpg" alt="A" width="1" height="1" loading="lazy" decoding="async"></li></ul></div>',
            $this->render('<twig:Gallery label="L" :images="[{src: \'https://tracker.example.com/p.gif\', alt: \'\', width: 1, height: 1}, {src: \'/a.jpg\', alt: \'A\', width: 1, height: 1}]" />'),
        );
        self::assertSame('', $this->render('<twig:Gallery label="L" :images="[]" />'));
    }

    public function testWithoutAnIdentifierTheLightboxesAreNamedAfterTheGallery(): void
    {
        self::assertStringContainsString('<dialog id="lightbox-gallery-2"', $this->render('<twig:Gallery label="L" :images="'.self::IMAGES.'" :lightbox="true" />'));
    }

    public function testTheLabelIsRequired(): void
    {
        $this->expectException(RuntimeError::class);

        $this->render('<twig:Gallery :images="'.self::IMAGES.'" />');
    }
}
