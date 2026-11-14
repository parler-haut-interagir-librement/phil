<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Override;

/**
 * A connection to MySQL that never opens: the statements it is given are recorded, with their parameters. Built by
 * {@see self::mysql()}.
 */
final class RecordingConnection extends Connection
{
    /** @var list<array{string, list<mixed>}> */
    public array $statements = [];

    public static function mysql(): self
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0', 'wrapperClass' => self::class]);
        \assert($connection instanceof self);

        return $connection;
    }

    /**
     * @param list<mixed>|array<string, mixed> $params
     * @param array<mixed>                     $types
     */
    #[Override]
    public function executeStatement(string $sql, array $params = [], array $types = []): int
    {
        $this->statements[] = [$sql, array_values($params)];

        return 1;
    }
}
