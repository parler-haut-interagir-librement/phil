<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\CommerceConsignmentBundle\Domain\Contract\ConsignorRepository;
use Phil\CommerceConsignmentBundle\Domain\Contract\LedgerRepository;
use Phil\CommerceConsignmentBundle\Infrastructure\Memory\InMemoryConsignorRepository;
use Phil\CommerceConsignmentBundle\Infrastructure\Memory\InMemoryLedgerRepository;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryLedgerRepository::class)]
#[CoversClass(InMemoryConsignorRepository::class)]
final class InMemoryRepositoryTest extends RepositoryTestCase
{
    private ?InMemoryLedgerRepository $repository = null;

    private ?InMemoryConsignorRepository $consignors = null;

    #[Override]
    protected function repository(): LedgerRepository
    {
        return $this->repository ??= new InMemoryLedgerRepository();
    }

    #[Override]
    protected function consignors(): ConsignorRepository
    {
        return $this->consignors ??= new InMemoryConsignorRepository();
    }

    #[Override]
    protected function flushAndClear(): void
    {
    }
}
