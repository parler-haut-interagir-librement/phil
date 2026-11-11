<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Tabs, Accordion, Carousel.
 */
#[CoversNothing]
final class InteractiveRecipesTest extends KitTestCase
{
    public function testTabs(): void
    {
        self::assertSame(
            '<div data-slot="tabs" data-tabs-active-tab-value="a" data-orientation="horizontal" class="isTabs" data-controller="tabs"><div data-slot="tabs-list" role="tablist" aria-orientation="horizontal" data-variant="default" class="isTabList" aria-label="V"><button type="button" id="tab-a" data-slot="tabs-trigger" data-tabs-target="trigger" data-tab-id="a" role="tab" aria-controls="tab-a-description" aria-selected="true" tabindex="0" data-active="true" class="isTab" data-action="click-&gt;tabs#open keydown-&gt;tabs#move">A</button><button type="button" id="tab-b" data-slot="tabs-trigger" data-tabs-target="trigger" data-tab-id="b" role="tab" aria-controls="tab-b-description" aria-selected="false" tabindex="-1" data-active="false" class="isTab" data-action="click-&gt;tabs#open keydown-&gt;tabs#move">B</button></div><div id="tab-a-description" data-slot="tabs-content" data-tabs-target="tab" data-tab-id="a" role="tabpanel" aria-labelledby="tab-a" tabindex="0" data-active="true" data-state="active" class="isTabPanel isCurrent">1</div><div id="tab-b-description" data-slot="tabs-content" data-tabs-target="tab" data-tab-id="b" role="tabpanel" aria-labelledby="tab-b" tabindex="0" data-active="false" data-state="inactive" class="isTabPanel">2</div></div>',
            $this->render('<twig:Tabs defaultValue="a"><twig:Tabs:List aria-label="V"><twig:Tabs:Trigger value="a">A</twig:Tabs:Trigger><twig:Tabs:Trigger value="b">B</twig:Tabs:Trigger></twig:Tabs:List><twig:Tabs:Content value="a">1</twig:Tabs:Content><twig:Tabs:Content value="b">2</twig:Tabs:Content></twig:Tabs>'),
        );
    }

    public function testVerticalTabsAndTheLineVariant(): void
    {
        $html = $this->render('<twig:Tabs orientation="vertical"><twig:Tabs:List variant="line">x</twig:Tabs:List></twig:Tabs>');

        self::assertStringContainsString('data-orientation="vertical"', $html);
        self::assertStringContainsString('aria-orientation="vertical" data-variant="line"', $html);
    }

    public function testAnAccordionOnDetailsAndSummary(): void
    {
        self::assertSame(
            '<div id="f" data-slot="accordion" data-accordion-multiple-value="false" data-accordion-orientation-value="vertical" data-orientation="vertical" class="isAccordion" data-controller="accordion"><details data-slot="accordion-item" data-accordion-target="item" data-value="a" data-disabled="false" name="f" open class="isAccordionItem"><summary id="f-a-trigger" data-slot="accordion-trigger" data-accordion-target="trigger" data-orientation="vertical" class="isAccordionTrigger" data-action="click-&gt;accordion#toggle keydown-&gt;accordion#handleKeydown">A</summary><div id="f-a-content" data-slot="accordion-content" data-accordion-target="content" role="region" aria-labelledby="f-a-trigger" class="isAccordionContent">1</div></details><details data-slot="accordion-item" data-accordion-target="item" data-value="b" data-disabled="true" name="f" inert class="isAccordionItem">x</details></div>',
            $this->render('<twig:Accordion id="f" defaultValue="a"><twig:Accordion:Item value="a"><twig:Accordion:Trigger>A</twig:Accordion:Trigger><twig:Accordion:Content>1</twig:Accordion:Content></twig:Accordion:Item><twig:Accordion:Item value="b" :disabled="true">x</twig:Accordion:Item></twig:Accordion>'),
        );
    }

