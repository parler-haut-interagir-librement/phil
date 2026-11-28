<?php

declare(strict_types=1);

namespace Phil\Measurement\Tests\Unit;

use Phil\Measurement\Exception\InvalidMeasurement;
use Phil\Measurement\Exception\MeasurementOutOfRange;
use Phil\Measurement\Exception\UnknownUnit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidMeasurement::class)]
#[CoversClass(MeasurementOutOfRange::class)]
#[CoversClass(UnknownUnit::class)]
final class ExceptionMessageTest extends TestCase
{
    public function testAnInputIsShownWhole(): void
    {
        $text = 'X'.str_repeat('a', 63);

        self::assertSame(\sprintf('"%s" is not a decimal: digits, with an optional "-" and "." followed by digits, are expected.', $text), InvalidMeasurement::notADecimal($text)->getMessage());
        self::assertSame(\sprintf('"%s" is not a measurement: a decimal, one space and a unit code are expected, as in "12.5 kg".', $text), InvalidMeasurement::notAMeasurement($text)->getMessage());
    }

    public function testALongInputIsCutAtSixtyFourBytesWithAnEllipsis(): void
    {
        $text = 'X'.str_repeat('a', 69);
        $shown = 'X'.str_repeat('a', 63).'…';

        self::assertStringContainsString('"'.$shown.'" is not a decimal', InvalidMeasurement::notADecimal($text)->getMessage());
        self::assertStringContainsString('"'.$shown.'" is not a measurement', InvalidMeasurement::notAMeasurement($text)->getMessage());
        self::assertStringContainsString('"X'.str_repeat('a', 63).'…" is not a decimal', InvalidMeasurement::notADecimal('X'.str_repeat('a', 64))->getMessage());
    }

    public function testACutNeverSplitsACharacter(): void
    {
        // The accent takes the bytes 64 and 65: it is left out whole.
        $text = str_repeat('a', 63).'étail';

        self::assertStringContainsString('"'.str_repeat('a', 63).'…" is not a decimal', InvalidMeasurement::notADecimal($text)->getMessage());
    }

    public function testEveryMessageOfAnotherKindCutsAtSixtyFourBytesToo(): void
    {
        $text = 'X'.str_repeat('a', 62).'étail';
        $cut = 'X'.str_repeat('a', 62);
        $whole = 'X'.str_repeat('a', 63);

        self::assertSame(\sprintf('"%s" has more than 18 significant digits: an exact measurement holds 18 at most.', $cut), MeasurementOutOfRange::tooManyDigits($text)->getMessage());
        self::assertSame(\sprintf('"%s" has more than 18 significant digits: an exact measurement holds 18 at most.', $whole), MeasurementOutOfRange::tooManyDigits($whole.'abc')->getMessage());
        self::assertSame(\sprintf('The unit "%s" is not in the registry.', $cut), UnknownUnit::code($text)->getMessage());
        self::assertSame(\sprintf('The unit "%s" is not in the registry.', $whole), UnknownUnit::code($whole.'abc')->getMessage());
        self::assertSame(\sprintf('No unit of the registry has the UCUM code "%s".', $cut), UnknownUnit::ucum($text)->getMessage());
        self::assertSame(\sprintf('No unit of the registry has the UCUM code "%s".', $whole), UnknownUnit::ucum($whole.'abc')->getMessage());
        self::assertSame(\sprintf('The UCUM code "%s" names a unit of more than one family: name the family too.', $cut), UnknownUnit::ambiguousUcum($text)->getMessage());
        self::assertSame(\sprintf('The UCUM code "%s" names a unit of more than one family: name the family too.', $whole), UnknownUnit::ambiguousUcum($whole.'abc')->getMessage());
        self::assertSame(\sprintf('The family "%s" is not in the registry.', $cut), UnknownUnit::family($text)->getMessage());
        self::assertSame(\sprintf('The family "%s" is not in the registry.', $whole), UnknownUnit::family($whole.'abc')->getMessage());
    }
}
