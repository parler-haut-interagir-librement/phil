<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Integration;

use Override;
use Phil\CommercePosBundle\Domain\Model\CashierPin;
use Phil\CommercePosBundle\Domain\Model\SalePreset;
use Phil\CommercePosBundle\Domain\Model\Terminal;
use Phil\CommercePosBundle\Domain\Model\TerminalPayment;
use Phil\CommercePosBundle\Infrastructure\Doctrine\DoctrinePosRepository;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DoctrinePosRepository::class)]
#[CoversClass(Terminal::class)]
#[CoversClass(TerminalPayment::class)]
#[CoversClass(SalePreset::class)]
#[CoversClass(CashierPin::class)]
final class SqliteRepositoryTest extends RepositoryTestCase
{
    #[Override]
    protected function url(): string
    {
        return 'sqlite:///:memory:';
    }
}
