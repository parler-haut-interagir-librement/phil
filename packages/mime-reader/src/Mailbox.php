<?php

declare(strict_types=1);

namespace Phil\MimeReader;

/**
 * One address of an address field as it is written, with its display name decoded to UTF-8. The address is not
 * validated: a reader of hostile mail checks it with its own rules before using it.
 */
final readonly class Mailbox
{
    public function __construct(
        public string $address,
        public ?string $name = null,
    ) {
    }

    /**
     * The domain after the last `@`, in lowercase, or `null` when the address has none.
     */
    public function domain(): ?string
    {
        $at = strrpos($this->address, '@');

        return false === $at || $at === \strlen($this->address) - 1 ? null : strtolower(substr($this->address, $at + 1));
    }
}
