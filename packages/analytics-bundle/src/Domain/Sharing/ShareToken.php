<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Sharing;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use SensitiveParameter;

/**
 * The secret of a share link. It is shown once, when the link is made; only {@see self::digest()} is ever stored, so
 * that a reader of the database cannot open the views.
 */
final readonly class ShareToken
{
    private const int BYTES = 32;

    private function __construct(
        public string $value,
    ) {
    }

    public static function generate(): self
    {
        return new self(sodium_bin2base64(random_bytes(self::BYTES), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING));
    }

    /**
     * A token as a URL brought it: anything else is refused before any lookup.
     */
    public static function fromString(#[SensitiveParameter] string $value): self
    {
        if (1 !== preg_match('/^[A-Za-z0-9_-]{43}$/D', $value)) {
            throw InvalidValue::because('This is not a share token.');
        }

        return new self($value);
    }

    /**
     * What is stored and looked up. A plain hash is enough: the token holds 256 random bits.
     */
    public function digest(): string
    {
        return hash('sha256', $this->value);
    }

    /**
     * @return array{value: string}
     */
    public function __debugInfo(): array
    {
        return ['value' => '[redacted]'];
    }
}
