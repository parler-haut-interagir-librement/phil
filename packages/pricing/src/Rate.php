<?php

declare(strict_types=1);

namespace Phil\Pricing;

use Phil\Pricing\Exception\InvalidRate;

/**
 * A rate in parts per million: `1_000_000` is 100 %, `350_000` is 35 %, `99_750` is 9.975 %.
 *
 * **Parts per million, never a float.** A margin, a markup or a discount is an exact integer, as the taxes of
 * `phil/tax` are. A rate may be negative — a sale below cost has a negative margin — or above 100 % — a markup of
 * 150 % on the cost; where a rate has no meaning, the operation that takes it refuses it.
 */
final readonly class Rate
{
    public const int ONE_MILLION = 1_000_000;

    private function __construct(
        public int $partsPerMillion,
    ) {
    }

    public static function of(int $partsPerMillion): self
    {
        if (\PHP_INT_MIN === $partsPerMillion) {
            throw InvalidRate::outOfRange();
        }

        return new self($partsPerMillion);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * A rate in basis points, the hundredths of a percent: `125` is 1.25 %.
     */
    public static function ofBasisPoints(int $basisPoints): self
    {
        return self::of(IntegerMath::multiply($basisPoints, 100));
    }

    /**
     * A rate from its percentage as a decimal string: `"35"`, `"9.975"`, `"-12.5"`. Up to four decimals, the
     * precision of a part per million; never a float.
     */
    public static function ofPercent(string $percent): self
    {
        if (1 !== preg_match('/^(-?)(\d{1,12})(?:\.(\d{1,4}))?$/D', $percent, $matches)) {
            throw InvalidRate::notAPercent($percent);
        }

        $magnitude = (int) ($matches[2].str_pad($matches[3] ?? '', 4, '0'));

        return new self('-' === $matches[1] ? -$magnitude : $magnitude);
    }

    /**
     * The percentage as a decimal string without trailing zeros: `"35"`, `"9.975"`, `"-12.5"`.
     */
    public function percent(): string
    {
        $magnitude = abs($this->partsPerMillion);
        $fraction = rtrim(str_pad((string) ($magnitude % 10_000), 4, '0', \STR_PAD_LEFT), '0');
        $sign = $this->partsPerMillion < 0 ? '-' : '';

        return $sign.intdiv($magnitude, 10_000).('' === $fraction ? '' : '.'.$fraction);
    }

    public function isNegative(): bool
    {
        return $this->partsPerMillion < 0;
    }

    public function equals(self $other): bool
    {
        return $this->partsPerMillion === $other->partsPerMillion;
    }

    /**
     * -1, 0 or 1, as `<=>`.
     */
    public function compare(self $other): int
    {
        return $this->partsPerMillion <=> $other->partsPerMillion;
    }
}
