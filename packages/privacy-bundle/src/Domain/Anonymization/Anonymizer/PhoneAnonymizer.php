<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Anonymization\Anonymizer;

use Override;
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizationContext;
use Phil\PrivacyBundle\Domain\Contract\Anonymizer;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * `phone` — a number in one of the locale pack's fictional ranges, such as `514 555-0142`.
 *
 * Option: `pattern`, which replaces the pack's ranges (`#` a digit, `?` a letter, `\` an escape). A pack without a
 * fictional range requires it.
 */
final readonly class PhoneAnonymizer implements Anonymizer
{
    #[Override]
    public function anonymize(string|int|float|bool $value, AnonymizationContext $context): string
    {
        if ($context->options->has('pattern')) {
            return $context->fill($context->options->string('pattern'));
        }

        $phones = $context->locale->phones;

        if ([] === $phones) {
            throw InvalidPrivacyValue::because(\sprintf('The locale pack "%s" has no fictional telephone range: give the phone anonymizer a "pattern" option.', $context->locale->code));
        }

        return $context->fill($context->pick($phones));
    }
}
