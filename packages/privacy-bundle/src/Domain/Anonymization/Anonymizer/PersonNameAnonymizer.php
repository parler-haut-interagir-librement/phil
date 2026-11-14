<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Anonymization\Anonymizer;

use Override;
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizationContext;
use Phil\PrivacyBundle\Domain\Anonymization\NamePart;
use Phil\PrivacyBundle\Domain\Contract\Anonymizer;

/**
 * `firstname`, `lastname` and `name` — a given name, a family name, or both, drawn from the locale pack.
 */
final readonly class PersonNameAnonymizer implements Anonymizer
{
    public function __construct(
        private NamePart $part,
    ) {
    }

    #[Override]
    public function anonymize(string|int|float|bool $value, AnonymizationContext $context): string
    {
        return match ($this->part) {
            NamePart::Given => $context->pick($context->locale->givenNames),
            NamePart::Family => $context->pick($context->locale->familyNames),
            NamePart::Full => $context->pick($context->locale->givenNames).' '.$context->pick($context->locale->familyNames),
        };
    }
}
