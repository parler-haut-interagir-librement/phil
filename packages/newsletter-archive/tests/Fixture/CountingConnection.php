<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Tests\Fixture;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Types\Type;
use Override;

/**
 * A connection that counts the queries it runs, to show that an empty page of the archive asks the store little.
 */
final class CountingConnection extends Connection
{
    public int $queries = 0;

    /**
     * @param list<mixed>|array<string, mixed>                                        $params
     * @param array<int<0, max>|string, ArrayParameterType|ParameterType|Type|string> $types
     */
    #[Override]
    public function executeQuery(string $sql, array $params = [], array $types = [], ?QueryCacheProfile $qcp = null): Result
    {
        ++$this->queries;

        return parent::executeQuery($sql, $params, $types, $qcp);
    }
}
