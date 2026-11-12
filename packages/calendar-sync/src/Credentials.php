<?php

declare(strict_types=1);

namespace Phil\CalendarSync;

use LogicException;
use Phil\CalendarSync\Exception\InvalidSync;
use SensitiveParameter;

/**
 * What opens a calendar: an OAuth access token, a user name and an application password, or nothing for a public feed.
 *
 * It is handed to an adapter for one call and kept nowhere: it cannot be serialised — it never goes into a message, a
 * cache or a session —, and a dump of it shows its kind, never its secret.
 */
final readonly class Credentials
{
    private function __construct(
        public string $kind,
        #[SensitiveParameter]
        private string $user,
        #[SensitiveParameter]
        private string $secret,
    ) {
    }

    /**
     * Nothing: a public feed, or a feed whose address holds its secret.
     */
    public static function none(): self
    {
        return new self('none', '', '');
    }

    /**
     * An OAuth access token, sent as `Authorization: Bearer`.
     */
    public static function bearer(#[SensitiveParameter] string $token): self
    {
        if ('' === $token || 1 === preg_match('/[\x00-\x20\x7F]/', $token)) {
            throw InvalidSync::because('An access token is a non-empty string without spaces or control characters.');
        }

        return new self('bearer', '', $token);
    }

    /**
     * A user name and a password — an application password, for CalDAV —, sent as `Authorization: Basic`.
     */
    public static function basic(#[SensitiveParameter] string $user, #[SensitiveParameter] string $password): self
    {
        if ('' === $user || str_contains($user, ':') || 1 === preg_match('/[\x00-\x1F\x7F]/', $user.$password)) {
            throw InvalidSync::because('A user name is non-empty, without a colon; neither it nor the password holds a control character.');
        }

        return new self('basic', $user, $password);
    }

    /**
     * The options of Symfony's HttpClient that send them: `auth_bearer`, `auth_basic`, or none.
     *
     * @return array{auth_bearer?: string, auth_basic?: array{string, string}}
     */
    public function httpOptions(): array
    {
        return match ($this->kind) {
            'bearer' => ['auth_bearer' => $this->secret],
            'basic' => ['auth_basic' => [$this->user, $this->secret]],
            default => [],
        };
    }

    /**
     * @return array{kind: string}
     */
    public function __debugInfo(): array
    {
        return ['kind' => $this->kind];
    }

    public function __serialize(): never
    {
        throw new LogicException('Credentials are never serialised: a message carries the identifier of its connection.');
    }

    /**
     * @param array<mixed> $data
     */
    public function __unserialize(array $data): never
    {
        throw new LogicException('Credentials are never serialised: a message carries the identifier of its connection.');
    }
}
