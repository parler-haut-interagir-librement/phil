<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Attribute;

use Attribute;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Retention\RetentionRule;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;

/**
 * Declares a property as personal information.
 *
 * ```php
 * #[PersonalData('email', retention: 'R-002')]
 * private string $address;
 * ```
 *
 * The declaration is metadata and nothing else: no container, no I/O, nothing to load. That is what lets a
 * domain model carry it, the same way it carries mapping attributes. What reads it — an inventory test that
 * every declared field has a retention rule, an export, a scrubber — lives outside the model.
 *
 * **`retention` names a rule of the retention schedule by its identifier.** A field declared without one is a
 * field the schedule does not cover yet, and `DeclaredPersonalFields` reports it as such rather than guessing.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class PersonalData
{
    public DataCategory $category;

    public function __construct(
        string $category,
        public ?string $retention = null,
    ) {
        $this->category = DataCategory::fromString($category);

        if (null !== $retention && 1 !== preg_match(RetentionRule::ID_PATTERN, $retention)) {
            throw InvalidPrivacyValue::because(\sprintf('"%s" is not a retention rule identifier.', $retention));
        }
    }
}
