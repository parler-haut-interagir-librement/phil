<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Anonymization\Anonymizer;

use Override;
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizationContext;
use Phil\PrivacyBundle\Domain\Contract\Anonymizer;

/**
 * `null` — the value removed. The column must accept null.
 */
final readonly class NullAnonymizer implements Anonymizer
{
    #[Override]
    public function anonymize(string|int|float|bool $value, AnonymizationContext $context): null
    {
        return null;
    }
}
