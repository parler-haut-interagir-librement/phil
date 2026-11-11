<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class AutosaveStatusTest extends KitTestCase
{
    private const string C = 'phil--forms-autosave--autosave';

    public function testTheLiveRegionAndTheHiddenNoticeWithItsButtons(): void
    {
        $c = self::C;

        self::assertSame(
            '<div class="isStack"><p class="isMuted" role="status" aria-live="polite" data-'.$c.'-target="status"></p>'
            .'<div class="isCluster" role="group" hidden data-'.$c.'-target="notice"><p class="isGrow" data-'.$c.'-target="message"></p>'
            .'<button type="button" hidden data-'.$c.'-target="useServer" data-action="'.$c.'#useServer">Use the newer draft</button>'
            .'<button type="button" data-'.$c.'-target="discard" data-action="'.$c.'#discard">Discard the draft</button>'
            .'<button type="button" data-'.$c.'-target="dismiss" data-action="'.$c.'#dismiss">Keep it</button></div></div>',
            $this->render('<twig:AutosaveStatus />'),
        );
    }

    public function testTheButtonsAreProps(): void
    {
        $html = $this->render('<twig:AutosaveStatus discardLabel="Abandonner" dismissLabel="Garder" useServerLabel="<Prendre>" class="wide" />');

        self::assertStringStartsWith('<div class="isStack wide">', $html);
        self::assertStringContainsString('>Abandonner</button>', $html);
        self::assertStringContainsString('>Garder</button>', $html);
        self::assertStringContainsString('>&lt;Prendre&gt;</button>', $html);
    }
}
