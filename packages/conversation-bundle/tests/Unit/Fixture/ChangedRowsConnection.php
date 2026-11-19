<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;
use Override;

/**
 * A connection whose updates of the participations report no row, as MySQL does for an update that leaves the row as it
 * was: the rows are written all the same.
 */
final class ChangedRowsConnection extends Connection
{
    public static function sqlite(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'wrapperClass' => self::class]);
        Store::create($connection);

        return $connection;
    }

    /**
     * @param array<string, mixed>                                                                  $data
     * @param array<string, mixed>                                                                  $criteria
     * @param array<int<0,max>, string|ParameterType|Type>|array<string, string|ParameterType|Type> $types
     */
    #[Override]
    public function update(string $table, array $data, array $criteria = [], array $types = []): int|string
    {
        $written = parent::update($table, $data, $criteria, $types);

        return 'phil_conversation_participant' === $table ? 0 : $written;
    }
}
