<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use Twig\Error\RuntimeError;

/**
 * An image enlarged in a native modal dialog, opened and closed by the invokers of HTML, the lightbox controller
 * standing in where the browser does not run them.
 */
#[CoversNothing]
final class LightboxTest extends KitTestCase
{
    private const string CLOSE = '<button type="button" class="isLightboxClose" command="close" commandfor="lightbox-bridge" data-action="click->lightbox#close"><span aria-hidden="true">&times;</span><span class="isVisuallyHidden">Close</span></button>';

    public function testAThumbnailOpensTheFullImageInAModalDialog(): void
    {
        self::assertSame(
            '<div class="isLightbox" data-controller="lightbox">'
            .'<button type="button" class="isLightboxTrigger" command="show-modal" commandfor="lightbox-bridge" aria-haspopup="dialog" data-action="click->lightbox#open">'
            .'<img class="isImage" src="/bridge-640.jpg" alt="A bridge" width="640" height="480" loading="lazy" decoding="async">'
            .'<span class="isVisuallyHidden">Enlarge</span></button>'
            .'<dialog id="lightbox-bridge" class="isLightboxDialog" closedby="any" aria-label="A bridge" data-lightbox-target="dialog" data-action="click->lightbox#closeOnClickOutside">'
            .'<img class="isImage hasFitContain" src="/bridge.jpg" alt="A bridge" width="1600" height="1200" loading="lazy" decoding="async">'
            .self::CLOSE.'</dialog></div>',
            $this->render('<twig:Lightbox id="bridge" src="/bridge-640.jpg" alt="A bridge" :width="640" :height="480" :full="{src: \'/bridge.jpg\', width: 1600, height: 1200}" openLabel="Enlarge" />'),
        );
    }

    public function testWithoutAFullImageTheThumbnailIsShownWholeAndItsCaptionUnderBoth(): void
    {
        self::assertSame(
            '<div class="isLightbox" data-controller="lightbox">'
            .'<figure><button type="button" class="isLightboxTrigger" command="show-modal" commandfor="lightbox-bridge" aria-haspopup="dialog" data-action="click->lightbox#open">'
            .'<picture><source type="image/avif" srcset="/b-480.avif 480w, /b-960.avif 960w" sizes="auto, 50vw"><img class="isImage hasRatio1x1 hasFitCover" src="/b.jpg" alt="A bridge" width="960" height="720" loading="lazy" decoding="async"></picture>'
            .'</button><figcaption>Dusk<small class="isCredit">Ana Lima</small></figcaption></figure>'
            .'<dialog id="lightbox-bridge" class="isLightboxDialog" closedby="any" aria-label="A bridge" data-lightbox-target="dialog" data-action="click->lightbox#closeOnClickOutside">'
            .'<figure><picture><source type="image/avif" srcset="/b-480.avif 480w, /b-960.avif 960w" sizes="auto, 100vw"><img class="isImage hasFitContain" src="/b.jpg" alt="A bridge" width="960" height="720" loading="lazy" decoding="async"></picture>'
            .'<figcaption>Dusk<small class="isCredit">Ana Lima</small></figcaption></figure>'
            .self::CLOSE.'</dialog></div>',
            $this->render('<twig:Lightbox id="bridge" src="/b.jpg" :sources="[{type: \'image/avif\', srcset: {480: \'/b-480.avif\', 960: \'/b-960.avif\'}}]" sizes="50vw" aspect="1/1" alt="A bridge" :width="960" :height="720" caption="Dusk" credit="Ana Lima" />'),
        );
    }

    public function testTheLabelsAreProps(): void
    {
        $html = $this->render('<twig:Lightbox id="bridge" src="/b.jpg" alt="Un pont" :width="1" :height="1" openLabel="Agrandir" closeLabel="Fermer" />');

        self::assertStringContainsString('<span class="isVisuallyHidden">Agrandir</span>', $html);
        self::assertStringContainsString('<span class="isVisuallyHidden">Fermer</span>', $html);
    }

    public function testADecorativeThumbnailIsNamedByItsCaption(): void
    {
        self::assertStringContainsString(
            'aria-label="The bridge at dusk"',
            $this->render('<twig:Lightbox id="bridge" src="/b.jpg" alt="" :width="1" :height="1" caption="The bridge at dusk" />'),
        );
    }

    public function testARefusedImageDrawsNoLightbox(): void
    {
        self::assertSame('', $this->render('<twig:Lightbox id="bridge" src="https://tracker.example.com/p.gif" alt="A" :width="1" :height="1" />'));
        self::assertSame('', $this->render('<twig:Lightbox id="bridge" src="javascript:x" alt="A" :width="1" :height="1" />'));
    }

    public function testTheIdentifierIsRequired(): void
    {
        $this->expectException(RuntimeError::class);

        $this->render('<twig:Lightbox src="/b.jpg" alt="A" :width="1" :height="1" />');
    }
}
