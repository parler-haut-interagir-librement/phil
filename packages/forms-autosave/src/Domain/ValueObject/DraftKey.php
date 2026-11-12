<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Domain\ValueObject;

use Phil\FormsAutosave\Domain\Exception\InvalidDraft;

/**
 * The name a draft is kept under, in the browser and on the server: an opaque string of URL-safe characters, derived
 * from the form, its subject and its user by a keyed hash, so that it tells nothing of them and can travel in a URL.
 */
final readonly class DraftKey
{
    /** The characters and lengths a key may have. */
    public const string PATTERN = '/^[A-Za-z0-9_-]{16,64}$/D';

    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidDraft when the value is not 16 to 64 URL-safe characters
     */
    public static function of(string $value): self
    {
        if (1 !== preg_match(self::PATTERN, $value)) {
            throw InvalidDraft::because('A draft key is 16 to 64 letters, digits, "-" or "_".');
        }

        return new self($value);
    }
}
