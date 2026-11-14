<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Anonymization\Anonymizer;

use DateTimeImmutable;
use Override;
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizationContext;
use Phil\PrivacyBundle\Domain\Contract\Anonymizer;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * `date_shift` — the date moved by a random number of days, forward or back, written as it was read: a birth date
 * stays a plausible birth date, an order date keeps its season, and no date is the one that identified the person.
 *
 * Options: `days`, the largest shift (default 30), and `format`, the date's format when it is none of `Y-m-d`,
 * `Y-m-d H:i:s`, `Y-m-d H:i:s.u` and `Y-m-d\TH:i:sP`. The shift is never zero.
 */
final readonly class DateShiftAnonymizer implements Anonymizer
{
    private const array FORMATS = ['Y-m-d', 'Y-m-d H:i:s', 'Y-m-d H:i:s.u', 'Y-m-d\TH:i:sP'];

    #[Override]
    public function anonymize(string|int|float|bool $value, AnonymizationContext $context): string
    {
        $days = $context->options->int('days', 30);
        $text = (string) $value;

        foreach ($context->options->has('format') ? [$context->options->string('format')] : self::FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $text);

            if (false !== $date && $date->format($format) === $text) {
                $shift = $context->random->getInt(1, $days) * (0 === $context->random->getInt(0, 1) ? -1 : 1);

                return $date->modify(\sprintf('%+d days', $shift))->format($format);
            }
        }

        throw InvalidPrivacyValue::because(\sprintf('"%s" is not a date the date_shift anonymizer reads; give it a "format" option.', $text));
    }
}
