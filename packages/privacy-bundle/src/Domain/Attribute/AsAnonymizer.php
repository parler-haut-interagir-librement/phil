<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Attribute;

use Attribute;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * Registers an application's `Anonymizer` under a type that `#[Anonymize]` and the configuration can name.
 *
 * ```php
 * #[AsAnonymizer('vat_number')]
 * final class VatNumberAnonymizer implements Anonymizer { … }
 * ```
 *
 * A type the bundle already answers, such as `email`, is replaced by the application's anonymizer.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsAnonymizer
{
    public function __construct(
        public string $type,
    ) {
        if (1 !== preg_match(Anonymize::TYPE_PATTERN, $type)) {
            throw InvalidPrivacyValue::because(\sprintf('"%s" is not an anonymizer type: lowercase letters, digits and underscores, starting with a letter.', $type));
        }
    }
}
