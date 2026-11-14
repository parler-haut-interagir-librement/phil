<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Override;
use RuntimeException;
use SensitiveParameter;

/**
 * A driver middleware that records the parameters each connection is opened with, as the driver receives them, and
 * opens none.
 */
final class RecordingDriver implements Middleware
{
    public const string REFUSAL = 'Not opened.';

    /** @var list<array<array-key, mixed>> */
    public array $opened = [];

    #[Override]
    public function wrap(Driver $driver): Driver
    {
        return new RecordingDriverConnector($driver, $this);
    }

    /**
     * @param array<array-key, mixed> $params
     */
    public function record(#[SensitiveParameter] array $params): Connection
    {
        $this->opened[] = $params;

        throw new RuntimeException(self::REFUSAL);
    }
}
