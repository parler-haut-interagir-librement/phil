<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Infrastructure\Move;

use Phil\TenancyDatabaseBundle\Domain\Exception\TenantNotCopyable;
use Phil\TenancyDatabaseBundle\Infrastructure\Move\CopyPlan;
use Phil\TenancyDatabaseBundle\Infrastructure\Move\CopyTable;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\Sqlite;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CopyPlan::class)]
#[CoversClass(CopyTable::class)]
#[CoversClass(TenantNotCopyable::class)]
final class CopyPlanTest extends TestCase
{
    public function testTheTablesOfATenantInTheOrderTheirRowsCanBeInserted(): void
    {
        $plan = CopyPlan::of(Sqlite::entityManager(\dirname(__DIR__, 2).'/Fixture/Plan'));

        self::assertSame([
            ['shop', 'tenant_ref = ?', ['id'], 'shop', 'id'],
            ['tag', 'tenant_ref = ?', ['code'], null, null],
            ['b_order', 'tenant_ref = ?', ['id'], 'b_order', 'id'],
            ['a_payment', 'tenant_ref = ?', ['id'], 'a_payment', 'id'],
            ['kiosk', 'id IN (SELECT id FROM shop WHERE tenant_ref = ?)', ['id'], null, null],
            ['a_pop_up', 'id IN (SELECT id FROM shop WHERE tenant_ref = ?)', ['id'], null, null],
            ['b_order_tag', 'order_id IN (SELECT id FROM b_order WHERE tenant_ref = ?)', ['order_id', 'tag_id'], null, null],
            ['kiosk_tag', 'kiosk_id IN (SELECT id FROM shop WHERE tenant_ref = ?)', ['kiosk_id', 'tag_id'], null, null],
        ], array_map(static fn (CopyTable $table): array => [$table->table, $table->where, $table->orderBy, $table->keyTable, $table->key], $plan));
    }

    public function testAMappedSuperclassAndAnEmbeddableHaveNoTableOfTheirOwn(): void
    {
        $plan = CopyPlan::of(Sqlite::entityManager(\dirname(__DIR__, 2).'/Fixture/Inherited'));

        self::assertSame([['memo', 'tenant_ref = ?', ['id'], 'memo', 'id']], array_map(static fn (CopyTable $table): array => [$table->table, $table->where, $table->orderBy, $table->keyTable, $table->key], $plan));
    }

    public function testTheTablesOfTheSubclassesOfALevelAreInTheOrderOfTheirNames(): void
    {
        $plan = CopyPlan::of(Sqlite::entityManager(\dirname(__DIR__, 2).'/Fixture/Siblings'));

        self::assertSame(['vehicle', 'bike', 'truck'], array_map(static fn (CopyTable $table): string => $table->table, $plan));
    }

    public function testATableWaitsForEveryTableItReferences(): void
    {
        $plan = CopyPlan::of(Sqlite::entityManager(\dirname(__DIR__, 2).'/Fixture/Dependencies'));

        self::assertSame(['gamma', 'zeta', 'beta', 'a_line'], array_map(static fn (CopyTable $table): string => $table->table, $plan));
    }

    public function testTheStatementsOfATable(): void
    {
        $table = new CopyTable('kiosk', 'id IN (SELECT id FROM shop WHERE tenant_ref = ?)', ['id', 'position']);

        self::assertSame('SELECT * FROM kiosk WHERE id IN (SELECT id FROM shop WHERE tenant_ref = ?) ORDER BY id, position', $table->select());
        self::assertSame('DELETE FROM kiosk WHERE id IN (SELECT id FROM shop WHERE tenant_ref = ?)', $table->delete());
    }

    public function testTablesThatReferenceEachOtherCannotBeOrdered(): void
    {
        $this->expectException(TenantNotCopyable::class);
        $this->expectExceptionMessageIsOrContains('The tenant-scoped tables egg, hen reference each other: a move cannot order their rows.');

        CopyPlan::of(Sqlite::entityManager(\dirname(__DIR__, 2).'/Fixture/Cycle'));
    }

    public function testATableReachedThroughAKeyOfSeveralColumnsIsRefused(): void
    {
        $this->expectException(TenantNotCopyable::class);
        $this->expectExceptionMessageIsOrContains('The table line_link reaches its tenant through a key of several columns: a move copies a table reached through one column only.');

        CopyPlan::of(Sqlite::entityManager(\dirname(__DIR__, 2).'/Fixture/Composite'));
    }

    public function testAJoinTableOfOneColumnOnAKeyOfSeveralIsRefused(): void
    {
        $this->expectException(TenantNotCopyable::class);
        $this->expectExceptionMessageIsOrContains('The table pair_mark reaches its tenant through a key of several columns');

        CopyPlan::of(Sqlite::entityManager(\dirname(__DIR__, 2).'/Fixture/Partial'));
    }
}
