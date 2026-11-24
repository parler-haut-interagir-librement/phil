<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;
use Override;

/**
 * A connection whose statements that take the row of a subject report no row, as MySQL reports an update that changes
 * nothing, or as a row another transaction created between the update and the insert: the rows are written all the
 * same.
 */
final class UnchangedRowsConnection extends Connection
{
    public static function on(string $path): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path, 'wrapperClass' => self::class]);
    }

    /**
     * @param list<mixed>|array<string, mixed>                                        $params
     * @param array<int<0, max>|string, ArrayParameterType|ParameterType|Type|string> $types
     */
    #[Override]
    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        $changed = parent::executeStatement($sql, $params, $types);

        return str_starts_with($sql, 'UPDATE phil_review_subject SET version') ? 0 : $changed;
    }
}
