<?php

declare(strict_types=1);

namespace Phil\SvgChart\Internal;

/**
 * A value axis with round ticks: the step is 1, 2 or 5 times a power of ten, a quarter of the values' span made round,
 * so that about five ticks cover the values, and the axis runs from a multiple of the step at or below the lowest value to one at or above the highest.
 *
 * @internal
 */
final readonly class Scale
{
    /**
     * @param non-empty-list<float> $ticks
     */
    private function __construct(
        public float $min,
        public float $max,
        public float $step,
        public array $ticks,
    ) {
    }

    public static function covering(float $low, float $high): self
    {
        if ($high <= $low) {
            $high = $low + 1.0;
        }

        $step = self::nice(self::nice($high - $low, false) * 0.25, true);
        $min = floor($low / $step) * $step;
        $max = ceil($high / $step) * $step;
        $values = [self::clean($min)];

        for ($i = 1; $i <= round(($max - $min) / $step); ++$i) {
            $values[] = self::clean($min + $i * $step);
        }

        return new self(self::clean($min), self::clean($max), $step, $values);
    }

    /**
     * The position of a value between the two ends of the axis, as a fraction: 0 at `min`, 1 at `max`.
     */
    public function fraction(float $value): float
    {
        return ($value - $this->min) / ($this->max - $this->min);
    }

    /**
     * A tick value written with as many decimals as the step needs.
     */
    public function label(float $value): string
    {
        return number_format($value, max(0, (int) -floor(log10($this->step))), '.', '');
    }

    /**
     * 1, 2, 5 or 10 times the power of ten of a positive value: the nearest when rounding, else the next at or above.
     */
    public static function nice(float $value, bool $nearest): float
    {
        $power = 10 ** floor(log10($value));
        $fraction = $value / $power;

        $nice = $nearest
            ? ($fraction < 1.5 ? 1 : ($fraction < 3 ? 2 : ($fraction < 7 ? 5 : 10)))
            : ($fraction <= 1 ? 1 : ($fraction <= 2 ? 2 : ($fraction <= 5 ? 5 : 10)));

        return $nice * $power;
    }

    /**
     * The value without the error of the floating point steps: 0.30000000000000004 is 0.3.
     */
    private static function clean(float $value): float
    {
        return round($value, 10) + 0.0;
    }
}
