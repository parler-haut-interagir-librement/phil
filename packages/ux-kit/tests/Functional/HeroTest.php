<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use Twig\Error\RuntimeError;

/**
 * A hero: a real image across its box, the main one of the page by default, and its content over it.
 */
#[CoversNothing]
final class HeroTest extends KitTestCase
{
    public function testTheImageIsTheMainOneOfThePageAndCoversTheScreen(): void
    {
        [$html, $links] = $this->renderInRequest('<twig:Hero src="/hall.jpg" alt="" :width="1600" :height="900"><h1>Welcome</h1></twig:Hero>');

        self::assertSame(
            '<div class="isHero hasHeroScreen"><div class="isHeroMedia"><img class="isImage hasLayoutFullWidth hasFitCover" src="/hall.jpg" alt="" width="1600" height="900" loading="eager" fetchpriority="high"></div><div class="isHeroContent"><h1>Welcome</h1></div></div>',
            $html,
        );
        self::assertSame([[['preload'], '/hall.jpg', ['as' => 'image', 'fetchpriority' => 'high']]], $links);
    }

    public function testItsWidthsAreThoseOfTheScreenAndItsSourcesCropForPhones(): void
    {
        self::assertSame(
            '<div id="top" class="isHero hasHeroLarge hasHeroCenter home"><div class="isHeroMedia"><picture>'
            .'<source type="image/webp" srcset="/hall-square.webp" sizes="100vw" media="(max-width: 40rem)" width="600" height="600">'
            .'<img class="isImage hasLayoutFullWidth hasFitCover hasPositionTop hasPlaceholderBlue" src="/hall.jpg" srcset="/hall-960.jpg 960w, /hall-1920.jpg 1920w" sizes="100vw" alt="The hall" width="1920" height="1080" loading="eager" fetchpriority="high">'
            .'</picture></div><div class="isHeroContent"></div></div>',
            $this->render('<twig:Hero id="top" class="home" src="/hall.jpg" :srcset="{960: \'/hall-960.jpg\', 1920: \'/hall-1920.jpg\'}" :sources="[{type: \'image/webp\', srcset: \'/hall-square.webp\', media: \'(max-width: 40rem)\', width: 600, height: 600}]" alt="The hall" :width="1920" :height="1080" size="large" align="center" position="top" placeholder="blue" />'),
        );
    }

    public function testBelowTheFoldItLoadsLazilyAndStandsAtTheHeightOfItsContent(): void
    {
        [$html, $links] = $this->renderInRequest('<twig:Hero src="/hall.jpg" alt="" :width="16" :height="9" size="auto" align="start" :priority="false">Hi</twig:Hero>');

        self::assertSame(
            '<div class="isHero hasHeroStart"><div class="isHeroMedia"><img class="isImage hasLayoutFullWidth hasFitCover" src="/hall.jpg" alt="" width="16" height="9" loading="lazy" decoding="async"></div><div class="isHeroContent">Hi</div></div>',
            $html,
        );
        self::assertSame([], $links);
    }

    public function testARefusedImageLeavesTheContentAlone(): void
    {
        self::assertSame(
            '<div class="isHero hasHeroScreen"><div class="isHeroMedia"></div><div class="isHeroContent">Hi</div></div>',
            $this->render('<twig:Hero src="https://tracker.example.com/a.jpg" alt="" :width="16" :height="9">Hi</twig:Hero>'),
        );
    }

    public function testTheTextAlternativeIsRequired(): void
    {
        $this->expectException(RuntimeError::class);

        $this->render('<twig:Hero src="/hall.jpg" :width="16" :height="9" />');
    }
}
