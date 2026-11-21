<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Override;
use Phil\PrivacyBundle\Infrastructure\Database\DbalIncidentRegister;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DbalIncidentRegister::class)]
#[CoversClass(PrivacyTables::class)]
final class SqliteIncidentRegisterTest extends IncidentRegisterTestCase
{
    #[Override]
    protected function open(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }
}
