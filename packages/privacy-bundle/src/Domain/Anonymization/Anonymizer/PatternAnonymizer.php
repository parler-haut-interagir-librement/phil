<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Anonymization\Anonymizer;

use Override;
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizationContext;
use Phil\PrivacyBundle\Domain\Contract\Anonymizer;

/**
 * `pattern` — a value of a known shape, such as a membership number `M-####-??`: `#` a digit, `?` an uppercase
 * letter, `*` either, `\` writes the next character as it is.
 *
 * Option: `pattern`, required.
 */
final readonly class PatternAnonymizer implements Anonymizer
{
    #[Override]
    public function anonymize(string|int|float|bool $value, AnonymizationContext $context): string
    {
        return $context->fill($context->options->string('pattern'));
    }
}
