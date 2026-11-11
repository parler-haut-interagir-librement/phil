<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Domain\ValueObject;

use Phil\ReportingBundle\Domain\Exception\InvalidFigures;

/**
 * One series of a chart: its name, and a value for each label of its set, `null` where it has none.
 */
final readonly class Series
{
    /**
     * @param list<float|null> $values
     *
     * @throws InvalidFigures when a value is not finite
     */
    public function __construct(
        public string $label,
        public array $values,
    ) {
        foreach ($values as $value) {
            if (null !== $value && !is_finite($value)) {
                throw InvalidFigures::because(\sprintf('the series "%s" holds a value that is not finite', $label));
            }
        }
    }
}
