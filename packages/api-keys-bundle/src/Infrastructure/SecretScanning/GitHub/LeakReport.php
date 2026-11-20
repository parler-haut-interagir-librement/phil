<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Infrastructure\SecretScanning\GitHub;

use JsonException;
use Phil\ApiKeysBundle\Infrastructure\SecretScanning\Exception\UnreadableReport;
use SensitiveParameter;

/**
 * The body GitHub's secret scanning sends to a partner's endpoint, read within bounds: a JSON list of matches, each
 * the token found, the type of the pattern that found it, and where. Only the token and the type are kept; an entry
 * missing either, or holding something else, makes the whole body unreadable.
 */
final readonly class LeakReport
{
    /** The longest body read. */
    public const int MAX_BYTES = 1_048_576;

    /** The most matches one body holds. */
    public const int MAX_MATCHES = 1_000;

    /** The longest token read: longer than any token of this bundle, short enough to bound the work. */
    public const int MAX_TOKEN_LENGTH = 512;

    /** The longest type of pattern. */
    public const int MAX_TYPE_LENGTH = 256;

    /**
     * @param list<array{token: string, type: string}> $matches
     */
    private function __construct(
        #[SensitiveParameter]
        public array $matches,
    ) {
    }

    /**
     * @throws UnreadableReport when the body is not such a list, or passes a bound
     */
    public static function fromJson(#[SensitiveParameter] string $json): self
    {
        if (\strlen($json) > self::MAX_BYTES) {
            throw UnreadableReport::because(\sprintf('A report of leaked tokens is %d bytes at most.', self::MAX_BYTES));
        }

        try {
            $entries = json_decode($json, true, 3, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw UnreadableReport::because('A report of leaked tokens is JSON.', $jsonException);
        }

        if (!\is_array($entries) || !array_is_list($entries)) {
            throw UnreadableReport::because('A report of leaked tokens is a list.');
        }

        if (\count($entries) > self::MAX_MATCHES) {
            throw UnreadableReport::because(\sprintf('A report of leaked tokens holds %d matches at most.', self::MAX_MATCHES));
        }

        $matches = [];

        foreach ($entries as $entry) {
            $token = \is_array($entry) ? $entry['token'] ?? null : null;
            $type = \is_array($entry) ? $entry['type'] ?? null : null;

            if (!\is_string($token) || '' === $token || \strlen($token) > self::MAX_TOKEN_LENGTH || !\is_string($type) || \strlen($type) > self::MAX_TYPE_LENGTH) {
                throw UnreadableReport::because('Each match of a report of leaked tokens holds a token and a type, within bounds.');
            }

            $matches[] = ['token' => $token, 'type' => $type];
        }

        return new self($matches);
    }
}
