<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Application\Contract;

use Phil\UserSecretsBundle\Domain\Credential\OAuthToken;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;
use Throwable;

/**
 * Revokes an OAuth token at the service that issued it — usually a request to the service's revocation endpoint, as
 * RFC 7009 describes it, with the refresh token when there is one, since revoking it also ends the access tokens it
 * issued. The application writes one per service, or one for several.
 *
 * It is called after the token was wiped or removed locally, when the owner revokes or removes the secret and when
 * their account is erased. A failure is logged and handed to the audit trail; it never brings the token back.
 */
interface TokenRevoker
{
    public function supports(ServiceName $service): bool;

    /**
     * @throws Throwable when the service did not revoke the token; the bundle catches it
     */
    public function revoke(ServiceName $service, OAuthToken $token): void;
}
