<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Tests\Integration;

use Override;
use Phil\LibraryBundle\Domain\Model\Copy;
use Phil\LibraryBundle\Domain\Model\Loan;
use Phil\LibraryBundle\Domain\Model\Title;
use Phil\LibraryBundle\Infrastructure\Doctrine\DbalTitleSearch;
use Phil\LibraryBundle\Infrastructure\Doctrine\DoctrineCopyRepository;
use Phil\LibraryBundle\Infrastructure\Doctrine\DoctrineLoanRepository;
use Phil\LibraryBundle\Infrastructure\Doctrine\DoctrineTitleRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(DoctrineTitleRepository::class)]
#[CoversClass(DoctrineCopyRepository::class)]
#[CoversClass(DoctrineLoanRepository::class)]
#[CoversClass(DbalTitleSearch::class)]
#[CoversClass(Title::class)]
#[CoversClass(Copy::class)]
#[CoversClass(Loan::class)]
#[Group('postgresql')]
final class PostgreSqlLibraryTest extends ServerTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
