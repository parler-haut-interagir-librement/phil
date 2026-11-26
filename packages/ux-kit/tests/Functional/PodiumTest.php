<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class PodiumTest extends KitTestCase
{
    public function testTheFirstThreeInTheirOrderEachOnAStepHiddenFromScreenReaders(): void
    {
        self::assertSame(
            '<div data-podium-message-value="Ada wins" data-podium-effect-value="" data-podium-autoplay-value="true" class="isPodium" data-controller="podium">'
            .'<ol class="isPodiumPlaces" aria-label="Podium">'
            .'<li class="isPodiumPlace" data-place="1"><span class="isPodiumName">Ada</span><span class="isPodiumScore">42</span><span class="isPodiumStep" aria-hidden="true">1</span></li>'
            .'<li class="isPodiumPlace" data-place="2"><span class="isPodiumName">Grace</span><span class="isPodiumStep" aria-hidden="true">2</span></li>'
            .'<li class="isPodiumPlace" data-place="3"><span class="isPodiumName">Alan</span><span class="isPodiumScore">0</span><span class="isPodiumStep" aria-hidden="true">3</span></li>'
            .'</ol><p class="isPodiumMessage" role="status" aria-live="polite" data-podium-target="message"></p></div>',
            $this->render("<twig:Podium :places=\"[{name: 'Ada', score: 42}, {name: 'Grace'}, {name: 'Alan', score: 0}, {name: 'Edsger'}]\" message=\"Ada wins\" />"),
        );
    }

    public function testItsNamesAreEscapedAndWhatFollowsComesBeforeTheMessage(): void
    {
        $html = $this->render("<twig:Podium :places=\"[{name: '<b>Ada</b>', score: '3–1'}]\" label=\"Final\" effect=\"confetti\" :autoplay=\"false\" class=\"wide\"><button type=\"button\" data-action=\"podium#rise\">Reveal</button></twig:Podium>");

        self::assertStringContainsString('<span class="isPodiumName">&lt;b&gt;Ada&lt;/b&gt;</span><span class="isPodiumScore">3–1</span>', $html);
        self::assertStringContainsString('aria-label="Final"', $html);
        self::assertStringContainsString('data-podium-effect-value="confetti" data-podium-autoplay-value="false" class="isPodium wide"', $html);
        self::assertStringContainsString('</ol><button type="button" data-action="podium#rise">Reveal</button><p class="isPodiumMessage"', $html);
    }

    public function testTheExamplesOfItsReadmeRenderAndNoStyleIsWritten(): void
    {
        $readme = (string) file_get_contents(\dirname(__DIR__, 2).'/podium/README.md');
        self::assertGreaterThan(1, preg_match_all('/^```twig \{"preview":true\}\R(.*?)\R```$/ms', $readme, $examples));
        $html = implode("\n", array_map($this->render(...), $examples[1]));

        self::assertSame(3, substr_count($html, 'data-action="podium#rise"'));
        self::assertStringNotContainsString('data-podium-autoplay-value="true"', $html);
        self::assertStringNotContainsString('style', $html);
    }
}
