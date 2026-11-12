<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class CelebrationTest extends KitTestCase
{
    private const string COLORS = '--phil-color-brand-fill-loud --phil-color-success-fill-loud --phil-color-warning-fill-loud --phil-color-danger-fill-loud --phil-color-info-fill-loud';

    public function testItWaitsWithAnEmptyLiveRegionAndItsCanvasInATemplate(): void
    {
        self::assertSame(
            '<div data-celebration-message-value="You won" data-celebration-effect-value="confetti" data-celebration-autoplay-value="false" data-celebration-duration-value="2500" data-celebration-colors-value="'.self::COLORS.'" class="isCelebration" data-controller="celebration">'
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

    public function testWhatPlaysItComesBeforeTheMessageAndItsAttributesAreKept(): void
    {
        $html = $this->render('<twig:Celebration message="Bingo" class="wide" data-action="poll:revealed@window->celebration#play"><button type="button" data-action="celebration#play">Go</button></twig:Celebration>');

        self::assertStringContainsString('class="isCelebration wide" data-controller="celebration" data-action="poll:revealed@window-&gt;celebration#play">', $html);
        self::assertStringContainsString('<button type="button" data-action="celebration#play">Go</button><p class="isCelebrationMessage"', $html);
    }

    public function testNoStyleIsWritten(): void
    {
        self::assertStringNotContainsString('style', $this->render('<twig:Celebration message="Yes" :autoplay="true" />'));
    }
}
