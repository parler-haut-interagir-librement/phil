<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\ApiKeysBundle\Domain\Contract\ApiTokenRepository;
use Phil\ApiKeysBundle\Domain\Model\ApiToken;
use Phil\ApiKeysBundle\Infrastructure\Memory\InMemoryApiTokenRepository;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryApiTokenRepository::class)]
final class InMemoryRepositoryTest extends RepositoryTestCase
{
    public function testAResetForgetsEveryToken(): void
    {
        $repository = new InMemoryApiTokenRepository();
        $repository->save($this->token('AAAAAAAAAAAA', 'alice', '2026-11-08 10:00:00'), $this->token('BBBBBBBBBBBB', 'bob', '2026-11-08 10:00:00'));

        $repository->reset();

        self::assertNull($repository->get('AAAAAAAAAAAA'));
        self::assertSame([], $repository->find(null, 10));
    }

    #[Override]
    protected function repository(): ApiTokenRepository
    {
        return new InMemoryApiTokenRepository();
    }

    #[Override]
    protected function clear(): void
    {
    }

    #[Override]
    protected function assertUnwritten(?ApiToken $token): void
    {
        // The store holds the token itself: what is recorded on it is in the store.
        self::assertInstanceOf(ApiToken::class, $token);
    }
}
