<?php

declare(strict_types=1);

namespace Phil\Idempotency;

use Phil\Idempotency\Exception\InvalidIdempotencyValue;

/**
 * Whose keys these are: the authenticated client of an API, the session of a form, the sender of a webhook, a
 * message bus.
 *
 * **Keys are never global.** In a shared namespace, one caller's key suppresses another caller's request,
 * and neither side sees an error. What identifies the caller is the application's decision.
 */
final readonly class IdempotencyScope
{
    public const int MAX_LENGTH = 255;

    private function __construct(
        public string $value,
    ) {
    }

    public static function of(string $value): self
    {
        if ('' === $value || \strlen($value) > self::MAX_LENGTH || 1 === preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw InvalidIdempotencyValue::because(\sprintf('An idempotency scope is 1 to %d characters long, without control characters.', self::MAX_LENGTH));
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
