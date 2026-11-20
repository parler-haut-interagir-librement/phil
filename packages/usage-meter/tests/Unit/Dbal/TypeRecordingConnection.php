<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Tests\Unit\Dbal;

use Doctrine\DBAL\Connection;
use Override;

/**
 * A connection that keeps the parameter types of every statement it runs.
 */
final class TypeRecordingConnection extends Connection
{
    /**
     * @var list<array<array-key, mixed>>
     */
    public array $recorded = [];

    #[Override]
    public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
    {
        $this->recorded[] = $types;

        return parent::fetchAssociative($query, $params, $types);
    }

    #[Override]
    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        $this->recorded[] = $types;

        return parent::executeStatement($sql, $params, $types);
    }
}
