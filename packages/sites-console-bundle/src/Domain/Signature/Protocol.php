<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Signature;

use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;

/**
 * The signed exchange between the console and an agent, version 1.
 *
 * The console signs each request with its key over the site it is meant for, the action, the date, a nonce and the
 * SHA-256 of the body — the agent accepts the current key of the console and, during a rotation, the next one; the agent signs its response with its own key over the site, the same nonce, the status and
 * the SHA-256 of its body. A request is therefore good for one site, one action and one moment, and a response for
 * one request.
 */
final readonly class Protocol
{
    public const string VERSION = 'phil-sites-console/1';

    public const string HEADER_SITE = 'Phil-Console-Site';

    public const string HEADER_DATE = 'Phil-Console-Date';

    public const string HEADER_NONCE = 'Phil-Console-Nonce';

    public const string HEADER_SIGNATURE = 'Phil-Console-Signature';

    public const string HEADER_AGENT_SIGNATURE = 'Phil-Agent-Signature';

    public const string ACTION_STATUS = 'status';

    public const string ACTION_CONFIGURATION = 'configuration';

    public const string ACTION_UPDATE = 'update';

    public const string ACTION_KEYS = 'keys';

    public const array ACTIONS = [self::ACTION_STATUS, self::ACTION_CONFIGURATION, self::ACTION_UPDATE, self::ACTION_KEYS];

    private const string NONCE_PATTERN = '/^[A-Za-z0-9_-]{22,64}$/D';

    /**
     * What the console signs.
     */
    public static function requestMessage(string $site, string $action, int $date, string $nonce, string $body): string
    {
        if (!\in_array($action, self::ACTIONS, true)) {
            throw InvalidValue::because(\sprintf('The action of a request is one of %s.', implode(', ', self::ACTIONS)));
        }

        return implode("\n", [self::VERSION.' request', $site, $action, (string) $date, $nonce, hash('sha256', $body)]);
    }

    /**
     * What the agent signs.
     */
    public static function responseMessage(string $site, string $nonce, int $status, string $body): string
    {
        return implode("\n", [self::VERSION.' response', $site, $nonce, (string) $status, hash('sha256', $body)]);
    }

    /**
     * A new nonce: 24 random bytes in base64url, 32 characters.
     */
    public static function nonce(): string
    {
        return Base64Url::encode(random_bytes(24));
    }

    public static function isNonce(string $nonce): bool
    {
        return 1 === preg_match(self::NONCE_PATTERN, $nonce);
    }
}
