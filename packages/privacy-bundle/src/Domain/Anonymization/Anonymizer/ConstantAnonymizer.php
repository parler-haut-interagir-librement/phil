<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Anonymization\Anonymizer;

use Override;
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizationContext;
use Phil\PrivacyBundle\Domain\Contract\Anonymizer;

/**
 * `constant` — the same value for every row, such as a known password hash for every account of a test copy.
 *
 * Option: `value` (default the empty string), written with its type.
 */
final readonly class ConstantAnonymizer implements Anonymizer
{
    #[Override]
    public function anonymize(string|int|float|bool $value, AnonymizationContext $context): string|int|float|bool|null
    {
        return $context->options->scalar('value', '');
    }
}
