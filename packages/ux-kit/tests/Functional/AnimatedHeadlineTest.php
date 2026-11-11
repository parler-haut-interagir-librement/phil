<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class AnimatedHeadlineTest extends KitTestCase
{
    public function testOneWordIsRevealedOnceAndReadWhole(): void
    {
        self::assertSame(
            '<h1 data-animated-headline-pause-value="2500" data-animated-headline-duration-value="600" data-animated-headline-loop-value="false" class="isHeadline" data-controller="animated-headline">'
            .'<span class="isVisuallyHidden">Studio</span>'
            .'<span aria-hidden="true"><span class="isReveal" aria-live="off" data-animated-headline-target="reveal"><span class="isCurrent" data-animated-headline-target="word">Studio</span></span></span>'
            .'</h1>',
            $this->render('<twig:AnimatedHeadline words="Studio" />'),
        );
    }

    public function testOneWordDoesNotCycleEvenWithTheLoop(): void
    {
        $html = $this->render('<twig:AnimatedHeadline words="Studio" :loop="true" />');

        self::assertStringContainsString('data-animated-headline-loop-value="false"', $html);
        self::assertStringNotContainsString('<button', $html);
    }

    public function testTheLoopBringsAPauseButtonWithItsLabels(): void
    {
        $html = $this->render('<twig:AnimatedHeadline :words="[\'sites\', \'apps\']" :loop="true" />');

        self::assertMatchesRegularExpression(
            '~^<h1 data-animated-headline-pause-value="2500" data-animated-headline-duration-value="600" data-animated-headline-loop-value="true" data-animated-headline-pause-label-value="Pause animation" data-animated-headline-play-label-value="Play animation" aria-labelledby="(animated-headline-\\d+)" class="isHeadline" data-controller="animated-headline"><span id="\\1" class="isVisuallyHidden">sites, apps</span>~',
            $html,
        );
        self::assertStringEndsWith(
            '</span></span><button type="button" class="isRevealToggle" hidden data-animated-headline-target="toggle" data-action="animated-headline#toggle">Pause animation</button></h1>',
            $html,
        );
    }

    public function testTheButtonLabelsAreTranslatable(): void
    {
        $html = $this->render('<twig:AnimatedHeadline :words="[\'a\', \'b\']" :loop="true" pauseLabel="Mettre en pause" playLabel="Relancer" />');

        self::assertStringContainsString('data-animated-headline-pause-label-value="Mettre en pause" data-animated-headline-play-label-value="Relancer"', $html);
        self::assertStringContainsString('data-action="animated-headline#toggle">Mettre en pause</button>', $html);
    }

    public function testSeveralWordsWithTextAroundThem(): void
    {
        self::assertSame(
            '<h2 data-animated-headline-pause-value="1000" data-animated-headline-duration-value="300" data-animated-headline-loop-value="false" class="isHeadline big" data-controller="animated-headline">'
            .'<span class="isVisuallyHidden">We make sites, apps, games for everyone</span>'
            .'<span aria-hidden="true">We make <span class="isReveal" aria-live="off" data-animated-headline-target="reveal">'
            .'<span class="isCurrent" data-animated-headline-target="word">sites</span>'
            .'<span data-animated-headline-target="word">apps</span>'
            .'<span data-animated-headline-target="word">games</span>'
            .'</span> for everyone</span></h2>',
            $this->render('<twig:AnimatedHeadline :words="[\'sites\', \'apps\', \'games\']" text="We make" suffix="for everyone" :level="2" :pause="1000" :duration="300" :loop="false" class="big" />'),
        );
    }

    public function testTheLabelAndTheSeparatorAreWhatScreenReadersRead(): void
    {
        self::assertStringContainsString(
            '<span class="isVisuallyHidden">Sites or apps</span>',
            $this->render('<twig:AnimatedHeadline :words="[\'Sites\', \'apps\']" separator=" or " />'),
        );
        self::assertStringContainsString(
            '<span class="isVisuallyHidden">A whole sentence.</span>',
            $this->render('<twig:AnimatedHeadline :words="[\'a\', \'b\']" label="A whole sentence." />'),
        );
    }

    public function testTheLevelStaysAHeading(): void
    {
        self::assertStringStartsWith('<h6 ', $this->render('<twig:AnimatedHeadline words="x" :level="9" />'));
        self::assertStringStartsWith('<h1 ', $this->render('<twig:AnimatedHeadline words="x" :level="0" />'));
    }

    public function testTheWordsAreEscaped(): void
    {
        $html = $this->render('<twig:AnimatedHeadline :words="words" />', ['words' => ['<i>a</i>']]);

        self::assertStringContainsString('<span class="isVisuallyHidden">&lt;i&gt;a&lt;/i&gt;</span>', $html);
        self::assertStringNotContainsString('<i>', $html);
    }
}
