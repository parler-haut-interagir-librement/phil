<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\StoreSchemaListener;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\StoreTables;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\Sqlite;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(StoreSchemaListener::class)]
final class StoreSchemaListenerTest extends TestCase
{
    /**
     * @return iterable<string, array{bool, bool}>
     */
    public static function tables(): iterable
    {
        yield 'both' => [true, true];
        yield 'the placements' => [true, false];
        yield 'the suspensions' => [false, true];
    }

    #[DataProvider('tables')]
    public function testTheTablesOfTheStoresJoinTheSchemaOfTheirConnection(bool $placements, bool $suspensions): void
    {
        $entityManager = Sqlite::entityManager(__DIR__.'/../../Fixture/Switch');
        $event = new GenerateSchemaEventArgs($entityManager, Schema::editor()->create());

        new StoreSchemaListener($entityManager->getConnection(), $placements, $suspensions)->postGenerateSchema($event);

        self::assertSame($placements, $event->getSchema()->hasTable(StoreTables::PLACEMENTS));
        self::assertSame($suspensions, $event->getSchema()->hasTable(StoreTables::SUSPENSIONS));
    }

    public function testTheSchemaOfAnotherConnectionIsLeftAlone(): void
    {
        $event = new GenerateSchemaEventArgs(Sqlite::entityManager(__DIR__.'/../../Fixture/Switch'), Schema::editor()->create());

        new StoreSchemaListener(Sqlite::entityManager(__DIR__.'/../../Fixture/Switch')->getConnection(), true, true)->postGenerateSchema($event);

        self::assertSame([], $event->getSchema()->getTables());
    }
}
