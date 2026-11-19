<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Domain\Credential;

use Phil\UserSecretsBundle\Domain\Contract\Credential;
use Phil\UserSecretsBundle\Domain\Exception\InvalidSecret;

/**
 * The kinds of credential, as the store names them.
 */
enum CredentialKind: string
{
    case ApiKey = 'api_key';
    case OAuthToken = 'oauth_token';

    /**
     * The credential of this kind that the payload holds.
     *
     * @param array<array-key, mixed> $payload
     *
     * @throws InvalidSecret when the payload is not one of this kind
     */
    public function credential(array $payload): Credential
    {
        return match ($this) {
            self::ApiKey => ApiKey::fromPayload($payload),
            self::OAuthToken => OAuthToken::fromPayload($payload),
        };
    }
}
