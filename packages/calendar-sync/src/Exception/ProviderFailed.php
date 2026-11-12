<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Exception;

use RuntimeException;

/**
 * A calendar service did not do what was asked: it refused the credentials, answered with an error, or did not answer.
 * The message names the adapter and the status, never an address, a token or a body: the address of a feed can be its
 * secret.
 */
final class ProviderFailed extends RuntimeException implements CalendarSyncException
{
    /**
     * Built by its named constructors, which word the message.
     */
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly ?int $status = null,
    ) {
        parent::__construct($message);
    }

    /**
     * A `401` or a `403`: the credentials are refused, or no longer valid — a token to refresh, an access withdrawn.
     */
    public static function unauthorized(string $provider, int $status): self
    {
        return new self(\sprintf('The calendar service "%s" refused the credentials (HTTP %d).', $provider, $status), $provider, $status);
    }

    public static function status(string $provider, int $status): self
    {
        return new self(\sprintf('The calendar service "%s" answered HTTP %d.', $provider, $status), $provider, $status);
    }

    /**
     * An answer that refuses what was asked: the code of its reason, kept only when it is a short word, as `notFound`.
     */
    public static function refused(string $provider, string $reason): self
    {
        $code = 1 === preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,63}$/D', $reason) ? $reason : 'unknown';

        return new self(\sprintf('The calendar service "%s" refused the request: %s.', $provider, $code), $provider);
    }

    /**
     * No answer: the cause is not chained, as its message can hold the address read.
     */
    public static function unreachable(string $provider): self
    {
        return new self(\sprintf('The calendar service "%s" could not be read.', $provider), $provider);
    }

    /**
     * Whether the credentials were refused, which a new token may mend.
     */
    public function isUnauthorized(): bool
    {
        return 401 === $this->status || 403 === $this->status;
    }
}
