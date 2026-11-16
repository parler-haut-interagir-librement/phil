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

    /**
     * Marks the code used, in one step, unless it already is: of two requests that read it unused at once, only one is
     * told it used it (RFC 6749, section 4.1.2).
     *
     * @return bool whether this call used the code
     */
    public function consumeCode(AuthorizationCode $code, DateTimeImmutable $now): bool;

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
     * Marks the refresh token rotated, in one step, unless it already is rotated or revoked: of two requests that read
     * it active at once, only one is told it rotated it (RFC 9700, section 4.14).
     *
     * @return bool whether this call rotated the token
     */
    public function rotateToken(IssuedToken $token, DateTimeImmutable $now): bool;

    /**
     * Removes the codes and tokens expired before the date.
     *
     * @return int the rows removed
     */
    public function purge(DateTimeImmutable $before): int;
}
