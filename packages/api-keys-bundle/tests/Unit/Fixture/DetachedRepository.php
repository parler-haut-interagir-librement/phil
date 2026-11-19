<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\ApiKeysBundle\Domain\Contract\ApiTokenRepository;
use Phil\ApiKeysBundle\Domain\Model\ApiToken;
use Phil\ApiKeysBundle\Domain\Token\TokenOwner;
use Phil\ApiKeysBundle\Infrastructure\Memory\InMemoryApiTokenRepository;

/**
 * A store that keeps copies, as a database does: a change to a token is in the store only once it is saved.
 */
final readonly class DetachedRepository implements ApiTokenRepository
{
    private InMemoryApiTokenRepository $tokens;

    public function __construct()
    {
        $this->tokens = new InMemoryApiTokenRepository();
    }

    #[Override]
    public function get(string $id): ?ApiToken
    {
        $token = $this->tokens->get($id);

        return $token instanceof ApiToken ? clone $token : null;
    }

    #[Override]
    public function find(?TokenOwner $owner, int $limit): array
    {
        return array_map(static fn (ApiToken $token): ApiToken => clone $token, $this->tokens->find($owner, $limit));
    }

    #[Override]
    public function save(ApiToken ...$tokens): void
    {
        $this->tokens->save(...array_map(static fn (ApiToken $token): ApiToken => clone $token, $tokens));
    }

    #[Override]
    public function recordUse(ApiToken $token): void
    {
        $this->tokens->recordUse(clone $token);
    }

    #[Override]
    public function purge(DateTimeImmutable $before): int
    {
        return $this->tokens->purge($before);
    }
}
