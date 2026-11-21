<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\IdentityProviderBundle\Domain\Model\Link\LinkToken;

/**
 * The tokens of the links sent by email, in the stored mode.
 */
interface LinkTokenRepository
{
    public function get(string $id): ?LinkToken;

    public function save(LinkToken $token): void;

    /**
     * Removes the token, once: of two requests that use the same link at once, only one is told it removed it.
     *
     * @return bool whether this call removed it
     */
    public function consume(string $id): bool;

    /**
     * Removes the tokens of an account, of one purpose or all of them.
     *
     * @return int how many were removed
     */
    public function revoke(string $accountId, ?string $purpose = null): int;

    /**
     * Removes the tokens expired before the moment given.
     *
     * @return int how many were removed
     */
    public function purge(DateTimeImmutable $before): int;
}
