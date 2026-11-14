<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Tests\Unit\Infrastructure;

use Phil\StockroomBundle\Infrastructure\Dbal\DbalItems;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalJournal;
use Phil\StockroomBundle\Tests\Unit\Fixture\Stores;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * A row that is not one the library keeps is refused with the reason, never read as another.
 */
#[CoversClass(DbalItems::class)]
#[CoversClass(DbalJournal::class)]
final class CorruptRowsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function items(): iterable
    {
        yield 'a tracking that is none' => ['pile', 'usable', 'The tracking of an item of the stockroom is not one.'];
        yield 'a condition that is none' => ['unit', 'broken', 'The condition of an item of the stockroom is not one.'];
    }

    #[DataProvider('items')]
    public function testAnItemOfAnotherTrackingOrConditionIsRefused(string $tracking, string $condition, string $message): void
    {
        $stores = Stores::sqlite();
        $connection = $stores->connection;
        self::assertNotNull($connection);
        $connection->insert($stores->tables->items, [
            'id' => 'sword',
            'code' => 'SW-1',
            'name' => 'Foam sword',
            'tracking' => $tracking,
            'item_condition' => $condition,
        ]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains($message);
        $stores->items->get('sword');
    }

    public function testAMovementOfAnotherKindIsRefused(): void
    {
        $stores = Stores::sqlite();
        $connection = $stores->connection;
        self::assertNotNull($connection);
        $connection->insert($stores->tables->movements, ['id' => 'm-1', 'kind' => 'teleport', 'item' => 'sword', 'quantity' => 1, 'to_place' => 'container:shelf', 'moved_at' => 0]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('The kind of a movement of the stockroom is not one.');
        $stores->journal->ofItem('sword', 10);
    }
}
