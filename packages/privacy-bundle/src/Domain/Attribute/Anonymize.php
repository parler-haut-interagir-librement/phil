<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Attribute;

use Attribute;
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizerOptions;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * Says how a mapped property is anonymized when a copy of the database leaves production.
 *
 * ```php
 * #[PersonalData('email', retention: 'R-002')]
 * #[Anonymize('email', options: ['domain' => 'example.org'])]
 * private string $email;
 * ```
 *
 * `type` names an anonymizer, built in (`email`, `name`, `phone`, `address`, `date_shift`…) or registered by the
 * application with `#[AsAnonymizer]`; `options` are that anonymizer's, read when the database is anonymized.
 * Like `#[PersonalData]`, the declaration is metadata only: the model carries no service.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Anonymize
{
    /**
     * Lowercase ASCII letters, digits and underscores, starting with a letter: `email`, `date_shift`, `vat_number`.
     */
    public const string TYPE_PATTERN = '/^[a-z][a-z0-9_]*$/D';

    public AnonymizerOptions $options;

    /**
     * @param array<string, scalar|null> $options
     */
    public function __construct(
        public string $type,
        array $options = [],
    ) {
        if (1 !== preg_match(self::TYPE_PATTERN, $type)) {
            throw InvalidPrivacyValue::because(\sprintf('"%s" is not an anonymizer type: lowercase letters, digits and underscores, starting with a letter.', $type));
        }

        $this->options = new AnonymizerOptions($options);
    }
}
