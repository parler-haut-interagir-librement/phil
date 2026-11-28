<?php

declare(strict_types=1);

namespace Phil\Measurement\Tests\Unit\Property;

use Phil\Measurement\Fraction;
use Phil\Measurement\Measurement;
use Phil\Measurement\Unit;
use Phil\Measurement\UnitRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * Properties of the conversions, on values drawn from a seeded generator so that a failure is replayed: a value
 * converted to any unit of its family and back is the value again, to the digits it was written with; a conversion
 * keeps the order of two values, the other way for a reciprocal unit.
 */
#[CoversClass(Measurement::class)]
#[CoversClass(Unit::class)]
#[CoversClass(Fraction::class)]
final class ConversionRoundTripTest extends TestCase
{
    private const int DRAWS = 40;

    public function testAValueComesBackFromEveryUnitOfItsFamily(): void
    {
        $randomizer = new Randomizer(new Xoshiro256StarStar(20261124));
        $registry = UnitRegistry::standard();

        foreach ($registry->families() as $family) {
            foreach ($family->units as $from) {
                for ($draw = 0; $draw < self::DRAWS; ++$draw) {
                    $value = $this->decimal($randomizer, $from->reciprocal);
                    $measurement = $registry->measurement($value, $from->code);
                    $codes = array_keys($family->units);
                    $to = $family->units[$codes[$randomizer->getInt(0, \count($codes) - 1)]];

                    self::assertSame(
                        $measurement->toDecimal(6),
                        $measurement->convertTo($to)->convertTo($from)->toDecimal(6),
                        \sprintf('%s %s through %s', $value, $from->code, $to->code),
                    );
                }
            }
        }
    }

    public function testAConversionKeepsTheOrder(): void
    {
        $randomizer = new Randomizer(new Xoshiro256StarStar(11242026));
        $registry = UnitRegistry::standard();

        foreach ($registry->families() as $family) {
            foreach ($family->units as $to) {
                $from = $family->standard;
                $left = $registry->measurement($this->decimal($randomizer, false), $from->code);
                $right = $registry->measurement($this->decimal($randomizer, false), $from->code);
                $expected = $left->compareTo($right);
                $converted = $left->convertTo($to)->value()->compare($right->convertTo($to)->value());

                self::assertSame($to->reciprocal ? -$expected : $expected, $converted, $to->code);
            }
        }
    }

    public function testASumIsTheSameInEveryUnit(): void
    {
        $randomizer = new Randomizer(new Xoshiro256StarStar(42));
        $registry = UnitRegistry::standard();
        $kilograms = $registry->unit('kg');

        for ($draw = 0; $draw < self::DRAWS; ++$draw) {
            $left = $registry->measurement($this->decimal($randomizer, false), 'kg');
            $right = $registry->measurement($this->decimal($randomizer, false), '[lb_av]');

            self::assertSame(
                $left->add($right)->toDecimal(6),
                $left->convertTo($registry->unit('[oz_av]'))->add($right)->convertTo($kilograms)->toDecimal(6),
            );
        }
    }

    /**
     * A positive decimal of one to six digits before the point, so that every unit of its family holds it, and zero to
     * six after.
     */
    private function decimal(Randomizer $randomizer, bool $reciprocal): string
    {
        $integer = (string) $randomizer->getInt($reciprocal ? 1 : 0, 10 ** $randomizer->getInt(1, 6) - 1);
        $digits = $randomizer->getInt(0, 6);

        return 0 === $digits ? $integer : $integer.'.'.str_pad((string) $randomizer->getInt(0, 10 ** $digits - 1), $digits, '0', \STR_PAD_LEFT);
    }
}
