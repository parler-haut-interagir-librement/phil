<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;
use Override;

/**
 * A connection on which the direct conversations lose their key while a refused one is removed, as when a participant
 * of the conversation that took the key is erased at that moment.
 */
final class ForgettingConnection extends Connection
{
    public static function sqlite(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'wrapperClass' => self::class]);
        Store::create($connection);

        return $connection;
    }

    /**
     * @param array<string, mixed>                                                                  $criteria
     * @param array<int<0,max>, string|ParameterType|Type>|array<string, string|ParameterType|Type> $types
     */
    #[Override]
    public function delete(string $table, array $criteria = [], array $types = []): int|string
    {
        if ('phil_conversation_participant' === $table) {
            $this->executeStatement('UPDATE phil_conversation SET direct_key = NULL');
        }

        return parent::delete($table, $criteria, $types);
    }
}