    public function testAnAccordionOfSeveralOpenItemsIsNotExclusive(): void
    {
        $html = $this->render('<twig:Accordion id="f" :multiple="true" :defaultValue="[\'a\', \'b\']" orientation="horizontal"><twig:Accordion:Item value="a">x</twig:Accordion:Item><twig:Accordion:Item value="b">y</twig:Accordion:Item><twig:Accordion:Item value="c" :open="true">z</twig:Accordion:Item><twig:Accordion:Item value="d">w</twig:Accordion:Item></twig:Accordion>');

        self::assertStringNotContainsString(' name=', $html);
        self::assertSame(3, substr_count($html, ' open class="isAccordionItem">'));
        self::assertStringContainsString('data-accordion-multiple-value="true"', $html);
        self::assertStringContainsString('data-accordion-orientation-value="horizontal"', $html);
    }

    public function testACarouselScrollsWithoutItsController(): void
    {
        $html = $this->render('<twig:Carousel label="Photos"><twig:Carousel:Content><twig:Carousel:Item>1</twig:Carousel:Item></twig:Carousel:Content><twig:Carousel:Previous /><twig:Carousel:Next /></twig:Carousel>');

        self::assertStringStartsWith('<div role="region" aria-roledescription="carousel" tabindex="0" data-slot="carousel" data-orientation="horizontal" data-align="center" data-carousel-orientation-value="horizontal" data-carousel-options-value="{&quot;align&quot;:&quot;center&quot;,&quot;loop&quot;:false}" data-carousel-autoplay-value="0" data-carousel-pause-label-value="Pause" data-carousel-play-label-value="Play" class="isCarouselFrame hasCenteredSlides" aria-label="Photos" data-controller="carousel" data-action="keydown-&gt;carousel#handleKeydown', $html);
        self::assertStringContainsString('<div class="isCarouselViewport" data-slot="carousel-content" data-carousel-target="viewport" data-action="scroll->carousel#update:passive"><div class="isCarouselTrack"><div role="group" aria-roledescription="slide" data-slot="carousel-item" class="isCarouselSlide">1</div></div></div>', $html);
        self::assertStringContainsString('data-slot="carousel-previous" data-variant="outline" data-size="icon-sm" class="isOutline hasIcon isIconOnly isSmall isCarouselPrevious" type="button" hidden="" data-carousel-target="previous" data-action="click-&gt;carousel#scrollPrev"><span aria-hidden="true">&lsaquo;</span><span class="isVisuallyHidden">Previous slide</span></button>', $html);
        self::assertStringContainsString('data-carousel-target="next" data-action="click-&gt;carousel#scrollNext"><span aria-hidden="true">&rsaquo;</span><span class="isVisuallyHidden">Next slide</span></button>', $html);
        self::assertStringNotContainsString('carousel-toggle', $html);
    }

    public function testTheTextsOfACarouselAndItsPauseButton(): void
    {
        $html = $this->render('<twig:Carousel :autoplay="4000" :loop="true" pauseLabel="Arrêter" playLabel="Lire" roleDescription="carrousel" orientation="vertical" align="start" :options="{loop: false}"><twig:Carousel:Item roleDescription="diapositive">1</twig:Carousel:Item><twig:Carousel:Next text="Suivante" variant="ghost" size="sm" /></twig:Carousel>');

        self::assertStringContainsString('aria-roledescription="carrousel"', $html);
        self::assertStringContainsString('aria-roledescription="diapositive"', $html);
        self::assertStringContainsString('data-carousel-autoplay-value="4000"', $html);
        self::assertStringContainsString('data-orientation="vertical" data-align="start"', $html);
        self::assertStringContainsString('{&quot;align&quot;:&quot;start&quot;,&quot;loop&quot;:false}', $html);
        self::assertStringContainsString('>Suivante<', $html);
        self::assertStringContainsString('data-slot="carousel-toggle"', $html);
        self::assertStringContainsString('data-carousel-target="toggle" data-action="click-&gt;carousel#toggleAutoplay">Arrêter</button>', $html);
    }
}
