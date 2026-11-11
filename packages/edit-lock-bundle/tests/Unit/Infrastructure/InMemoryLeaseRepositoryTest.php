<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\EditLockBundle\Domain\Repository\LeaseRepository;
use Phil\EditLockBundle\Infrastructure\Memory\InMemoryLeaseRepository;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryLeaseRepository::class)]
final class InMemoryLeaseRepositoryTest extends LeaseRepositoryTestCase
{
    #[Override]
    protected function store(): LeaseRepository
    {
        return new InMemoryLeaseRepository();
    }
}
