<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use Twig\Error\RuntimeError;

/**
 * An image with points of interest: a list of links over it, each placed by classes and by its data attributes.
 */
#[CoversNothing]
final class HotspotsTest extends KitTestCase
{
    public function testEachPointIsALinkPlacedByItsClassesAndItsAttributes(): void
    {
        self::assertSame(
            '<figure><div id="plan" class="isHotspots wide"><img class="isImage" src="/plan.png" alt="The plan of the hall" width="800" height="400" loading="lazy" decoding="async">'
            .'<ul role="list" class="isHotspotsList" aria-label="Rooms">'
            .'<li class="isHotspot hasHotspotX20 hasHotspotY30" data-x="20" data-y="30"><a href="/stage">Stage</a></li>'
            .'<li class="isHotspot hasHotspotX75 hasHotspotY60" data-x="72.5" data-y="62.4"><a href="#bar">Bar &amp; café</a></li>'
            .'</ul></div><figcaption>The hall<small class="isCredit">Ana Lima</small></figcaption></figure>',
            $this->render('<twig:Hotspots id="plan" class="wide" src="/plan.png" alt="The plan of the hall" :width="800" :height="400" label="Rooms" caption="The hall" credit="Ana Lima" :spots="[{x: 20, y: 30, label: \'Stage\', href: \'/stage\'}, {x: 72.5, y: 62.4, label: \'Bar & café\', href: \'#bar\'}]" />'),
        );
    }

    public function testAPointBeyondTheImageStandsOnItsEdge(): void
    {
        self::assertStringContainsString(
            '<li class="isHotspot hasHotspotX100 hasHotspotY0" data-x="100" data-y="0"><a href="/far">Far</a></li>',
            $this->render('<twig:Hotspots src="/plan.png" alt="Plan" :width="8" :height="4" label="Rooms" :spots="[{x: 140, y: 0, label: \'Far\', href: \'/far\'}]" />'),
        );
        self::assertStringContainsString(
            '<li class="isHotspot hasHotspotX0 hasHotspotY5" data-x="2.4" data-y="2.5">',
            $this->render('<twig:Hotspots src="/plan.png" alt="Plan" :width="8" :height="4" label="Rooms" :spots="[{x: 2.4, y: 2.5, label: \'Near\', href: \'/near\'}]" />'),
            'to the nearest five per cent, half way up',
        );
    }

    public function testAPointWithoutWordsAddressOrPlaceOrWithARefusedAddressIsLeftOut(): void
    {
        self::assertSame(
            '<div class="isHotspots"><img class="isImage" src="/plan.png" alt="Plan" width="8" height="4" loading="lazy" decoding="async"><ul role="list" class="isHotspotsList" aria-label="Rooms"><li class="isHotspot hasHotspotX50 hasHotspotY50" data-x="50" data-y="50"><a href="/kept">Kept</a></li></ul></div>',
            $this->render('<twig:Hotspots src="/plan.png" alt="Plan" :width="8" :height="4" label="Rooms" :spots="[
                {x: 1, y: 1, label: \'Script\', href: \' JavaScript:alert(1)\'},
                {x: 1, y: 1, label: \'Inline\', href: \'data:text/html,x\'},
                {x: 1, y: 1, label: \'Cut\', href: \'java\tscript:alert(1)\'},
                {x: 1, y: 1, label: \'\', href: \'/empty\'},
                {x: 1, y: 1, label: \'No address\'},
                {x: \'left\', y: 1, label: \'Word\', href: \'/word\'},
                {x: -5, y: 1, label: \'Negative\', href: \'/negative\'},
                {y: 1, label: \'No place\', href: \'/none\'},
                {x: 50, y: 50, label: \'Kept\', href: \'/kept\'},
            ]" />'),
        );
    }

    public function testWithoutAPointThereIsNoListAndARefusedImageDrawsNothing(): void
    {
        self::assertSame(
            '<div class="isHotspots"><img class="isImage" src="/plan.png" alt="Plan" width="8" height="4" loading="lazy" decoding="async"></div>',
            $this->render('<twig:Hotspots src="/plan.png" alt="Plan" :width="8" :height="4" label="Rooms" />'),
        );
        self::assertSame('', $this->render('<twig:Hotspots src="https://tracker.example.com/plan.png" alt="Plan" :width="8" :height="4" label="Rooms" :spots="[{x: 1, y: 1, label: \'A\', href: \'/a\'}]" />'));
        self::assertSame('', $this->render('<twig:Hotspots src="data:text/html,x" alt="Plan" :width="8" :height="4" label="Rooms" />'));
        self::assertSame('', $this->render('<twig:Hotspots src="" alt="Plan" :width="8" :height="4" label="Rooms" />'));
    }

    public function testThePictureTakesItsSourcesAndItsPriority(): void
    {
        [$html, $links] = $this->renderInRequest('<twig:Hotspots src="/plan.png" :sources="[{type: \'image/avif\', srcset: \'/plan.avif\'}]" alt="Plan" :width="8" :height="4" label="Rooms" :priority="true" placeholder="green" />');

        self::assertSame('<div class="isHotspots"><picture><source type="image/avif" srcset="/plan.avif"><img class="isImage hasPlaceholderGreen" src="/plan.png" alt="Plan" width="8" height="4" loading="eager" fetchpriority="high"></picture></div>', $html);
        self::assertSame([[['preload'], '/plan.png', ['as' => 'image', 'imagesrcset' => '/plan.avif', 'type' => 'image/avif', 'fetchpriority' => 'high']]], $links);
    }

    public function testTheLabelIsRequired(): void
    {
        $this->expectException(RuntimeError::class);

        $this->render('<twig:Hotspots src="/plan.png" alt="Plan" :width="8" :height="4" />');
    }
}
