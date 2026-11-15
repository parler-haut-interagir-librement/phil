<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\AuthorizationCode;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\IssuedToken;

/**
 * The authorization codes and the tokens the provider issued.
 */
interface GrantRepository
{
    public function code(string $id): ?AuthorizationCode;

    public function saveCode(AuthorizationCode $code): void;

    public function token(string $id): ?IssuedToken;

    /**
     * @return list<IssuedToken>
     */
    public function family(string $family): array;

    /**
     * @return list<IssuedToken> the tokens of an account not revoked, for a client or for all
     */
    public function activeFor(string $accountId, ?string $clientId = null): array;

    /**
     * @return list<IssuedToken> the tokens issued in a session, not revoked
     */
    public function forSession(string $sessionId): array;

    public function saveToken(IssuedToken ...$tokens): void;

    /**
     * Removes the codes and tokens expired before the date.
     *
     * @return int the rows removed
     */
    public function purge(DateTimeImmutable $before): int;
}
