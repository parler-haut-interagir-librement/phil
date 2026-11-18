<?php

declare(strict_types=1);

namespace Phil\Markup\Tests\Unit\Adapter;

use Closure;
use LogicException;
use Phil\Markup\Adapter\AmmoniaPurifier;
use Phil\Markup\Adapter\Purifiers;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\Exception\InputTooLarge;
use Phil\Markup\HtmlPurifier;
use Phil\Markup\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * The ceiling of every purifier: an HTML of the ceiling's length is purified, one byte more is refused whole, before a
 * sanitizer sees it, by an `InputTooLarge` that says both lengths. Never cut to fit.
 */
#[CoversClass(SanitizerPurifier::class)]
#[CoversClass(AmmoniaPurifier::class)]
#[CoversClass(Purifiers::class)]
#[CoversClass(InputTooLarge::class)]
final class PurifierCeilingTest extends TestCase
{
    public function testTheDefaultCeilingIsFourMebibytes(): void
    {
        self::assertSame(4_194_304, HtmlPurifier::DEFAULT_MAX_BYTES);
    }

    public function testTheSanitizerPurifiesUpToItsCeilingAndRefusesMore(): void
    {
        $purifier = SanitizerPurifier::defaultPolicies(maxBytes: 8);

        foreach (Level::cases() as $level) {
            self::assertSame('<b>1</b>', $purifier->purify('<b>1</b>', $level));
            $this->assertRefused(9, 8, static fn (): string => $purifier->purify('<b>12</b>', $level));
        }

        $this->assertRefused(9, 8, static fn (): string => SanitizerPurifier::defaultPolicies([], 8)->purify('<b>12</b>', Level::All));
    }

    /**
     * Refused before the sanitizer of the level sees it, so a sanitizer that would throw on any input is never called.
     */
    public function testTheSanitizerIsNotCalledAboveTheCeiling(): void
    {
        $never = new class implements HtmlSanitizerInterface {
            public function sanitize(string $input): string
            {
                throw new LogicException('The sanitizer should not be called.');
            }

            public function sanitizeFor(string $element, string $input): string
            {
                throw new LogicException('The sanitizer should not be called.');
            }
        };
        $purifier = new SanitizerPurifier(['basic' => $never, 'forum' => $never, 'all' => $never], 4);

        $this->assertRefused(5, 4, static fn (): string => $purifier->purify('12345', Level::Basic));
    }

    public function testTheSanitizerRefusesAnHtmlAboveFourMebibytesByDefault(): void
    {
        $exact = str_repeat('a', 4_194_304);

        self::assertSame($exact, SanitizerPurifier::defaultPolicies()->purify($exact, Level::Basic));
        $this->assertRefused(4_194_305, 4_194_304, static fn (): string => SanitizerPurifier::defaultPolicies()->purify($exact.'a', Level::Basic));
        $this->assertRefused(4_194_305, 4_194_304, static fn (): string => new SanitizerPurifier(['basic' => new NoSanitizer(), 'forum' => new NoSanitizer(), 'all' => new NoSanitizer()])->purify($exact.'a', Level::All));
        $this->assertRefused(4_194_305, 4_194_304, static fn (): string => Purifiers::defaultPolicies()->purify($exact.'a', Level::Forum));
    }

    /**
     * The ceiling counts bytes: a character of two bytes counts twice.
     */
    public function testTheCeilingCountsBytes(): void
    {
        self::assertSame('éé', SanitizerPurifier::defaultPolicies(maxBytes: 4)->purify('éé', Level::Basic));
        $this->assertRefused(6, 4, static fn (): string => SanitizerPurifier::defaultPolicies(maxBytes: 4)->purify('ééé', Level::Basic));
    }

    public function testThePurifierChosenTakesTheCeiling(): void
    {
        foreach ([true, false] as $extension) {
            $purifier = Purifiers::defaultPolicies([], $extension, 8);

            self::assertSame('<b>1</b>', $purifier->purify('<b>1</b>', Level::All));
            $this->assertRefused(9, 8, static fn (): string => $purifier->purify('<b>12</b>', Level::All));
        }
    }

    #[RequiresPhpExtension('ammonia')]
    public function testTheExtensionPurifiesUpToItsCeilingAndRefusesMore(): void
    {
        $purifier = AmmoniaPurifier::defaultPolicies(minimumBytes: 0, maxBytes: 8);

        self::assertSame('<b>1</b>', $purifier->purify('<b>1</b>', Level::All));
        $this->assertRefused(9, 8, static fn (): string => $purifier->purify('<b>12</b>', Level::All));
        $this->assertRefused(9, 8, static fn (): string => AmmoniaPurifier::defaultPolicies(maxBytes: 8)->purify('<input a>', Level::All));
        $this->assertRefused(4_194_305, 4_194_304, static fn (): string => AmmoniaPurifier::defaultPolicies()->purify(str_repeat('a', 4_194_305), Level::Basic));
    }

    /**
     * @param Closure(): string $purify
     */
    private function assertRefused(int $bytes, int $limit, Closure $purify): void
    {
        try {
            $purify();
            self::fail(\sprintf('An HTML of %d bytes should be refused above %d.', $bytes, $limit));
        } catch (InputTooLarge $inputTooLarge) {
            self::assertSame([$bytes, $limit], [$inputTooLarge->bytes, $inputTooLarge->limit]);
        }
    }
}

/**
 * A sanitizer that keeps its input as it is, for the length a purifier refuses by default.
 */
final class NoSanitizer implements HtmlSanitizerInterface
{
    public function sanitize(string $input): string
    {
        return $input;
    }

    public function sanitizeFor(string $element, string $input): string
    {
        return $input;
    }
}
