<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Phil\FollowBundle\Infrastructure\Doctrine\FollowTables;
use Phil\NotificationsBundle\Domain\Identifiers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The columns, keys and indexes of each table, as the queries of the repositories rely on them.
 */
#[CoversClass(FollowTables::class)]
final class TableDefinitionsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, array<string, array{int|null, bool}>, list<string>, array<string, array{list<string>, bool}>}>
     */
    public static function tables(): iterable
    {
        $id = Identifiers::ID_LENGTH;
        $key = Identifiers::KEY_LENGTH;

        yield 'follows' => [
            'phil_follows',
            ['member' => [$id, true], 'subject_type' => [$key, true], 'subject_id' => [$id, true], 'level' => [16, true], 'events' => [null, true], 'followed_at' => [null, true]],
            ['member', 'subject_type', 'subject_id'],
            ['phil_follows_subject' => [['subject_type', 'subject_id', 'member'], false]],
        ];
        yield 'requests' => [
            'phil_follow_requests',
            ['id' => [$id, true], 'requester' => [$id, true], 'subject_type' => [$key, true], 'subject_id' => [$id, true], 'kind' => [16, true], 'approver' => [$id, true], 'level' => [16, true], 'events' => [null, true], 'requested_at' => [null, true]],
            ['id'],
            ['phil_follow_requests_approver' => [['approver', 'id'], false], 'phil_follow_requests_pending' => [['requester', 'subject_type', 'subject_id', 'kind'], true]],
        ];
        yield 'mutuals' => [
            'phil_follow_mutuals',
            ['first_member' => [$id, true], 'second_member' => [$id, true], 'since' => [null, true]],
            ['first_member', 'second_member'],
            ['phil_follow_mutuals_second' => [['second_member', 'first_member'], false]],
        ];
        yield 'activities' => [
            'phil_follow_activities',
            ['id' => [$id, true], 'subject_type' => [$key, true], 'subject_id' => [$id, true], 'event' => [$key, true], 'actor' => [$id, false], 'mentions' => [null, true], 'occurred_at' => [null, true]],
            ['id'],
            ['phil_follow_activities_occurred' => [['occurred_at'], false], 'phil_follow_activities_actor' => [['actor'], false]],
        ];
        yield 'deliveries' => [
            'phil_follow_deliveries',
            ['activity_id' => [$id, true], 'member' => [$id, true], 'claim' => [36, true], 'claimed_at' => [null, true]],
            ['activity_id', 'member'],
            ['phil_follow_deliveries_claimed' => [['claimed_at'], false], 'phil_follow_deliveries_member' => [['member'], false]],
        ];
        yield 'unseen' => [
            'phil_follow_unseen',
            ['member' => [$id, true], 'subject_type' => [$key, true], 'subject_id' => [$id, true], 'notified_at' => [null, true]],
            ['member', 'subject_type', 'subject_id'],
            ['phil_follow_unseen_subject' => [['subject_type', 'subject_id', 'member'], false], 'phil_follow_unseen_notified' => [['notified_at'], false]],
        ];
    }

    /**
     * @param array<string, array{int|null, bool}>     $columns
     * @param list<string>                             $primary
     * @param array<string, array{list<string>, bool}> $indexes
     */
    #[DataProvider('tables')]
    public function testEachTableHoldsItsColumnsKeysAndIndexes(string $name, array $columns, array $primary, array $indexes): void
    {
        $table = FollowTables::configure(Schema::editor()->create())->getTable($name);

        $found = [];

        foreach ($table->getColumns() as $column) {
            $found[$column->getObjectName()->toString()] = [$column->getLength(), $column->getNotnull()];
        }

        self::assertSame($columns, $found);
        self::assertSame($primary, array_map(static fn (UnqualifiedName $column): string => $column->toString(), $table->getPrimaryKeyConstraint()?->getColumnNames() ?? []));

        $foundIndexes = [];

        foreach (array_keys($indexes) as $indexName) {
            $index = $table->getIndex($indexName);
            $foundIndexes[$indexName] = [
                array_map(static fn (IndexedColumn $indexed): string => $indexed->getColumnName()->toString(), $index->getIndexedColumns()),
                IndexType::UNIQUE === $index->getType(),
            ];
        }

        self::assertSame($indexes, $foundIndexes);
    }
}
