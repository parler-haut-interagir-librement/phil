<?php

declare(strict_types=1);

namespace Phil\Idempotency;

use Phil\Idempotency\Exception\InvalidIdempotencyValue;
use Phil\Idempotency\Exception\MissingIdempotencyKey;
use Symfony\Component\Uid\Uuid;

/**
 * The key of one intention: the value of an `Idempotency-Key` header, the identifier a message carries, the
 * token of a form.
 *
 * **On the client, one key per intention, kept from one retry to the next.** A key generated per attempt
 * makes every retry a new request, which is the duplicate the key exists to prevent. Generate it where the
 * intention is born and carry it in the message or job that retries.
 *
 * The header is specified by the IETF draft `draft-ietf-httpapi-idempotency-key-header`, which writes the value
 * as a structured-field string (quoted); many clients send it bare. This reader accepts both and this writer
 * sends the quoted form.
 */
final readonly class IdempotencyKey
{
    public const string HEADER = 'Idempotency-Key';

    public const int MAX_LENGTH = 255;

    private function __construct(
        public string $value,
    ) {
    }

    /**
     * A random UUIDv4: 122 bits from a CSPRNG, so that two intentions never share a key by accident.
     */
    public static function generate(): self
    {
        return new self(Uuid::v4()->toRfc4122());
    }

    /**
     * For a key kept by the client between retries, carried by a message, or read from storage.
     */
    public static function fromString(string $value): self
    {
        if ('' === $value) {
            throw InvalidIdempotencyValue::because('An idempotency key is not empty.');
        }

        if (\strlen($value) > self::MAX_LENGTH) {
            throw InvalidIdempotencyValue::because(\sprintf('An idempotency key is at most %d characters long; this one is %d.', self::MAX_LENGTH, \strlen($value)));
        }

        // Printable ASCII without `"` or `\`: what a structured-field string holds without escapes, and what
        // a bare header value holds without ambiguity.
        if (1 !== preg_match('/^[\x20\x21\x23-\x5B\x5D-\x7E]+$/D', $value) || trim($value) !== $value) {
            throw InvalidIdempotencyValue::because('An idempotency key holds printable ASCII only, without quotes, backslashes or surrounding spaces.');
        }

        return new self($value);
    }

    /**
     * Reads the header on the server. A missing or blank header is the `400` of the contract.
     */
    public static function fromHeader(?string $header): self
    {
        $value = null === $header ? '' : trim($header);

        if ('' === $value) {
            throw MissingIdempotencyKey::onRequest();
        }

        if (\strlen($value) >= 2 && str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = substr($value, 1, -1);
        }

        return self::fromString($value);
    }

    public function toHeaderValue(): string
    {
        return '"'.$this->value.'"';
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
