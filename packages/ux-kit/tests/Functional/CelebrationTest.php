<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use Phil\UxKit\Application\CelebrationStream;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class CelebrationTest extends KitTestCase
{
    private const string COLORS = '--phil-color-brand-fill-loud --phil-color-success-fill-loud --phil-color-warning-fill-loud --phil-color-danger-fill-loud --phil-color-info-fill-loud';

    public function testItWaitsWithAnEmptyLiveRegionAndItsCanvasInATemplate(): void
    {
        self::assertSame(
            '<div data-celebration-message-value="You won" data-celebration-messages-value="[]" data-celebration-effect-value="confetti" data-celebration-autoplay-value="false" data-celebration-duration-value="2500" data-celebration-focus-value="" data-celebration-colors-value="'.self::COLORS.'" class="isCelebration" data-controller="celebration">'
            .'<p class="isCelebrationMessage" role="status" aria-live="polite" data-celebration-target="message"></p>'
            .'<template data-celebration-target="stage"><canvas class="isCelebrationCanvas" aria-hidden="true"></canvas></template></div>',
            $this->render('<twig:Celebration message="You won" />'),
        );
    }

    public function testOnAPageThatArrivesOnAVictoryTheMessageIsThereFromTheStart(): void
    {
        $html = $this->render('<twig:Celebration message="<Bravo>" effect="fireworks" :autoplay="true" :duration="3000" />');

        self::assertStringContainsString('data-celebration-message-value="&lt;Bravo&gt;"', $html);
        self::assertStringContainsString('data-celebration-effect-value="fireworks"', $html);
        self::assertStringContainsString('data-celebration-autoplay-value="true"', $html);
        self::assertStringContainsString('data-celebration-duration-value="3000"', $html);
        self::assertStringContainsString('data-celebration-target="message">&lt;Bravo&gt;</p>', $html);
    }

    public function testAnUnknownEffectIsConfetti(): void
    {
        self::assertStringContainsString('data-celebration-effect-value="confetti"', $this->render('<twig:Celebration effect="&quot;><script>" />'));
    }

    public function testEveryEffectOfTheControllerIsKept(): void
    {
        foreach (['confetti', 'fireworks', 'lasers', 'balloons', 'shooting-stars', 'streamers', 'coins', 'bubbles', 'aurora', 'spotlight', 'trophy'] as $effect) {
            self::assertStringContainsString(\sprintf('data-celebration-effect-value="%s"', $effect), $this->render(\sprintf('<twig:Celebration effect="%s" />', $effect)));
        }
    }

    public function testItsMessagesAreAListOfTextsAndTheFirstIsShownOnAPageThatArrivesOnAVictory(): void
    {
        $html = $this->render('<twig:Celebration :messages="[\'Well done\', \'\', \'<Bravo>\']" :autoplay="true" focus="#winner" />');

        self::assertStringContainsString('data-celebration-messages-value="[&quot;Well done&quot;,&quot;&lt;Bravo&gt;&quot;]"', $html);
        self::assertStringContainsString('data-celebration-message-value=""', $html);
        self::assertStringContainsString('data-celebration-focus-value="#winner"', $html);
        self::assertStringContainsString('data-celebration-target="message">Well done</p>', $html);
        self::assertStringContainsString('data-celebration-target="message">Bingo</p>', $this->render('<twig:Celebration message="Bingo" :messages="[\'Well done\']" :autoplay="true" />'), 'the message first');
        self::assertStringContainsString('data-celebration-target="message"></p>', $this->render('<twig:Celebration :messages="[\'Well done\']" />'), 'empty until it plays');
    }

    public function testWhatPlaysItComesBeforeTheMessageAndItsAttributesAreKept(): void
    {
        $html = $this->render('<twig:Celebration message="Bingo" class="wide" data-action="poll:revealed@window->celebration#play"><button type="button" data-action="celebration#play">Go</button></twig:Celebration>');

        self::assertStringContainsString('class="isCelebration wide" data-controller="celebration" data-action="poll:revealed@window-&gt;celebration#play">', $html);
        self::assertStringContainsString('<button type="button" data-action="celebration#play">Go</button><p class="isCelebrationMessage"', $html);
    }

    public function testCelebratePlaysItFromTheServerByATurboStream(): void
    {
        self::assertSame(
            '<turbo-stream action="celebrate" effect="trophy" message="First &lt;place&gt;" focus="#winner"><template></template></turbo-stream>',
            $this->render("{{ celebrate('First <place>', 'trophy', '#winner') }}"),
        );
        self::assertSame('<turbo-stream action="celebrate" effect="confetti"><template></template></turbo-stream>', $this->render('{{ celebrate() }}'));
    }

    public function testItsMessagesInTheLanguageOfTheRequestOrOfTheLocaleGiven(): void
    {
        self::assertSame('Bien joué!|Bravo!', $this->render("{{ celebration_messages('fr_CA')|slice(0, 2)|join('|') }}"));
        self::assertSame('Well done!', $this->render('{{ celebration_messages()|first }}'));
        [$html] = $this->renderInRequest("{{ app.request.setLocale('fr') }}{{ celebration_messages()|first }}");
        self::assertSame('Bien joué!', $html);
    }

    /**
     * The live examples of its README, which a demo renders: a button for each effect, and nothing that plays on load.
     */
    public function testTheExamplesOfItsReadmeOfferAButtonForEachEffectAndNothingPlaysOnLoad(): void
    {
        $readme = (string) file_get_contents(\dirname(__DIR__, 2).'/celebration/README.md');
        self::assertGreaterThan(1, preg_match_all('/^```twig \{"preview":true\}\R(.*?)\R```$/ms', $readme, $examples));
        $html = implode("\n", array_map($this->render(...), $examples[1]));

        foreach (CelebrationStream::EFFECTS as $effect) {
            self::assertStringContainsString(\sprintf('<button type="button" data-action="celebration#play" data-celebration-effect-param="%s">', $effect), $html, $effect);
        }

        self::assertStringNotContainsString('data-celebration-autoplay-value="true"', $html);
        self::assertStringNotContainsString('<turbo-stream', $html);
    }

    public function testItsEffectNamesEachEffectOfTheControllerInItsType(): void
    {
        $template = (string) file_get_contents(\dirname(__DIR__, 2).'/celebration/templates/components/Celebration.html.twig');

        self::assertStringContainsString("## '".implode("'|'", CelebrationStream::EFFECTS)."' What is drawn;", $template);
    }

    public function testNoStyleIsWritten(): void
    {
        self::assertStringNotContainsString('style', $this->render('<twig:Celebration message="Yes" :autoplay="true" />'));
    }
}
