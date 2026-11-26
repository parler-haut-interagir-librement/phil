<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class ScratchCardTest extends KitTestCase
{
    public function testThePrizeShowsWithoutTheControllerItsCoverInATemplateAndItsButtonHidden(): void
    {
        self::assertSame(
            '<div data-scratch-card-message-value="" data-scratch-card-threshold-value="50" data-scratch-card-effect-value="" data-scratch-card-color-value="--phil-color-neutral-fill-loud" class="isScratchCard" data-controller="scratch-card">'
            .'<div class="isScratchCardPrize" data-scratch-card-target="prize"><p>A free coffee</p></div>'
            .'<template data-scratch-card-target="stage"><canvas class="isScratchCardCover" aria-hidden="true" data-action="pointerdown->scratch-card#press pointermove->scratch-card#scratch pointerup->scratch-card#release pointercancel->scratch-card#release"></canvas></template>'
            .'<button type="button" class="isScratchCardButton" data-scratch-card-target="button" data-action="scratch-card#reveal" hidden>Reveal</button>'
            .'<p class="isScratchCardMessage" role="status" aria-live="polite" data-scratch-card-target="message"></p></div>',
            $this->render('<twig:ScratchCard><p>A free coffee</p></twig:ScratchCard>'),
        );
    }

    public function testItsValuesAndAttributesAreKept(): void
    {
        $html = $this->render('<twig:ScratchCard label="Gratter" message="<Won>" :threshold="40" effect="coins" id="card">Prize</twig:ScratchCard>');

        self::assertStringContainsString('data-scratch-card-message-value="&lt;Won&gt;" data-scratch-card-threshold-value="40" data-scratch-card-effect-value="coins"', $html);
        self::assertStringContainsString('class="isScratchCard" data-controller="scratch-card" id="card"', $html);
        self::assertStringContainsString('hidden>Gratter</button>', $html);
    }

    public function testTheExamplesOfItsReadmeRenderAndNoStyleIsWritten(): void
    {
        $readme = (string) file_get_contents(\dirname(__DIR__, 2).'/scratch-card/README.md');
        self::assertGreaterThan(1, preg_match_all('/^```twig \{"preview":true\}\R(.*?)\R```$/ms', $readme, $examples));
        $html = implode("\n", array_map($this->render(...), $examples[1]));

        self::assertSame(2, substr_count($html, 'data-controller="scratch-card"'));
        self::assertStringNotContainsString('style', $html);
    }
}
