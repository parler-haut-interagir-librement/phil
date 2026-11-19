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
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(DoctrinePosRepository::class)]
#[CoversClass(Terminal::class)]
#[CoversClass(TerminalPayment::class)]
#[CoversClass(SalePreset::class)]
#[CoversClass(CashierPin::class)]
#[Group('postgresql')]
final class PostgreSqlRepositoryTest extends RepositoryTestCase
{
    #[Override]
    protected function url(): ?string
    {
        $url = getenv('PHIL_TEST_POSTGRESQL_URL');

        return \is_string($url) && '' !== $url ? $url : null;
    }
}
