<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Domain\ValueObject;

use Phil\ReportingBundle\Domain\Exception\InvalidFigures;
use Phil\ReportingBundle\Domain\Format\MetricFormat;

/**
 * One figure measured over a period: its value, how it reads, the currency of an amount, and how many decimals it
 * shows.
 */
final readonly class Metric
{
    /**
     * @throws InvalidFigures when the value is not finite, an amount has no ISO 4217 currency, or the decimals are out of
     *                        0 to 6
     */
    public function __construct(
        public float $value,
        public MetricFormat $format = MetricFormat::Number,
        public string $currency = '',
        public int $decimals = 0,
    ) {
        if (!is_finite($value)) {
            throw InvalidFigures::because('a metric is a finite number');
        }

        if (MetricFormat::Currency === $format && 1 !== preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw InvalidFigures::because('an amount names its currency by its three capital letters');
        }

        if ($decimals < 0 || $decimals > 6) {
            throw InvalidFigures::because('a metric shows from 0 to 6 decimals');
        }
    }
}
