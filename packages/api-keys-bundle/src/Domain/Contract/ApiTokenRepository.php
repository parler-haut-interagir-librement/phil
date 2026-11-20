<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\ApiKeysBundle\Domain\Model\ApiToken;
use Phil\ApiKeysBundle\Domain\Token\TokenOwner;

/**
 * Where tokens are kept.
 */
interface ApiTokenRepository
{
    public function get(string $id): ?ApiToken;

    /**
     * Tokens of one owner — its type and its identifier — or of every owner, the newest first, those created at the
     * same instant by identifier.
     *
     * @param positive-int $limit
     *
     * @return list<ApiToken>
     */
    public function find(?TokenOwner $owner, int $limit): array;

    public function save(ApiToken ...$tokens): void;

    /**
     * Writes the last use of a token — its date and address — and nothing else, so that the request that used it
     * flushes none of the application's pending changes.
     */
    public function recordUse(ApiToken $token): void;

    /**
     * Removes the tokens that expired or were revoked before that instant.
     *
     * @return int how many were removed
     */
    public function purge(DateTimeImmutable $before): int;
}
