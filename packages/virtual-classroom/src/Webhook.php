<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom;

/**
 * A notification as it reached the application: its method, its headers with lowercase names, its query parameters,
 * its body, and the channel the application received it on — the address the room was opened with for the end of a
 * room (`ended`), for the signed notifications (`events`), or the one address of the provider's account (`account`).
 */
final readonly class Webhook
{
    public const string ENDED = 'ended';

    public const string EVENTS = 'events';

    public const string ACCOUNT = 'account';

    /** @var array<string, string> */
    public array $headers;

    /**
     * @param array<string, string>   $headers
     * @param array<array-key, mixed> $query
     */
    public function __construct(
        public string $method,
        array $headers = [],
        public array $query = [],
        public string $body = '',
        public string $channel = self::EVENTS,
    ) {
        $this->headers = array_change_key_case($headers, \CASE_LOWER);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
