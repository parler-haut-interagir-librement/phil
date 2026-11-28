<?php

declare(strict_types=1);

namespace Phil\BulkActions\Selection;

use Phil\BulkActions\Exception\SelectionUnavailable;
use SensitiveParameter;

/**
 * The secret a page carries between the selection and the confirmation, instead of the items: 32 random bytes in
 * base64url. The store keeps only its SHA-256 fingerprint.
 */
final readonly class SelectionToken
{
    private function __construct(
        #[SensitiveParameter]
        public string $value,
    ) {
    }

    public static function generate(): self
    {
        return new self(sodium_bin2base64(random_bytes(32), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING));
    }

    /**
     * Reads a token sent back by a page; one that cannot be a token is unavailable, like an unknown one.
     */
    public static function fromString(#[SensitiveParameter] string $value): self
    {
        if (1 !== preg_match('/^[A-Za-z0-9_-]{43}$/D', $value)) {
            throw SelectionUnavailable::create();
        }

        return new self($value);
    }

    /**
     * The key the store files the selection under: the token cannot be read back from it.
     */
    public function fingerprint(): string
    {
        return hash('sha256', $this->value);
    }
}
