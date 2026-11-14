<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Anonymization\Anonymizer;

use Override;
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizationContext;
use Phil\PrivacyBundle\Domain\Contract\Anonymizer;

/**
 * `email` — `user.3f9a…@example.com`: a local part derived from the original address under the run's salt, so a
 * unique column stays unique and an address repeated across tables still joins, at a domain reserved for examples.
 *
 * Options: `domain` (default `example.com`), `prefix` (default `user`).
 */
final readonly class EmailAnonymizer implements Anonymizer
{
    #[Override]
    public function anonymize(string|int|float|bool $value, AnonymizationContext $context): string
    {
        return \sprintf(
            '%s.%s@%s',
            $context->options->string('prefix', 'user'),
            substr($context->digest(mb_strtolower((string) $value)), 0, 16),
            $context->options->string('domain', 'example.com'),
        );
    }
}
