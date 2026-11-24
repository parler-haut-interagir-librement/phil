<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Replenishment;

use Phil\InventoryAlerts\Exception\InvalidInput;

/**
 * The share of replenishment cycles that should end without running out, from 50 % to 99.99 %, and the matching
 * number of standard deviations of the normal distribution.
 */
final readonly class ServiceLevel
{
    private const float LOW = 0.5;

    private const float HIGH = 0.9999;

    /** Where the rational approximation of the central region gives way to the one of the tail. */
    private const float TAIL = 0.97575;

    private function __construct(public float $probability)
    {
    }

    public static function of(float $probability): self
    {
        if (is_nan($probability) || $probability < self::LOW || $probability > self::HIGH) {
            throw InvalidInput::because('service level', 'must be from 0.5 to 0.9999');
        }

        return new self($probability);
    }

    /**
     * The number of standard deviations under which this share of a normal distribution falls (the quantile), within
     * about 1e-9 of the exact value: 1.6449 for 95 %, 2.3263 for 99 %.
     */
    public function z(): float
    {
        $p = $this->probability;

        if ($p > self::TAIL) {
            $q = sqrt(-2.0 * log(1.0 - $p));

            return -(((((-7.784894002430293e-03 * $q - 3.223964580411365e-01) * $q - 2.400758277161838e+00) * $q - 2.549732539343734e+00) * $q + 4.374664141464968e+00) * $q + 2.938163982698783e+00)
                / ((((7.784695709041462e-03 * $q + 3.224671290700398e-01) * $q + 2.445134137142996e+00) * $q + 3.754408661907416e+00) * $q + 1.0);
        }

        $q = $p - 0.5;
        $r = $q * $q;

        return (((((-3.969683028665376e+01 * $r + 2.209460984245205e+02) * $r - 2.759285104469687e+02) * $r + 1.383577518672690e+02) * $r - 3.066479806614716e+01) * $r + 2.506628277459239e+00) * $q
            / (((((-5.447609879822406e+01 * $r + 1.615858368580409e+02) * $r - 1.556989798598866e+02) * $r + 6.680131188771972e+01) * $r - 1.328068155288572e+01) * $r + 1.0);
    }
}
