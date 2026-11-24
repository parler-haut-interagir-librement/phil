<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Phil\NotificationsBundle\Domain\Identifiers;

/**
 * The bundle's tables, their instants in `datetime_utc_immutable` columns:
 *
 * - the follows, keyed by member and subject — one per pair —, indexed by subject for the followers and the pages of a
 *   delivery;
 * - the requests, one per requester, subject and kind, indexed by approver;
 * - the mutual relationships, one per pair of members in byte order;
 * - the activities, until their retention ends;
 * - the claims of deliveries, keyed by activity and member, each with the token of the attempt that made it, so that a
 *   member gets an activity once;
 * - the marks of the notifications sent on every channel, by member and subject, until the member sees it;
 * - whether each member hides their followers.
 *
 * **Identifiers compare byte by byte.** On MySQL and MariaDB the identifiers and keys are stored in `utf8mb4` with the
 * `utf8mb4_bin` collation, so that `member:Ann` is never read for `member:ann` and the pages follow the byte order of
 * PHP; PostgreSQL's default collations are deterministic and SQLite compares bytes.
 */
final class FollowTables
{
    public const string FOLLOWS = 'phil_follows';

    public const string REQUESTS = 'phil_follow_requests';

    public const string MUTUALS = 'phil_follow_mutuals';

    public const string ACTIVITIES = 'phil_follow_activities';

    public const string DELIVERIES = 'phil_follow_deliveries';

    public const string UNSEEN = 'phil_follow_unseen';

    public const string VISIBILITY = 'phil_follow_visibility';

    public const array ALL = [self::FOLLOWS, self::REQUESTS, self::MUTUALS, self::ACTIVITIES, self::DELIVERIES, self::UNSEEN, self::VISIBILITY];

    private const int ID = Identifiers::ID_LENGTH;

    private const int KEY = Identifiers::KEY_LENGTH;

    private function __construct()
    {
    }

    /**
     * Adds the tables the schema lacks. Without a platform, the columns keep the connection's default collation: the
     * schema listener always gives the platform of its connection.
     */
    public static function configure(Schema $schema, ?AbstractPlatform $platform = null): Schema
    {
        $binary = $platform instanceof AbstractMySQLPlatform;
        $editor = $schema->edit();

        foreach (self::tables($binary) as $name => $table) {
            if (!$schema->hasTable($name)) {
                $editor->addTable($table);
            }
        }

        return $editor->create();
    }

    /**
     * @return array<string, Table>
     */
    private static function tables(bool $binary): array
    {
        return [
            self::FOLLOWS => self::table(self::FOLLOWS, [
                self::string('member', self::ID, $binary),
                self::string('subject_type', self::KEY, $binary),
                self::string('subject_id', self::ID, $binary),
                self::string('level', 16),
                self::text('events'),
                self::instant('followed_at'),
            ], ['member', 'subject_type', 'subject_id'], ['subject' => ['subject_type', 'subject_id', 'member']]),
            self::REQUESTS => self::table(self::REQUESTS, [
                self::string('id', self::ID, $binary),
                self::string('requester', self::ID, $binary),
                self::string('subject_type', self::KEY, $binary),
                self::string('subject_id', self::ID, $binary),
                self::string('kind', 16),
                self::string('approver', self::ID, $binary),
                self::string('level', 16),
                self::text('events'),
                self::instant('requested_at'),
            ], ['id'], ['approver' => ['approver', 'id']], ['pending' => ['requester', 'subject_type', 'subject_id', 'kind']]),
            self::MUTUALS => self::table(self::MUTUALS, [
                self::string('first_member', self::ID, $binary),
                self::string('second_member', self::ID, $binary),
                self::instant('since'),
            ], ['first_member', 'second_member'], ['second' => ['second_member', 'first_member']]),
            self::ACTIVITIES => self::table(self::ACTIVITIES, [
                self::string('id', self::ID, $binary),
                self::string('subject_type', self::KEY, $binary),
                self::string('subject_id', self::ID, $binary),
                self::string('event', self::KEY, $binary),
                self::string('actor', self::ID, $binary, true),
                self::text('mentions'),
                self::instant('occurred_at'),
            ], ['id'], ['occurred' => ['occurred_at'], 'actor' => ['actor']]),
            self::DELIVERIES => self::table(self::DELIVERIES, [
                self::string('activity_id', self::ID, $binary),
                self::string('member', self::ID, $binary),
                self::string('claim', 36),
                self::instant('claimed_at'),
            ], ['activity_id', 'member'], ['claimed' => ['claimed_at'], 'member' => ['member']]),
            self::UNSEEN => self::table(self::UNSEEN, [
                self::string('member', self::ID, $binary),
                self::string('subject_type', self::KEY, $binary),
                self::string('subject_id', self::ID, $binary),
                self::instant('notified_at'),
            ], ['member', 'subject_type', 'subject_id'], ['subject' => ['subject_type', 'subject_id', 'member'], 'notified' => ['notified_at']]),
            self::VISIBILITY => self::table(self::VISIBILITY, [
                self::string('member', self::ID, $binary),
                Column::editor()->setUnquotedName('followers_hidden')->setTypeName(Types::BOOLEAN)->create(),
            ], ['member']),
        ];
    }

    /**
     * @param non-empty-string                                $name
     * @param list<Column>                                    $columns
     * @param non-empty-list<non-empty-string>                $primary
     * @param array<non-empty-string, list<non-empty-string>> $indexes
     * @param array<non-empty-string, list<non-empty-string>> $uniques
     */
    private static function table(string $name, array $columns, array $primary, array $indexes = [], array $uniques = []): Table
    {
        $built = [];

        foreach ($indexes as $suffix => $indexed) {
            $built[] = Index::editor()->setUnquotedName($name.'_'.$suffix)->setUnquotedColumnNames(...$indexed)->create();
        }

        foreach ($uniques as $suffix => $indexed) {
            $built[] = Index::editor()->setUnquotedName($name.'_'.$suffix)->setUnquotedColumnNames(...$indexed)->setType(IndexType::UNIQUE)->create();
        }

        return Table::editor()
            ->setUnquotedName($name)
            ->setColumns(...$columns)
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames(...$primary)->create())
            ->setIndexes(...$built)
            ->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function string(string $name, int $length, bool $binary = false, bool $nullable = false): Column
    {
        $column = Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->setNotNull(!$nullable);

        if ($binary) {
            $column->setCharset('utf8mb4')->setCollation('utf8mb4_bin');
        }

        return $column->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function text(string $name): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::TEXT)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function instant(string $name): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::DATETIME_UTC_IMMUTABLE)->create();
    }
}
