<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use Phil\UxKit\Application\Festivities;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class SeasonalDecorTest extends KitTestCase
{
    private const string KONAMI = 'ArrowUp ArrowUp ArrowDown ArrowDown ArrowLeft ArrowRight ArrowLeft ArrowRight b a';

    public function testADecorWaitsWithItsCanvasAndItsEggInTemplatesAndItsCloseButtonHidden(): void
    {
        $html = $this->render('<twig:SeasonalDecor decor="snow" holiday="winter-holidays-2026-12-20" />');

        self::assertStringStartsWith(
            '<div data-seasonal-decor-decor-value="snow" data-seasonal-decor-holiday-value="winter-holidays-2026-12-20" data-seasonal-decor-sober-value="false" data-seasonal-decor-eggs-value="0" data-seasonal-decor-egg-label-value="A hidden egg" data-seasonal-decor-counter-value="Eggs found: %found% of %total%" data-seasonal-decor-hunted-value="Every egg found!" data-seasonal-decor-code-value="'.self::KONAMI.'" data-seasonal-decor-code-message-value="You found the secret code!" data-seasonal-decor-code-effect-value="fireworks" data-seasonal-decor-duration-value="12000" class="isSeasonalDecor" data-controller="seasonal-decor">'
            .'<template data-seasonal-decor-target="stage"><canvas class="isSeasonalDecorCanvas" aria-hidden="true"></canvas></template>'
            .'<template data-seasonal-decor-target="egg"><button type="button" class="isSeasonalEgg" aria-label="A hidden egg">',
            $html,
        );
        self::assertStringContainsString('<p class="isSeasonalDecorCounter" role="status" aria-live="polite" data-seasonal-decor-target="counter"></p>', $html);
        self::assertStringContainsString('<button type="button" class="isSeasonalDecorClose" aria-label="Hide the decorations" data-seasonal-decor-target="close" data-action="seasonal-decor#close" hidden>', $html);
        self::assertStringNotContainsString('isSeasonalDecorPoppy', $html);
    }

    public function testNothingIsRenderedForAnUnknownDecorOrNone(): void
    {
        self::assertSame('', $this->render('<twig:SeasonalDecor decor="&quot;><script>" />'));
        self::assertSame('', $this->render('<twig:SeasonalDecor />'));
    }

    public function testEveryDecorOfTheConfigurationIsKept(): void
    {
        foreach (Festivities::DECORS as $decor) {
            self::assertStringContainsString(\sprintf('data-seasonal-decor-decor-value="%s"', $decor), $this->render(\sprintf('<twig:SeasonalDecor decor="%s" />', $decor)), $decor);
        }

        $template = (string) file_get_contents(\dirname(__DIR__, 2).'/seasonal-decor/templates/components/SeasonalDecor.html.twig');
        self::assertStringContainsString("## '".implode("'|'", Festivities::DECORS)."' What is drawn", $template);
    }

    public function testThePoppyIsFixedAndSoberWithoutHuntOrCode(): void
    {
        $html = $this->render('<twig:SeasonalDecor decor="poppy" :eggs="5" closeLabel="Hide the poppy" />');

        self::assertStringContainsString('<svg class="isSeasonalDecorPoppy" viewBox="0 0 64 64" aria-hidden="true" focusable="false">', $html);
        self::assertStringContainsString('data-seasonal-decor-sober-value="true"', $html);
        self::assertStringContainsString('data-seasonal-decor-eggs-value="0"', $html);
        self::assertStringContainsString('data-seasonal-decor-code-value=""', $html);
        self::assertStringContainsString('aria-label="Hide the poppy"', $html);
        self::assertStringNotContainsString('<animate', $html);
    }

    public function testASoberDayHasNoHuntOrCode(): void
    {
        $html = $this->render('<twig:SeasonalDecor decor="leaves" :sober="true" :eggs="3" />');

        self::assertStringContainsString('data-seasonal-decor-sober-value="true"', $html);
        self::assertStringContainsString('data-seasonal-decor-eggs-value="0"', $html);
        self::assertStringContainsString('data-seasonal-decor-code-value=""', $html);
        self::assertStringNotContainsString('isSeasonalDecorPoppy', $html);
    }

    public function testTheHuntAndTheCodeAreTheSites(): void
    {
        $html = $this->render('<twig:SeasonalDecor decor="eggs" :eggs="5" eggLabel="Un œuf" counter="%found% sur %total%" hunted="Bravo" code="g n o m e" codeMessage="<Gnome>" codeEffect="balloons" :duration="5000" class="mine" />');

        self::assertStringContainsString('data-seasonal-decor-eggs-value="5"', $html);
        self::assertStringContainsString('aria-label="Un œuf"', $html);
        self::assertStringContainsString('data-seasonal-decor-counter-value="%found% sur %total%"', $html);
        self::assertStringContainsString('data-seasonal-decor-hunted-value="Bravo"', $html);
        self::assertStringContainsString('data-seasonal-decor-code-value="g n o m e"', $html);
        self::assertStringContainsString('data-seasonal-decor-code-message-value="&lt;Gnome&gt;"', $html);
        self::assertStringContainsString('data-seasonal-decor-code-effect-value="balloons"', $html);
        self::assertStringContainsString('data-seasonal-decor-duration-value="5000"', $html);
        self::assertStringContainsString('class="isSeasonalDecor mine"', $html);
    }

    public function testTheHolidayOfADayFromTheConfiguration(): void
    {
        self::assertSame('halloween|bats|0|halloween-2026-10-25', $this->render("{% set season = seasonal_decor('2026-10-31') %}{{ season.key }}|{{ season.decor }}|{{ season.sober ? 1 : 0 }}|{{ season.id }}"));
        self::assertSame('remembrance-day|poppy|1', $this->render("{% set season = seasonal_decor('2026-11-11 23:30') %}{{ season.key }}|{{ season.decor }}|{{ season.sober ? 1 : 0 }}"));
        self::assertSame('none', $this->render("{{ seasonal_decor('2026-08-12') ?? 'none' }}"));
    }

    public function testTheLayoutOfItsReadmeRendersTheDecorOfTheDay(): void
    {
        $html = $this->render("{% set season = seasonal_decor('2026-12-24') %}{% if season %}<twig:SeasonalDecor :decor=\"season.decor\" :holiday=\"season.id\" :sober=\"season.sober\" />{% endif %}");

        self::assertStringContainsString('data-seasonal-decor-decor-value="snow" data-seasonal-decor-holiday-value="winter-holidays-2026-12-20"', $html);
    }

    /**
     * The live examples of its README, which a demo renders: a decor, its nests and a poppy.
     */
    public function testTheExamplesOfItsReadmeRender(): void
    {
        $readme = (string) file_get_contents(\dirname(__DIR__, 2).'/seasonal-decor/README.md');
        self::assertGreaterThan(1, preg_match_all('/^```twig \{"preview":true\}\R(.*?)\R```$/ms', $readme, $examples));
        $html = implode("\n", array_map($this->render(...), $examples[1]));

        self::assertStringContainsString('data-seasonal-decor-eggs-value="3"', $html);
        self::assertSame(3, substr_count($html, '<p data-egg-nest>'));
        self::assertStringContainsString('isSeasonalDecorPoppy', $html);
    }

    public function testNoStyleIsWritten(): void
    {
        self::assertStringNotContainsString('style', $this->render('<twig:SeasonalDecor decor="bats" :eggs="2" />'));
    }
}
