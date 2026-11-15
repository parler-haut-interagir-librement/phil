<?php

declare(strict_types=1);

namespace Phil\Lti\Platform;

use Phil\Lti\Exception\InvalidRequest;

/**
 * The OpenID Connect authentication request a tool sends the browser back with, to the platform's authorization
 * endpoint: what LTI fixes — `scope=openid`, `response_type=id_token`, `response_mode=form_post`, `prompt=none` — and
 * what it carries — the client, the redirect address, the hints, the `state` and the `nonce`.
 */
final readonly class AuthenticationRequest
{
    public function __construct(
        public string $clientId,
        public string $redirectUri,
        public string $loginHint,
        public string $nonce,
        public ?string $state = null,
        public ?string $messageHint = null,
    ) {
    }

    /**
     * @param array<array-key, mixed> $parameters the query or the body of the request
     *
     * @throws InvalidRequest
     */
    public static function fromParameters(array $parameters): self
    {
        $fixed = ['scope' => 'openid', 'response_type' => 'id_token', 'response_mode' => 'form_post', 'prompt' => 'none'];

        foreach ($fixed as $name => $value) {
            $given = self::read($parameters, $name, 64);

            if ('scope' === $name ? !\in_array($value, explode(' ', (string) $given), true) : $given !== $value) {
                throw InvalidRequest::because(\sprintf('An authentication request of LTI has "%s=%s".', $name, $value));
            }
        }

        return new self(
            self::read($parameters, 'client_id', 255) ?? throw InvalidRequest::because('An authentication request names the client.'),
            self::read($parameters, 'redirect_uri', 2048) ?? throw InvalidRequest::because('An authentication request names the redirect address.'),
            self::read($parameters, 'login_hint', 2048) ?? throw InvalidRequest::because('An authentication request carries the login hint.'),
            self::read($parameters, 'nonce', 255) ?? throw InvalidRequest::because('An authentication request carries a nonce.'),
            self::read($parameters, 'state', 2048),
            self::read($parameters, 'lti_message_hint', 4096),
        );
    }

    /**
     * @param array<array-key, mixed> $parameters
     */
    private static function read(array $parameters, string $name, int $max): ?string
    {
        $value = $parameters[$name] ?? null;

        if (null === $value || '' === $value) {
            return null;
        }

        if (!\is_string($value) || \strlen($value) > $max || !mb_check_encoding($value, 'UTF-8')) {
            throw InvalidRequest::because(\sprintf('The parameter "%s" is a string of %d bytes at most.', $name, $max));
        }

        return $value;
    }
}
