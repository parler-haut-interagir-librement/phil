<?php

declare(strict_types=1);

namespace Phil\Address;

/**
 * A field of an address, and what is wrong with it.
 */
final readonly class AddressViolation
{
    public function __construct(
        public AddressField $field,
        public Violation $violation,
    ) {
    }
}
