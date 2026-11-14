<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Phil\NotificationsBundle\Domain\Identifiers;
use Phil\Url\Url;

/**
 * The bundle's tables: the notifications, indexed for the unread count, the centre, the grouping, the digest and the
 * purge, its instants in `datetime_utc_immutable` columns; the recipients' preferences, one row each; and, with Web
 * Push, the browsers' push subscriptions, one row per endpoint, keyed by its hash.
 */
final class NotificationTables
{
    public const string NOTIFICATIONS = 'phil_notifications';

    public const string PREFERENCES = 'phil_notification_preferences';

    public const string PUSH_SUBSCRIPTIONS = 'phil_notification_push_subscriptions';

    /** The length of a group key: a type, `|`, a subject type, `:` and a subject identifier. */
    public const int GROUP_LENGTH = Identifiers::KEY_LENGTH * 2 + Identifiers::ID_LENGTH + 2;

    public static function configure(Schema $schema, bool $preferences = true, bool $pushSubscriptions = false): Schema
    {
        $editor = $schema->edit();

        if (!$schema->hasTable(self::NOTIFICATIONS)) {
            $editor->addTable(self::notifications());
        }

        if ($preferences && !$schema->hasTable(self::PREFERENCES)) {
            $editor->addTable(self::preferences());
        }

        if ($pushSubscriptions && !$schema->hasTable(self::PUSH_SUBSCRIPTIONS)) {
            $editor->addTable(self::pushSubscriptions());
        }

        return $editor->create();
    }

    private static function notifications(): Table
    {
        $name = self::NOTIFICATIONS;

        return Table::editor()
            ->setUnquotedName($name)
            ->setColumns(
                self::string('id', Identifiers::ID_LENGTH),
                self::string('recipient', Identifiers::ID_LENGTH),
                self::string('type', Identifiers::KEY_LENGTH),
                self::string('actor', Identifiers::ID_LENGTH, true),
                self::string('subject_type', Identifiers::KEY_LENGTH, true),
                self::string('subject_id', Identifiers::ID_LENGTH, true),
                Column::editor()->setUnquotedName('data')->setTypeName(Types::TEXT)->create(),
                self::string('origin', Identifiers::KEY_LENGTH, true),
                self::string('group_key', self::GROUP_LENGTH),
                self::string('state', 8),
                Column::editor()->setUnquotedName('occurrences')->setTypeName(Types::INTEGER)->create(),
                self::date('created_at'),
                self::date('updated_at'),
                self::date('read_at', true),
                self::date('digested_at', true),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName($name.'_centre')->setUnquotedColumnNames('recipient', 'state', 'updated_at')->create(),
                Index::editor()->setUnquotedName($name.'_group')->setUnquotedColumnNames('recipient', 'group_key')->create(),
                Index::editor()->setUnquotedName($name.'_digest')->setUnquotedColumnNames('state', 'digested_at', 'updated_at')->create(),
                Index::editor()->setUnquotedName($name.'_purge')->setUnquotedColumnNames('updated_at')->create(),
            )
            ->create();
    }

    private static function preferences(): Table
    {
        return Table::editor()
            ->setUnquotedName(self::PREFERENCES)
            ->setColumns(
                self::string('recipient', Identifiers::ID_LENGTH),
                Column::editor()->setUnquotedName('settings')->setTypeName(Types::TEXT)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('recipient')->create())
            ->create();
    }

    private static function pushSubscriptions(): Table
    {
        $name = self::PUSH_SUBSCRIPTIONS;

        return Table::editor()
            ->setUnquotedName($name)
            ->setColumns(
                self::string('id', 64),
                self::string('recipient', Identifiers::ID_LENGTH),
                self::string('endpoint', Url::MAX_LENGTH),
                self::string('public_key', 88),
                self::string('auth_token', 24),
                self::string('content_encoding', 16),
                self::date('subscribed_at'),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(Index::editor()->setUnquotedName($name.'_recipient')->setUnquotedColumnNames('recipient', 'subscribed_at')->create())
            ->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function string(string $name, int $length, bool $nullable = false): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->setNotNull(!$nullable)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function date(string $name, bool $nullable = false): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::DATETIME_UTC_IMMUTABLE)->setNotNull(!$nullable)->create();
    }
}
