<?php

declare(strict_types=1);

namespace Phil\MeasurementBundle\Tests\Integration;

use Override;
use Phil\MeasurementBundle\Infrastructure\Doctrine\MeasurementType;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The column on SQLite, in memory.
 */
#[CoversClass(MeasurementType::class)]
final class SqliteColumnTest extends DatabaseTestCase
{
    #[Override]
    protected function url(): string
    {
        return 'sqlite:///:memory:';
    }
}
