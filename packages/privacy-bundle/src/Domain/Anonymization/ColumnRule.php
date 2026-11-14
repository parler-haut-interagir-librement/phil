<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Anonymization;

use Phil\PrivacyBundle\Domain\Attribute\Anonymize;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * One column to anonymize: its table, its name, the anonymizer type and options, and where the rule comes from — a
 * property such as `App\Entity\Member::$email`, a personal data category, or the configuration — for the plan a
 * dry run prints.
 */
final readonly class ColumnRule
{
    /** @var non-empty-string */
    public string $table;

    /** @var non-empty-string */
    public string $column;

    public function __construct(
        string $table,
        string $column,
        public string $type,
        public AnonymizerOptions $options,
        public string $origin,
    ) {
        if ('' === $table || '' === $column) {
            throw InvalidPrivacyValue::because('An anonymized column names its table and itself.');
        }

        $this->table = $table;
        $this->column = $column;

        if (1 !== preg_match(Anonymize::TYPE_PATTERN, $type)) {
            throw InvalidPrivacyValue::because(\sprintf('"%s" is not an anonymizer type: lowercase letters, digits and underscores, starting with a letter.', $type));
        }
    }
}
