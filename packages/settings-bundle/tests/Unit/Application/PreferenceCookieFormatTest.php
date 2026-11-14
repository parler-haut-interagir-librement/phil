<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Application;

use Phil\SettingsBundle\Application\Visitor\PreferenceCookieFormat;
use Phil\SettingsBundle\Tests\Unit\Fixture\Catalogs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PreferenceCookieFormat::class)]
final class PreferenceCookieFormatTest extends TestCase
{
    public function testTheSettingsKeptForVisitorsAreRead(): void
    {
        $format = new PreferenceCookieFormat(Catalogs::sample());

        self::assertSame(['display.motion' => 'reduce', 'author.public' => true, 'display.theme' => 'dark'], $format->parse('display.motion=reduce&author.public=1&display.theme=dark'));
        self::assertSame(['author.public' => false], $format->parse('author.public=0'));
        self::assertSame(['display.motion' => 'system'], $format->parse('display.motion=system'));
        self::assertSame([], $format->parse(''));
    }

    public function testWhatIsNotRecognisedIsLeftOut(): void
    {
        $format = new PreferenceCookieFormat(Catalogs::sample());

        self::assertSame(['display.motion' => 'reduce'], $format->parse('display.motion=reduce&display.theme=sepia&author.columns=3&author.public=yes&nothing.here=1&display.contrast&=more&display.text_size=large=x'));
        self::assertSame(['display.motion' => 'reduce', 'display.theme' => 'dark'], $format->parse('display.motion=reduce&display.theme=dark&display.motion=spin'), 'a refused repetition leaves the earlier value');
        self::assertSame(['display.motion' => 'system'], $format->parse('display.motion=reduce&display.motion=system'), 'the last valid value wins');
    }

    public function testACookieTooLongOrTooManyPairsIsBounded(): void
    {
        $format = new PreferenceCookieFormat(Catalogs::sample());
        $long = 'display.motion=reduce&'.str_repeat('x', PreferenceCookieFormat::MAX_LENGTH);

        self::assertSame([], $format->parse(substr($long, 0, PreferenceCookieFormat::MAX_LENGTH + 1)));
        self::assertSame(['display.motion' => 'reduce'], $format->parse(substr($long, 0, PreferenceCookieFormat::MAX_LENGTH)));

        $pairs = array_fill(0, PreferenceCookieFormat::MAX_PAIRS - 1, 'x=1');
        self::assertSame(['display.motion' => 'reduce'], $format->parse(implode('&', [...$pairs, 'display.motion=reduce'])));
        self::assertSame([], $format->parse(implode('&', [...$pairs, 'x=1', 'display.motion=reduce'])), 'past the 32nd pair');
        self::assertSame(['display.motion' => 'reduce'], $format->parse(implode('&', [...$pairs, 'display.motion=reduce', 'x=1'])), 'the 32nd pair stands alone');
    }

    public function testTheValuesAreWrittenSortedWithoutTheirDefaults(): void
    {
        $format = new PreferenceCookieFormat(Catalogs::sample());

        self::assertSame('author.public=1&display.motion=reduce&display.theme=dark', $format->format([
            'display.theme' => 'dark',
            'display.motion' => 'reduce',
            'display.contrast' => 'system',
            'author.public' => true,
            'author.columns' => 3,
            'author.signature' => 'Ada',
            'display.text_size' => 'huge',
            'nothing.here' => '1',
        ]));
        self::assertSame('', $format->format(['author.public' => false, 'display.motion' => 'system']));
        self::assertSame('display.motion=reduce', $format->format($format->parse('display.motion=reduce')));
    }
}
