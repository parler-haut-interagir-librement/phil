<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use Phil\Stockroom\LoadLine;
use Phil\Stockroom\LoadList;
use Phil\Stockroom\Movement;
use Phil\Stockroom\MovementKind;
use Phil\Stockroom\Place;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalBalances;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalJournal;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalLoadLists;
use Phil\StockroomBundle\Tests\Unit\Fixture\RecordingMiddleware;
use Phil\StockroomBundle\Tests\Unit\Fixture\Stores;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The numbers are sent to the database as numbers: MySQL and PostgreSQL compare and lock on the type that is bound,
 * which SQLite forgives.
 */
#[CoversClass(DbalBalances::class)]
#[CoversClass(DbalJournal::class)]
#[CoversClass(DbalLoadLists::class)]
final class BindingsTest extends TestCase
{
    public function testTheQuantityOfABalanceIsBoundAsAnInteger(): void
    {
        $recording = new RecordingMiddleware();
        $stores = $this->stores($recording);

        $stores->balances->set('sword', Place::container('shelf'), 3);
        self::assertSame([ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER], $recording->typesOf('INSERT INTO phil_stockroom_balance'));

        $stores->balances->set('sword', Place::container('shelf'), 4);
        self::assertSame([ParameterType::INTEGER, ParameterType::STRING, ParameterType::STRING], $recording->typesOf('UPDATE phil_stockroom_balance'));
    }

    public function testTheNumbersOfAMovementAreBoundAsIntegers(): void
    {
        $recording = new RecordingMiddleware();
        $stores = $this->stores($recording);

        $stores->journal->append(new Movement('m-1', MovementKind::In, 'sword', 3, null, Place::container('shelf'), new DateTimeImmutable('2026-11-13 10:00:00 UTC')));

        $types = $recording->typesOf('INSERT INTO phil_stockroom_movement');
        self::assertCount(9, $types);
        self::assertSame(ParameterType::INTEGER, $types[3]);
        self::assertSame(ParameterType::INTEGER, $types[6]);
        self::assertCount(2, array_filter($types, static fn (ParameterType $type): bool => ParameterType::INTEGER === $type));
    }

    public function testThePositionOfALoadLineIsBoundAsAnInteger(): void
    {
        $recording = new RecordingMiddleware();
        $stores = $this->stores($recording);

        $stores->loadLists->save(new LoadList('larp-7', [new LoadLine('axe', 3)]));

        $types = $recording->typesOf('INSERT INTO phil_stockroom_load_line');
        self::assertCount(5, $types);
        self::assertSame(ParameterType::INTEGER, $types[1]);
        self::assertSame(ParameterType::INTEGER, $types[3]);
        self::assertCount(2, array_filter($types, static fn (ParameterType $type): bool => ParameterType::INTEGER === $type));
    }

    private function stores(RecordingMiddleware $recording): Stores
    {
        return Stores::on(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], new Configuration()->setMiddlewares([$recording])));
    }
}
