<?php

declare(strict_types=1);

namespace Phil\Idempotency;

use Phil\Idempotency\Exception\InvalidIdempotencyValue;

/**
 * The answer of the first run, returned again when the same key arrives with the same payload.
 *
 * Only what the replay needs: a status, the headers the application chooses to replay (a `Location`, a
 * `Content-Type`), and the body. An operation that is not a request keeps `204` and an empty body, or what its
 * caller reads back. What the stored body holds, and how long it is kept, is listed in the application's
 * retention schedule like any other store.
 */
final readonly class StoredResponse
{
    /**
     * @param array<string, list<string>> $headers
     */
    private function __construct(
        public int $status,
        public array $headers,
        public string $body,
    ) {
    }

    /**
     * @param array<string, list<string>> $headers
     */
    public static function of(int $status, array $headers = [], string $body = ''): self
    {
        if ($status < 100 || $status > 599) {
            throw InvalidIdempotencyValue::because(\sprintf('A status is between 100 and 599; %d is not.', $status));
        }

        return new self($status, $headers, $body);
    }

    /**
     * The answer of an operation that returns nothing: `204`, no header, no body.
     */
    public static function nothing(): self
    {
        return new self(204, [], '');
    }
}
