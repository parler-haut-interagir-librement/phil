<?php

declare(strict_types=1);

namespace Phil\Idempotency;

use Phil\Idempotency\Exception\InvalidIdempotencyValue;

/**
 * The SHA-256 of what an operation was asked to do, as the store keeps it next to the key.
 *
 * **The fingerprint, not the payload.** It is enough to tell a replay from a reused key, and it holds nothing
 * personal the payload carried.
 *
 * For a request, it covers the method, the target and the raw body, byte for byte. Reusing a key on another
 * endpoint is a reuse, not a replay. A retry sends the same bytes, so it matches; a client that re-encodes the
 * body differently between attempts is refused, which is the safe answer when the server cannot tell whether
 * the change matters.
 */
final readonly class PayloadFingerprint
{
    private function __construct(
        public string $hex,
    ) {
    }

    /**
     * @param string $target the path and query the request was sent to, never the full URL with its origin
     */
    public static function ofRequest(string $method, string $target, string $body): self
    {
        return self::ofParts(strtoupper($method), $target, $body);
    }

    /**
     * For an operation that is not a request: a message class and its identifiers, the event type and the
     * fields of a webhook that matter, the submitted fields of a form. The parts are hashed in order.
     */
    public static function ofParts(string ...$parts): self
    {
        // Length-prefixed parts: no choice of parts collides with another by concatenation.
        $material = '';

        foreach ($parts as $part) {
            $material .= \strlen($part).':'.$part;
        }

        return new self(hash('sha256', $material));
    }

    /**
     * For a fingerprint read back from storage.
     */
    public static function fromHex(string $hex): self
    {
        if (1 !== preg_match('/^[0-9a-f]{64}$/D', $hex)) {
            throw InvalidIdempotencyValue::because('A payload fingerprint is 64 lowercase hexadecimal characters.');
        }

        return new self($hex);
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->hex, $other->hex);
    }
}
