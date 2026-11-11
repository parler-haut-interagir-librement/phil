<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\View;

use NumberFormatter;
use Phil\ReportingBundle\Domain\Format\MetricFormat;
use Phil\ReportingBundle\Domain\ValueObject\Metric;

/**
 * A metric, or a value of a series, written for a reader in a locale — `1 234`, `12,5 %`, `1 234,00 $` —, and its change from the period
 * before, as a direction and a percentage.
 */
final readonly class MetricReading
{
    public function format(Metric $metric, string $locale): string
    {
        $formatter = new NumberFormatter($locale, match ($metric->format) {
            MetricFormat::Number => NumberFormatter::DECIMAL,
            MetricFormat::Percent => NumberFormatter::PERCENT,
            MetricFormat::Currency => NumberFormatter::CURRENCY,
        });
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $metric->decimals);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $metric->decimals);

        return (string) (MetricFormat::Currency === $metric->format
            ? $formatter->formatCurrency($metric->value, $metric->currency)
            : $formatter->format($metric->value));
    }

    /**
     * A value of a series written in the locale with at most two decimals, or an empty text where there is none.
     */
    public function number(?float $value, string $locale): string
    {
        if (null === $value) {
            return '';
        }

        $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2);

        return (string) $formatter->format($value);
    }

    /**
     * The change from the previous figure, `up`, `down` or `flat`, with its percentage of the previous magnitude; no
     * percentage when the previous figure is zero.
     *
     * @return array{direction: 'down'|'flat'|'up', percent: string|null}
     */
    public function change(Metric $current, Metric $previous, string $locale): array
    {
        $difference = $current->value - $previous->value;
        $direction = match (true) {
            $difference > 0 => 'up',
            $difference < 0 => 'down',
            default => 'flat',
        };

        if (0.0 === $previous->value) {
            return ['direction' => $direction, 'percent' => null];
        }

        $formatter = new NumberFormatter($locale, NumberFormatter::PERCENT);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 1);

        return ['direction' => $direction, 'percent' => (string) $formatter->format(abs($difference / $previous->value))];
    }
}
