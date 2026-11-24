<?php

declare(strict_types=1);

namespace Phil\Pricing;

use Phil\Pricing\Exception\AmountOutOfRange;
use Phil\Pricing\Exception\InvalidRate;
use RoundingMode;

/**
 * Integer arithmetic that never leaves the native range silently and rounds every division by a named mode.
 *
 * @internal
 */
final class IntegerMath
{
    private function __construct()
    {
    }

    /**
     * The product, or `AmountOutOfRange` when it does not fit in a native integer: PHP turns such a product into a
     * float, which no amount or rate ever is.
     */
    public static function multiply(int $left, int $right): int
    {
        return self::checked($left * $right);
    }

    public static function subtract(int $left, int $right): int
    {
        return self::checked($left - $right);
    }

    /**
     * The share of the price the cost takes at a target margin, `1 − margin`, in parts per million.
     *
     * @throws InvalidRate when the margin is 100 % or more
     */
    public static function costShare(Rate $margin): int
    {
        if ($margin->partsPerMillion >= Rate::ONE_MILLION) {
            throw InvalidRate::marginNotBelowOneHundredPercent($margin->percent());
        }

        return self::subtract(Rate::ONE_MILLION, $margin->partsPerMillion);
    }

    /**
     * The rate `numerator / denominator` in parts per million, rounded by `$mode`.
     */
    public static function rate(int $numerator, int $denominator, RoundingMode $mode): Rate
    {
        return Rate::of(self::divide(self::multiply($numerator, Rate::ONE_MILLION), $denominator, $mode));
    }

    /**
     * The quotient `numerator / denominator`, rounded by `$mode`, in integer arithmetic only.
     *
     * The denominator is never zero and neither operand is `PHP_INT_MIN`: the callers check both.
     */
    public static function divide(int $numerator, int $denominator, RoundingMode $mode): int
    {
        $quotient = intdiv($numerator, $denominator);
        $remainder = abs($numerator % $denominator);

        if (0 === $remainder) {
            return $quotient;
        }

        // The exact quotient lies strictly between the truncated one and the next one away from zero, on the side of
        // its sign: 1 when both operands have the same sign, -1 otherwise. A denominator of 1 or -1 never leaves a
        // remainder, so comparing it with 0, 1 or -1 is the same here.
        $denominatorSign = $denominator <=> 0;
        $direction = ($numerator <=> 0) === $denominatorSign ? 1 : -1;
        $awayFromZero = $quotient + $direction;

        return match ($mode) {
            RoundingMode::TowardsZero => $quotient,
            RoundingMode::AwayFromZero => $awayFromZero,
            RoundingMode::PositiveInfinity => 1 === $direction ? $awayFromZero : $quotient,
            RoundingMode::NegativeInfinity => 1 === $direction ? $quotient : $awayFromZero,
            default => self::half($quotient, $awayFromZero, $remainder <=> abs($denominator) - $remainder, $mode),
        };
    }

    /**
     * The nearer of the two candidates, `$side` telling which (1 away from zero, -1 towards it); on an exact half,
     * `$side` 0, the one the mode names.
     */
    private static function half(int $towardsZero, int $awayFromZero, int $side, RoundingMode $mode): int
    {
        if (0 !== $side) {
            return 1 === $side ? $awayFromZero : $towardsZero;
        }

        return match ($mode) {
            RoundingMode::HalfAwayFromZero => $awayFromZero,
            RoundingMode::HalfTowardsZero => $towardsZero,
            RoundingMode::HalfEven => 0 === $towardsZero % 2 ? $towardsZero : $awayFromZero,
            default => 0 === $towardsZero % 2 ? $awayFromZero : $towardsZero,
        };
    }

    /**
     * Integer arithmetic that leaves the native range yields a float in PHP; an amount or a rate never does, and
     * never reaches `PHP_INT_MIN`, which has no positive counterpart.
     */
    private static function checked(float|int $result): int
    {
        if (!\is_int($result) || \PHP_INT_MIN === $result) {
            throw AmountOutOfRange::intermediate();
        }

        return $result;
    }
}
