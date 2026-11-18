<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Http;

use JsonException;

/**
 * The body of a commit, read: the attempt token, the CSRF token, the values by element and whether the session ends.
 * A body that is not a JSON object of these, with values that are text or numbers, reads as nothing.
 */
final readonly class ScormCommitPayload
{
    /**
     * @param array<array-key, string|int|float> $values
     */
    private function __construct(
        public string $token,
        public string $csrf,
        public array $values,
        public bool $finish,
    ) {
    }

    public static function fromJson(string $json): ?self
    {
        try {
            $body = json_decode($json, true, 4, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            return null;
        }

        if (!\is_array($body) || !\is_string($body['token'] ?? null) || !\is_string($body['csrf'] ?? null) || !\is_bool($body['finish'] ?? false)) {
            return null;
        }

        $values = $body['values'] ?? [];

        if (!\is_array($values)) {
            return null;
        }

        $kept = [];

        foreach ($values as $element => $value) {
            if (!\is_string($value) && !\is_int($value) && !\is_float($value)) {
                return null;
            }

            $kept[$element] = $value;
        }

        return new self($body['token'], $body['csrf'], $kept, $body['finish'] ?? false);
    }
}
