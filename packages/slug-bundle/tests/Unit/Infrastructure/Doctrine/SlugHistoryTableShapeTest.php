<?php

declare(strict_types=1);

namespace Phil\SlugBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Phil\SlugBundle\Infrastructure\Doctrine\SlugHistoryTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns of the table all hold a value, and its index on the subject has a name of its own.
 */
#[CoversClass(SlugHistoryTable::class)]
final class SlugHistoryTableShapeTest extends TestCase
{
    public function testEveryColumnHoldsAValueAndTheSubjectIsIndexed(): void
    {
        foreach ([new MySQLPlatform(), new SQLitePlatform(), null] as $platform) {
            $table = SlugHistoryTable::configure(Schema::editor()->create(), $platform)->getTable(SlugHistoryTable::NAME);

            self::assertCount(5, $table->getColumns());

            foreach ($table->getColumns() as $column) {
                self::assertTrue($column->getNotnull(), $column->getObjectName()->toString());
            }

            $names = array_map(static fn (Index $index): string => $index->getObjectName()->toString(), $table->getIndexes());
            self::assertContains('phil_slug_history_subject', $names);
        }
    }
}
