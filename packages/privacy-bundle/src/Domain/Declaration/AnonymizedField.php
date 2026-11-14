<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Declaration;

use Phil\PrivacyBundle\Domain\Attribute\Anonymize;

/**
 * One property declared by `#[Anonymize]`, as read from a class.
 */
final readonly class AnonymizedField
{
    /**
     * @param class-string $class the class that declares the property
     */
    public function __construct(
        public string $class,
        public string $property,
        public Anonymize $anonymize,
    ) {
    }
}
