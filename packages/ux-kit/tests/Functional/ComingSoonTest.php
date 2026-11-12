<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class ComingSoonTest extends KitTestCase
{
    public function testTheWholePage(): void
    {
        self::assertSame(
            '<div class="isSplit isFullHeight">'
            .'<div class="isCover">'
            .'<header><a href="/" class="isLogo"><picture><source type="image/webp" srcset="/logo-fr.webp"><img class="isImage" src="/logo-fr.png" alt="Studio" loading="eager" decoding="async"></picture></a></header>'
            .'<main class="isCoverBody"><div class="isNarrow">'
            .'<hgroup><p class="isEyebrow">Bientôt</p>'
            .'<h1 data-animated-headline-pause-value="2500" data-animated-headline-duration-value="600" data-animated-headline-loop-value="false" class="isHeadline" data-controller="animated-headline">'
            .'<span class="isVisuallyHidden">Studio</span><span aria-hidden="true"><span class="isReveal" aria-live="off" data-animated-headline-target="reveal"><span class="isCurrent" data-animated-headline-target="word">Studio</span></span></span></h1>'
            .'</hgroup>'
            .'<p>One.</p><p>Two &amp; three.</p><p>More <b>markup</b>.</p>'
            .'</div></main>'
            .'</div>'
            .'<figure class="isBleed hasEntrance"><picture><source type="image/webp" srcset="/main-2.webp"><img src="/main-2.png" alt="A building site" loading="lazy" decoding="async"></picture></figure>'
            .'</div>',
            $this->render(<<<'TWIG'
                <twig:ComingSoon
                    logoSrc="/logo-{locale}.png"
                    logoWebp="/logo-{locale}.webp"
                    logoAlt="Studio"
                    logoHref="/"
                    locale="fr"
                    eyebrow="Bientôt"
                    words="Studio"
                    :paragraphs="['One.', 'Two & three.']"
                    :illustrations="[{src: '/main-1.png', webp: '/main-1.webp'}, {src: '/main-2.png', webp: '/main-2.webp'}]"
                    illustrationAlt="A building site"
                    :seed="1"
                ><p>More <b>markup</b>.</p></twig:ComingSoon>
                TWIG),
        );
    }

    public function testWithoutLandmarksNorIllustration(): void
    {
        $html = $this->render('<twig:ComingSoon logoSrc="/l.svg" logoAlt="L" eyebrow="Soon" :words="[\'a\', \'b\']" headlineText="We make" :landmarks="false" theme="dark" />');

        self::assertStringStartsWith('<div class="isSplit isFullHeight"><div class="isCover"><div><span class="isLogo">', $html);
        self::assertStringContainsString('<div class="isCoverBody">', $html);
        self::assertStringContainsString('<span class="isVisuallyHidden">We make a, b</span>', $html);
        self::assertStringNotContainsString('<main', $html);
        self::assertStringNotContainsString('<header', $html);
        self::assertStringNotContainsString('<figure', $html);
    }
}
