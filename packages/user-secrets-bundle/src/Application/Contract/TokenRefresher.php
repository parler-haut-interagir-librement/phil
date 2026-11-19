<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Application\Contract;

use Phil\UserSecretsBundle\Domain\Credential\OAuthToken;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;

/**
 * Renews an expired OAuth token at the service that issued it, by its refresh token — usually a `refresh_token` grant
 * on the service's token endpoint. The application writes one per service, or one for several.
 */
interface TokenRefresher
{
    public function supports(ServiceName $service): bool;

    /**
     * @param OAuthToken $token the expired token; it has a refresh token
     *
     * @return OAuthToken the new token; without a refresh token, the old one is kept
     */
    public function refresh(ServiceName $service, OAuthToken $token): OAuthToken;
}
