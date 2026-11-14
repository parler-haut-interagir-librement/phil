<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Fixture;

use Phil\PrivacyBundle\Domain\Anonymization\AnonymizationContext;
use Phil\PrivacyBundle\Domain\Attribute\AsAnonymizer;
use Phil\PrivacyBundle\Domain\Contract\Anonymizer;

/**
 * An application's anonymizer, registered by its attribute.
 */
#[AsAnonymizer('vat_number')]
final class VatNumberAnonymizer implements Anonymizer
{
    public function anonymize(string|int|float|bool $value, AnonymizationContext $context): string
    {
        return $context->fill('VAT-####');
    }
}
