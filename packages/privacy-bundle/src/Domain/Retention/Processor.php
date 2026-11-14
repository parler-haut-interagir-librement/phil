<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Retention;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * A service provider that handles a store's records for the business — a host, a mail service, a payment provider — and
 * where it keeps them. The register names it so that a transfer outside the business's jurisdiction is visible.
 */
final readonly class Processor
{
    public const int MAX_LENGTH = 200;

    public function __construct(
        public string $name,
        public string $location,
    ) {
        foreach (['name' => $name, 'location' => $location] as $field => $value) {
            if ('' === trim($value) || mb_strlen($value) > self::MAX_LENGTH || 1 === preg_match('/[\x00-\x1F\x7F]/', $value)) {
                throw InvalidPrivacyValue::because(\sprintf("A processor's %s is one line of at most %d characters.", $field, self::MAX_LENGTH));
            }
        }
    }

    public function toString(): string
    {
        return $this->name.' ('.$this->location.')';
    }
}
