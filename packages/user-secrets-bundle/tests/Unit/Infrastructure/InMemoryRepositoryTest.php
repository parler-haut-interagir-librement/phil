<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\UserSecretsBundle\Domain\Contract\StoredSecretRepository;
use Phil\UserSecretsBundle\Infrastructure\Memory\InMemoryStoredSecretRepository;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryStoredSecretRepository::class)]
final class InMemoryRepositoryTest extends RepositoryTestCase
{
    #[Override]
    protected function repository(): StoredSecretRepository
    {
        return new InMemoryStoredSecretRepository();
    }
}
