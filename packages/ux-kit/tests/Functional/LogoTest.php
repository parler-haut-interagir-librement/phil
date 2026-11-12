<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use Twig\Error\RuntimeError;

/**
 * The logo, drawn through the Picture of the image recipe: its AVIF and WebP sources, its densities, its theme and locale.
 */
#[CoversNothing]
final class LogoTest extends KitTestCase
{
    public function testAnImageAlone(): void
    {
        self::assertSame(
            '<span class="isLogo"><img class="isImage" src="/logo.svg" alt="PHIL" loading="eager" decoding="async"></span>',
            $this->render('<twig:Logo src="/logo.svg" alt="PHIL" />'),
        );
    }

    public function testAWebpSourceWithItsFallbackLinkedHome(): void
    {
        self::assertSame(
            '<a href="/" class="isLogo wide"><picture><source type="image/webp" srcset="/logo.webp"><img class="isImage" src="/logo.png" alt="Studio" width="240" height="60" loading="lazy" decoding="async"></picture></a>',
            $this->render('<twig:Logo src="/logo.png" webp="/logo.webp" alt="Studio" href="/" :width="240" :height="60" loading="lazy" class="wide" />'),
        );
    }

    public function testAvifThenWebpByDensity(): void
    {
        self::assertSame(
            '<span class="isLogo"><picture><source type="image/avif" srcset="/1x/logo.avif 1x, /2x/logo.avif 2x"><source type="image/webp" srcset="/1x/logo.webp 1x, /2x/logo.webp 2x"><img class="isImage" src="/1x/logo.png" srcset="/1x/logo.png 1x, /2x/logo.png 2x" alt="Studio" width="160" height="40" loading="eager" decoding="async"></picture></span>',
            $this->render('<twig:Logo src="/1x/logo.png" :srcset="{1: \'/1x/logo.png\', 2: \'/2x/logo.png\'}" :avif="{1: \'/1x/logo.avif\', 2: \'/2x/logo.avif\'}" :webp="{1: \'/1x/logo.webp\', 2: \'/2x/logo.webp\'}" alt="Studio" :width="160" :height="40" />'),
        );
    }

    public function testTheThemeAndTheLocaleAreWrittenIntoTheSources(): void
    {
        self::assertSame(
            '<span class="isLogo"><picture><source type="image/avif" srcset="/logo-dark-fr.avif 1x, /logo-dark-fr@2x.avif 2x"><source type="image/webp" srcset="/logo-dark-fr.webp"><img class="isImage" src="/logo-dark-fr.png" alt="" loading="eager" decoding="async"></picture></span>',
            $this->render('<twig:Logo src="/logo-{theme}-{locale}.png" :avif="{1: \'/logo-{theme}-{locale}.avif\', 2: \'/logo-{theme}-{locale}@2x.avif\'}" webp="/logo-{theme}-{locale}.webp" alt="" theme="dark" locale="fr" />'),
        );
        self::assertSame(
            '<span class="isLogo"><img class="isImage" src="/logo-{theme}.svg" alt="X" loading="eager" decoding="async"></span>',
            $this->render('<twig:Logo src="/logo-{theme}.svg" alt="X" />'),
            'without a theme, the placeholder is left as written',
        );
    }

    public function testTheSourcesAndTheTextAreEscaped(): void
    {
        self::assertSame(
            '<span class="isLogo"><img class="isImage" src="/a?b=1&amp;c=&quot;2&quot;" alt="&lt;b&gt;" loading="eager" decoding="async"></span>',
            $this->render('<twig:Logo :src="src" :alt="alt" />', ['src' => '/a?b=1&c="2"', 'alt' => '<b>']),
        );
    }

    public function testARefusedSourceDrawsNoImage(): void
    {
        self::assertSame(
            '<a href="/" class="isLogo"></a>',
            $this->render('<twig:Logo src="javascript:alert(1)" alt="PHIL" href="/" />'),
        );
        self::assertSame(
            '<span class="isLogo"><img class="isImage" src="/logo.png" alt="PHIL" loading="eager" decoding="async"></span>',
            $this->render('<twig:Logo src="/logo.png" webp="data:text/html,x" alt="PHIL" />'),
        );
    }

    public function testTheTextAlternativeIsRequired(): void
    {
        $this->expectException(RuntimeError::class);

        $this->render('<twig:Logo src="/logo.svg" />');
    }
}
