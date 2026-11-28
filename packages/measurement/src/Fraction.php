<?php

declare(strict_types=1);

namespace Phil\Measurement;

use Phil\Measurement\Exception\InvalidMeasurement;
use Phil\Measurement\Exception\MeasurementOutOfRange;
use RoundingMode;

/**
 * A number kept as the quotient of two native integers, in lowest terms with a positive denominator.
 *
 * A conversion multiplies and divides by factors such as 0.45359237 or 5/9: as fractions, a chain of conversions loses
 * nothing, and the value is rounded once, when it is written. When the exact quotient of a sum or a product no longer
 * fits in native integers, that one result is rounded to 18 significant digits, or to 18 digits after the point for a
 * value below one, through integer arithmetic only, never through a float. A value whose integer part no longer fits
 * is refused, and so is `PHP_INT_MIN`, so that every value can be negated.
 *
 * @internal
 */
final readonly class Fraction
{
    /** The significant digits a decimal holds: 10^18 still fits in a native integer. */
    public const int DIGITS = 18;

    /** The base of the limbs of a product written out in full. */
    private const int LIMB = 1_000_000_000;

    private function __construct(
        public int $numerator,
        public int $denominator,
    ) {
    }

    public static function of(int $numerator, int $denominator = 1): self
    {
        if (0 === $denominator) {
            throw InvalidMeasurement::divisionByZero();
        }

        if (\PHP_INT_MIN === $numerator || \PHP_INT_MIN === $denominator) {
            throw MeasurementOutOfRange::intermediate();
        }

        if (0 > $denominator) {
            $numerator = -$numerator;
            $denominator = -$denominator;
        }

        $divisor = self::gcd(abs($numerator), $denominator);

        return new self(intdiv($numerator, $divisor), intdiv($denominator, $divisor));
    }

    /**
     * Reads a decimal (`12.5`, `-0.25`, `1200`) or the quotient of two (`5/9`, `-1/3`, `378.5411784/1.609344`), of 18
     * significant digits at most each.
     */
    public static function parse(string $text): self
    {
        $slash = strpos($text, '/');

        if (false !== $slash) {
            $denominator = substr($text, $slash + 1);

            return str_starts_with($denominator, '-')
                ? throw InvalidMeasurement::notADecimal($text) : self::decimal(substr($text, 0, $slash), $text)->divide(self::decimal($denominator, $text));
        }

        return self::decimal($text, $text);
    }

    private static function decimal(string $decimal, string $text): self
    {
        if (1 !== preg_match('/^(-?)(\d{1,64})(?:\.(\d{1,64}))?$/D', $decimal, $parts)) {
            throw InvalidMeasurement::notADecimal($text);
        }

        $fraction = rtrim($parts[3] ?? '', '0');

        if (self::DIGITS < \strlen($fraction)) {
            throw MeasurementOutOfRange::tooManyDigits($text);
        }

        return self::of(self::integer($parts[1], $parts[2].$fraction, $text), 10 ** \strlen($fraction));
    }

    public function add(self $other): self
    {
        $divisor = self::gcd($this->denominator, $other->denominator);
        $left = intdiv($other->denominator, $divisor);
        $right = intdiv($this->denominator, $divisor);
        $numerator = $this->product($this->numerator, $left, $other->numerator, $right);
        $denominator = $this->product($this->denominator, $left);

        return null === $numerator || null === $denominator
            ? $this->roundedSum($this, $other)
            : self::of($numerator, $denominator);
    }

    public function subtract(self $other): self
    {
        return $this->add($other->negate());
    }

    public function multiply(self $other): self
    {
        // Crossed reductions first, so that the products stay as small as the result allows.
        $first = self::gcd(abs($this->numerator), $other->denominator);
        $second = self::gcd(abs($other->numerator), $this->denominator);
        $numerator = $this->product(intdiv($this->numerator, $first), intdiv($other->numerator, $second));
        $denominator = $this->product(intdiv($this->denominator, $second), intdiv($other->denominator, $first));

        if (null === $numerator || null === $denominator) {
            return $this->roundedProduct($this, $other);
        }

        // Two fractions in lowest terms, reduced crosswise, give a product in lowest terms with a positive denominator;
        // zero is written 0/1.
        return 0 === $numerator ? new self(0, 1) : new self($numerator, $denominator);
    }

    public function divide(self $other): self
    {
        return $this->multiply($other->reciprocal());
    }

    public function reciprocal(): self
    {
        return self::of($this->denominator, $this->numerator);
    }

    public function negate(): self
    {
        return new self(-$this->numerator, $this->denominator);
    }

    public function abs(): self
    {
        return new self(abs($this->numerator), $this->denominator);
    }

    public function sign(): int
    {
        return $this->numerator <=> 0;
    }

    /**
     * -1, 0 or 1 as this number is below, equal to or above the other, without a product that could leave the range of
     * native integers: the integer parts are compared first, then the reciprocals of what remains.
     */
    public function compare(self $other): int
    {
        return self::compareFractions($this->numerator, $this->denominator, $other->numerator, $other->denominator);
    }

    /**
     * Whether the number is a decimal with an end, its denominator a product of 2s and 5s only.
     */
    public function isTerminating(): bool
    {
        return null !== $this->terminatingDigits();
    }

    /**
     * The decimal with exactly `$fractionDigits` digits after the point, rounded by `$mode`: `1/3` to 2 digits is
     * `0.33`, `-5/8` to 2 digits half-even is `-0.62`.
     */
    public function toDecimal(int $fractionDigits, RoundingMode $mode): string
    {
        if (0 > $fractionDigits) {
            throw InvalidMeasurement::negativeFractionDigits($fractionDigits);
        }

        $negative = 0 > $this->numerator;
        $magnitude = abs($this->numerator);
        $digits = (string) intdiv($magnitude, $this->denominator);
        $remainder = $magnitude % $this->denominator;

        for ($position = 0; $position < $fractionDigits; ++$position) {
            [$digit, $remainder] = $this->nextDigit($remainder);
            $digits .= $digit;
        }

        if (0 !== $remainder && $this->roundsAway($remainder, (int) $digits[-1], $negative, $mode)) {
            $digits = $this->incremented($digits);
        }

        $written = 0 === $fractionDigits ? $digits : substr($digits, 0, -$fractionDigits).'.'.substr($digits, -$fractionDigits);

        return $negative && '' !== trim($digits, '0') ? '-'.$written : $written;
    }

    /**
     * The number rounded to `$fractionDigits` digits after the point, 18 at most, as an exact number again.
     */
    public function round(int $fractionDigits, RoundingMode $mode): self
    {
        if (self::DIGITS < $fractionDigits) {
            throw MeasurementOutOfRange::intermediate();
        }

        $decimal = $this->toDecimal($fractionDigits, $mode);

        return $this->fromDigits(str_starts_with($decimal, '-'), str_replace(['-', '.'], '', $decimal), $fractionDigits);
    }

    /**
     * The decimal with no more fraction digits than needed: `5/4` is `1.25`, `1/3` rounded to 4 digits is `0.3333`.
     */
    public function toTrimmedDecimal(int $maximumFractionDigits, RoundingMode $mode): string
    {
        $decimal = $this->toDecimal($maximumFractionDigits, $mode);

        return str_contains($decimal, '.') ? rtrim(rtrim($decimal, '0'), '.') : $decimal;
    }

    /**
     * The decimal that reads back as this number when it has 18 significant digits or fewer; otherwise the nearest one
     * of 18 significant digits, half to even.
     */
    public function toCanonicalDecimal(): string
    {
        $integerDigits = \strlen(ltrim((string) intdiv(abs($this->numerator), $this->denominator), '0'));
        $available = self::DIGITS - $integerDigits;
        $exact = $this->terminatingDigits();

        return $this->toTrimmedDecimal(null !== $exact && $exact <= $available ? $exact : max(0, $available), RoundingMode::HalfEven);
    }

    /**
     * The number of fraction digits of the exact decimal, or `null` when the decimal never ends.
     */
    private function terminatingDigits(): ?int
    {
        $rest = $this->denominator;
        $twos = 0;
        $fives = 0;

        while (0 === $rest % 2) {
            $rest = intdiv($rest, 2);
            ++$twos;
        }

        while (0 === $rest % 5) {
            $rest = intdiv($rest, 5);
            ++$fives;
        }

        return 1 === $rest ? max($twos, $fives) : null;
    }

    /**
     * The next digit of the long division and its remainder, `remainder × 10` divided by the denominator, by repeated
     * additions that never leave `[0, denominator)`: the product itself could leave the range of native integers.
     *
     * @return array{int, int}
     */
    private function nextDigit(int $remainder): array
    {
        $digit = 0;
        $accumulated = 0;

        for ($times = 0; $times < 10; ++$times) {
            $room = $this->denominator - $remainder;

            if ($accumulated >= $room) {
                $accumulated -= $room;
                ++$digit;
            } else {
                $accumulated += $remainder;
            }
        }

        return [$digit, $accumulated];
    }

    /**
     * Whether the magnitude goes up by one unit of its last digit, given what remains of the division.
     */
    private function roundsAway(int $remainder, int $lastDigit, bool $negative, RoundingMode $mode): bool
    {
        $half = $remainder <=> $this->denominator - $remainder;

        return match ($mode) {
            RoundingMode::TowardsZero => false,
            RoundingMode::AwayFromZero => true,
            RoundingMode::PositiveInfinity => !$negative,
            RoundingMode::NegativeInfinity => $negative,
            RoundingMode::HalfAwayFromZero => 0 <= $half,
            RoundingMode::HalfTowardsZero => 0 < $half,
            RoundingMode::HalfEven => 0 < $half || (0 === $half && 1 === $lastDigit % 2),
            RoundingMode::HalfOdd => 0 < $half || (0 === $half && 0 === $lastDigit % 2),
        };
    }

    /**
     * A string of digits plus one, with its carry.
     */
    private function incremented(string $digits): string
    {
        for ($position = \strlen($digits) - 1; 0 <= $position; --$position) {
            if ('9' !== $digits[$position]) {
                $digits[$position] = (string) ((int) $digits[$position] + 1);

                return $digits;
            }

            $digits[$position] = '0';
        }

        return '1'.$digits;
    }

    private static function compareFractions(int $leftNumerator, int $leftDenominator, int $rightNumerator, int $rightDenominator): int
    {
        $leftInteger = self::floorDivision($leftNumerator, $leftDenominator);
        $rightInteger = self::floorDivision($rightNumerator, $rightDenominator);

        if ($leftInteger !== $rightInteger) {
            return $leftInteger <=> $rightInteger;
        }

        // What remains of each, in [0, 1): the product of the integer part and the denominator never exceeds the
        // numerator.
        $leftRemainder = $leftNumerator - $leftInteger * $leftDenominator;
        $rightRemainder = $rightNumerator - $rightInteger * $rightDenominator;

        if (0 === $leftRemainder || 0 === $rightRemainder) {
            return $leftRemainder <=> $rightRemainder;
        }

        // a/b < c/d exactly when d/c < b/a, for positive remainders.
        return self::compareFractions($rightDenominator, $rightRemainder, $leftDenominator, $leftRemainder);
    }

    private static function floorDivision(int $numerator, int $denominator): int
    {
        $quotient = intdiv($numerator, $denominator);

        return 0 > $numerator && 0 !== $numerator % $denominator ? $quotient - 1 : $quotient;
    }

    private static function gcd(int $left, int $right): int
    {
        while (0 !== $right) {
            [$left, $right] = [$right, $left % $right];
        }

        return 0 === $left ? 1 : $left;
    }

    private static function integer(string $sign, string $digits, string $text): int
    {
        $digits = ltrim($digits, '0');

        if (self::DIGITS < \strlen($digits)) {
            throw MeasurementOutOfRange::tooManyDigits($text);
        }

        $magnitude = (int) $digits;

        return '-' === $sign ? -$magnitude : $magnitude;
    }

    /**
     * `a × b`, or `a × b + c × d`, or `null` when the result leaves the range of native integers: integer arithmetic
     * that overflows yields a float in PHP, which the library never keeps.
     */
    private function product(int $a, int $b, int $c = 0, int $d = 0): ?int
    {
        $result = $this->native($a * $b);
        $other = $this->native($c * $d);

        return null === $result || null === $other ? null : $this->native($result + $other);
    }

    /**
     * The integer, or `null` for what overflowed into a float and for `PHP_INT_MIN`, which has no opposite.
     */
    private function native(float|int $value): ?int
    {
        return \is_int($value) && \PHP_INT_MIN !== $value ? $value : null;
    }

    /**
     * The sum of two numbers whose exact sum does not fit: each is read to 18 significant digits, as `m / 10^k`, the
     * finer one is rounded to the digits of the coarser one, and the sum is rounded as `rounded()` does.
     */
    private function roundedSum(self $left, self $right): self
    {
        [$leftDigits, $leftScale] = $left->scaled();
        [$rightDigits, $rightScale] = $right->scaled();
        $scale = min($leftScale, $rightScale);
        $sum = $this->product($this->shifted($leftDigits, $leftScale - $scale), 1, $this->shifted($rightDigits, $rightScale - $scale), 1);

        return null === $sum ? throw MeasurementOutOfRange::intermediate() : $this->rounded(0 > $sum, (string) abs($sum), $scale);
    }

    /**
     * The product of two numbers whose exact product does not fit: each is read to 18 significant digits, as
     * `m / 10^k`, and the product of the two integers, written out in full, is rounded as `rounded()` does.
     */
    private function roundedProduct(self $left, self $right): self
    {
        [$leftDigits, $leftScale] = $left->scaled();
        [$rightDigits, $rightScale] = $right->scaled();

        return $this->rounded($left->sign() !== $right->sign(), $this->longProduct(abs($leftDigits), abs($rightDigits)), $leftScale + $rightScale);
    }

    /**
     * The number `±digits / 10^scale` rounded half to even to 18 significant digits, and to 18 digits after the point
     * at most, so that its denominator fits.
     */
    private function rounded(bool $negative, string $digits, int $scale): self
    {
        $dropped = max(0, \strlen($digits) - self::DIGITS, $scale - self::DIGITS);
        $digits = str_pad($digits, $dropped + 1, '0', \STR_PAD_LEFT);
        $kept = substr($digits, 0, \strlen($digits) - $dropped);
        $rest = substr($digits, \strlen($kept));

        if ('' !== $rest && ('5' < $rest[0] || ('5' === $rest[0] && ('' !== trim(substr($rest, 1), '0') || 1 === (int) $kept[-1] % 2)))) {
            $kept = $this->incremented($kept);
        }

        return $this->fromDigits($negative, $kept, $scale - $dropped);
    }

    /**
     * `m / 10^places` rounded half to even to an integer; past 18 places, `m` is below a tenth of one and gives zero.
     */
    private function shifted(int $digits, int $places): int
    {
        return self::DIGITS < $places ? 0 : self::of($digits, 10 ** max(0, $places))->round(0, RoundingMode::HalfEven)->numerator;
    }

    /**
     * The number as `[m, k]`, `m / 10^k` rounded half to even to 18 significant digits; `m` fits in native integers,
     * `10^k` may not.
     *
     * @return array{int, int}
     */
    private function scaled(): array
    {
        $magnitude = $this->abs();
        $integerDigits = \strlen(ltrim((string) intdiv($magnitude->numerator, $magnitude->denominator), '0'));

        // Below one, the zeros after the point come before the significant digits: 1/den is 10^-19 at the least.
        $zeros = 0;

        if (0 === $integerDigits) {
            $shown = $magnitude->toDecimal(19, RoundingMode::TowardsZero);
            $zeros = strspn($shown, '0', 2);
        }

        $scale = max(0, self::DIGITS - $integerDigits) + $zeros;
        $digits = ltrim(str_replace('.', '', $magnitude->toDecimal($scale, RoundingMode::HalfEven)), '0');

        return [0 > $this->numerator ? -(int) $digits : (int) $digits, $scale];
    }

    /**
     * The digits of `a × b` for two non-negative native integers, by limbs of nine digits: every partial product and
     * every column stays within native integers.
     */
    private function longProduct(int $left, int $right): string
    {
        $leftLimbs = [$left % self::LIMB, intdiv($left, self::LIMB) % self::LIMB, intdiv($left, self::LIMB * self::LIMB)];
        $rightLimbs = [$right % self::LIMB, intdiv($right, self::LIMB) % self::LIMB, intdiv($right, self::LIMB * self::LIMB)];
        $columns = array_fill(0, 6, 0);

        foreach ($leftLimbs as $i => $leftLimb) {
            foreach ($rightLimbs as $j => $rightLimb) {
                $columns[$i + $j] += $leftLimb * $rightLimb;
            }
        }

        $digits = '';
        $carry = 0;

        foreach ($columns as $column) {
            $column += $carry;
            $digits = str_pad((string) ($column % self::LIMB), 9, '0', \STR_PAD_LEFT).$digits;
            $carry = intdiv($column, self::LIMB);
        }

        return ltrim($digits, '0');
    }

    /**
     * The number `±digits / 10^scale`, a scale below zero adding zeros, refused when it does not fit.
     */
    private function fromDigits(bool $negative, string $digits, int $scale): self
    {
        $digits = ltrim($digits, '0').str_repeat('0', max(0, -$scale));
        $maximum = (string) \PHP_INT_MAX;

        if (\strlen($digits) > \strlen($maximum) || (\strlen($digits) === \strlen($maximum) && 0 < strcmp($digits, $maximum))) {
            throw MeasurementOutOfRange::intermediate();
        }

        return self::of($negative ? -(int) $digits : (int) $digits, 10 ** max(0, $scale));
    }
}
