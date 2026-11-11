<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Domain\ValueObject;

use Phil\ReportingBundle\Domain\Exception\InvalidFigures;

/**
 * The series a chart or a table shows over the same labels — days, months, categories —, checked against limits so
 * that a source's figures cost no more to draw than a large honest chart.
 */
final readonly class SeriesSet
{
    public const int MAX_LABELS = 500;

    public const int MAX_SERIES = 24;

    public const int MAX_TEXT = 200;

    /**
     * @param list<string> $labels
     * @param list<Series> $series
     *
     * @throws InvalidFigures when there are more labels or series than the limits, a series has not one value per label,
     *                        or a label is longer than 200 characters
     */
    public function __construct(
        public array $labels,
        public array $series,
    ) {
        if (\count($labels) > self::MAX_LABELS) {
            throw InvalidFigures::because(\sprintf('more than %d labels', self::MAX_LABELS));
        }

        if (\count($series) > self::MAX_SERIES) {
            throw InvalidFigures::because(\sprintf('more than %d series', self::MAX_SERIES));
        }

        $texts = $labels;

        foreach ($series as $one) {
            if (\count($one->values) !== \count($labels)) {
                throw InvalidFigures::because(\sprintf('the series "%s" has %d values for %d labels', $one->label, \count($one->values), \count($labels)));
            }

            $texts[] = $one->label;
        }

        foreach ($texts as $label) {
            if (mb_strlen($label) > self::MAX_TEXT) {
                throw InvalidFigures::because(\sprintf('a label is longer than %d characters', self::MAX_TEXT));
            }
        }
    }

    /**
     * The largest magnitude among the values, or zero when there is none: the full length of a bar.
     */
    public function largest(): float
    {
        $largest = 0.0;

        foreach ($this->series as $series) {
            foreach ($series->values as $value) {
                $largest = max($largest, abs($value ?? 0.0));
            }
        }

        return $largest;
    }
}
