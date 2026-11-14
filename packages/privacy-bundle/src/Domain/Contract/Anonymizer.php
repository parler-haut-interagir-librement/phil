<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Contract;

use Phil\PrivacyBundle\Domain\Anonymization\AnonymizationContext;

/**
 * Turns one stored value into a value that tells nothing about the person it belonged to.
 *
 * An anonymizer is given every non-null value of its column, one row at a time, with the column's options, the
 * run's random source and the locale pack. A null value is left null without asking it: there is nothing personal
 * in it. Throw `InvalidPrivacyValue` for an option or a value the anonymizer cannot read; the run is a transaction,
 * and nothing is written.
 */
interface Anonymizer
{
    public function anonymize(string|int|float|bool $value, AnonymizationContext $context): string|int|float|bool|null;
}
