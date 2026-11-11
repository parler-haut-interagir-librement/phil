<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use Twig\Error\RuntimeError;

#[CoversNothing]
final class LogoTest extends KitTestCase
{
    public function testAnImageAlone(): void
    {
        self::assertSame(
            '<span class="isLogo"><img src="/logo.svg" alt="PHIL" loading="eager" decoding="async"></span>',
            $this->render('<twig:Logo src="/logo.svg" alt="PHIL" />'),
        );
    }

    public function testAWebpSourceWithItsFallbackLinkedHome(): void
    {
        self::assertSame(
            '<a href="/" class="isLogo wide"><picture><source type="image/webp" srcset="/logo.webp"><img src="/logo.png" alt="Studio" width="240" height="60" loading="lazy" decoding="async"></picture></a>',
            $this->render('<twig:Logo src="/logo.png" webp="/logo.webp" alt="Studio" href="/" :width="240" :height="60" loading="lazy" class="wide" />'),
        );
    }

    public function testTheThemeAndTheLocaleAreWrittenIntoTheSources(): void
    {
        self::assertSame(
            '<span class="isLogo"><picture><source type="image/webp" srcset="/logo-dark-fr.webp"><img src="/logo-dark-fr.png" alt="" loading="eager" decoding="async"></picture></span>',
            $this->render('<twig:Logo src="/logo-{theme}-{locale}.png" webp="/logo-{theme}-{locale}.webp" alt="" theme="dark" locale="fr" />'),
        );
        self::assertSame(
            '<span class="isLogo"><img src="/logo-{theme}.svg" alt="X" loading="eager" decoding="async"></span>',
            $this->render('<twig:Logo src="/logo-{theme}.svg" alt="X" />'),
            'without a theme, the placeholder is left as written',
        );
    }

    public function testTheSourcesAndTheTextAreEscaped(): void
    {
        self::assertSame(
            '<span class="isLogo"><img src="/a?b=1&amp;c=&quot;2&quot;" alt="&lt;b&gt;" loading="eager" decoding="async"></span>',
            $this->render('<twig:Logo :src="src" :alt="alt" />', ['src' => '/a?b=1&c="2"', 'alt' => '<b>']),
        );
    }

    public function testTheTextAlternativeIsRequired(): void
    {
        $this->expectException(RuntimeError::class);

        $this->render('<twig:Logo src="/logo.svg" />');
    }
}
