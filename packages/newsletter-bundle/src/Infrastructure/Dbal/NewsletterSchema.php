<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Dbal;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableEditor;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Types\Types;

/**
 * The tables of the newsletter, added to a schema that lacks them — the one a migration builds, or the one the ORM
 * generates, through `NewsletterSchemaListener` —, and the columns and indexes a later version adds to them. Instants
 * are Unix timestamps.
 *
 * **Keys compare byte by byte.** The identifiers, sites, list and template keys, address keys and hashes take, given
 * the platform, the `utf8mb4` character set and the `utf8mb4_bin` collation on MySQL and MariaDB, whose default
 * collations fold case and accents. An address is case-insensitive by its key, `Subscriber::keyOf()`, which lowercases
 * it before it is stored or looked up; the binary collation keeps `muller@example.com` and `müller@example.com` two
 * subscribers, and the site `Acme` apart from `acme`. PostgreSQL's default collations are deterministic and SQLite
 * compares bytes: their columns keep the default.
 */
final class NewsletterSchema
{
    public const string LISTS = 'phil_newsletter_list';

    public const string SUBSCRIBERS = 'phil_newsletter_subscriber';

    public const string MEMBERSHIPS = 'phil_newsletter_membership';

    public const string CAMPAIGNS = 'phil_newsletter_campaign';

    public const string LINKS = 'phil_newsletter_link';

    public const string DELIVERIES = 'phil_newsletter_delivery';

    public const string TEMPLATES = 'phil_newsletter_template';

    public const string CLICKS = 'phil_newsletter_click';

    public const string RECIPIENT_CLICKS = 'phil_newsletter_recipient_click';

    public const string REASONS = 'phil_newsletter_unsubscribe_reason';

    public const string SUPPRESSIONS = 'phil_newsletter_suppression';

    public const string SEND_RUNS = 'phil_newsletter_send_run';

    /**
     * The columns that compare byte by byte on MySQL and MariaDB.
     */
    public const array BINARY_COLUMNS = ['id', 'site', 'list_key', 'email_key', 'external_id', 'subscriber_id', 'list_id', 'campaign_id', 'url_hash', 'template_key', 'email_hash', 'run_id'];

    public const array TABLES = [self::LISTS, self::SUBSCRIBERS, self::MEMBERSHIPS, self::CAMPAIGNS, self::LINKS, self::DELIVERIES, self::TEMPLATES, self::CLICKS, self::RECIPIENT_CLICKS, self::REASONS, self::SUPPRESSIONS, self::SEND_RUNS];

    /**
     * Adds the tables the schema lacks, and the columns and indexes its tables of the newsletter lack: a column that a
     * version adds to a table accepts a null, so that it is added to a table that holds rows. Without a platform, the columns keep the connection's default collation: the
     * schema listener always gives the platform of its connection.
     */
    public static function configure(Schema $schema, ?AbstractPlatform $platform = null): Schema
    {
        $editor = $schema->edit();

        foreach (self::tables($platform instanceof AbstractMySQLPlatform) as $table) {
            $name = $table->getObjectName()->toString();

            if (!$schema->hasTable($name)) {
                $editor->addTable($table);

                continue;
            }

            $existing = $schema->getTable($name);
            $editor->modifyTable($table->getObjectName(), static function (TableEditor $modified) use ($table, $existing): void {
                foreach ($table->getColumns() as $column) {
                    if (!$existing->hasColumn($column->getObjectName()->toString())) {
                        $modified->addColumn($column);
                    }
                }

                foreach ($table->getIndexes() as $index) {
                    if (IndexType::REGULAR === $index->getType() && !$existing->hasIndex($index->getObjectName()->toString())) {
                        $modified->addIndex($index);
                    }
                }
            });
        }

        return $editor->create();
    }

    /**
     * @return list<Table>
     */
    private static function tables(bool $binary): array
    {
        return [
            self::table(self::LISTS, [
                self::key('id', 36, $binary), self::string('site', 64, false, $binary), self::string('list_key', 64, false, $binary), self::string('name', 120, false, $binary),
                self::column('double_opt_in', Types::SMALLINT), self::string('sender_profile', 64, false, $binary), self::column('created_at', Types::BIGINT),
            ], ['id'], unique: [['site', 'list_key']]),
            self::table(self::SUBSCRIBERS, [
                self::key('id', 36, $binary), self::string('site', 64, false, $binary), self::string('email', 254, false, $binary), self::string('email_key', 254, false, $binary),
                self::string('external_id', 191, true, $binary), self::string('status', 16, false, $binary), self::column('created_at', Types::BIGINT),
                self::column('confirmed_at', Types::BIGINT, true), self::column('paused_until', Types::BIGINT, true), self::column('soft_bounces', Types::INTEGER),
            ], ['id'], [['external_id']], [['site', 'email_key']]),
            self::table(self::MEMBERSHIPS, [
                self::key('subscriber_id', 36, $binary), self::key('list_id', 36, $binary), self::string('status', 16, false, $binary), self::column('changed_at', Types::BIGINT),
            ], ['subscriber_id', 'list_id'], [['list_id', 'status', 'subscriber_id']]),
            self::table(self::CAMPAIGNS, [
                self::key('id', 36, $binary), self::string('site', 64, false, $binary), self::string('list_id', 36, false, $binary), self::string('subject', 200, false, $binary), self::column('body', Types::TEXT),
                self::string('preheader', 255, false, $binary), self::string('segment', 64, true, $binary), self::string('template', 64, true, $binary), self::string('sender_profile', 64, true, $binary),
                self::string('click_tracking', 16, false, $binary), self::string('status', 16, false, $binary), self::column('scheduled_at', Types::BIGINT, true), self::column('sent_at', Types::BIGINT, true),
                self::string('author', 191, true, $binary), self::string('approved_by', 191, true, $binary), self::column('approved_at', Types::BIGINT, true),
            ], ['id'], [['status', 'scheduled_at']]),
            self::table(self::LINKS, [
                self::key('campaign_id', 36, $binary), self::column('link_index', Types::INTEGER), self::string('url_hash', 64, false, $binary), self::column('url', Types::TEXT),
            ], ['campaign_id', 'link_index'], unique: [['campaign_id', 'url_hash']]),
            self::table(self::DELIVERIES, [
                self::key('id', 32, $binary), self::string('subscriber_id', 36, false, $binary), self::string('campaign_id', 36, true, $binary), self::string('template', 64, true, $binary),
                self::string('status', 16, false, $binary), self::column('attempts', Types::INTEGER), self::column('created_at', Types::BIGINT), self::column('sent_at', Types::BIGINT, true),
                self::string('run_id', 32, true, $binary),
            ], ['id'], [['campaign_id', 'status'], ['subscriber_id'], ['run_id', 'status', 'id']]),
            self::table(self::TEMPLATES, [
                self::key('site', 64, $binary), self::key('template_key', 64, $binary), self::string('subject', 200, false, $binary), self::column('source', Types::TEXT), self::column('updated_at', Types::BIGINT),
            ], ['site', 'template_key']),
            self::table(self::CLICKS, [
                self::key('campaign_id', 36, $binary), self::column('link_index', Types::INTEGER), self::column('clicks', Types::BIGINT),
            ], ['campaign_id', 'link_index']),
            self::table(self::RECIPIENT_CLICKS, [
                self::key('subscriber_id', 36, $binary), self::key('campaign_id', 36, $binary), self::column('link_index', Types::INTEGER), self::column('clicked_at', Types::BIGINT),
            ], ['subscriber_id', 'campaign_id', 'link_index', 'clicked_at']),
            self::table(self::REASONS, [
                self::key('site', 64, $binary), self::key('list_id', 36, $binary), self::key('reason', 32, $binary), self::column('day', Types::BIGINT), self::column('total', Types::BIGINT),
            ], ['site', 'list_id', 'reason', 'day']),
            self::table(self::SUPPRESSIONS, [
                self::key('site', 64, $binary), self::key('email_hash', 64, $binary), self::string('reason', 16, false, $binary), self::column('created_at', Types::BIGINT),
            ], ['site', 'email_hash']),
            self::table(self::SEND_RUNS, [
                self::key('id', 32, $binary), self::string('campaign_id', 36, false, $binary), self::column('run_number', Types::INTEGER), self::string('status', 16, false, $binary),
                self::string('pause_reason', 32, true, $binary), self::column('queued', Types::INTEGER), self::column('sent', Types::INTEGER), self::column('failed', Types::INTEGER),
                self::column('skipped', Types::INTEGER), self::column('bounced', Types::INTEGER), self::column('complained', Types::INTEGER), self::column('base_sent', Types::INTEGER),
                self::column('base_failed', Types::INTEGER), self::column('base_bounced', Types::INTEGER), self::column('base_complained', Types::INTEGER), self::column('started_at', Types::BIGINT),
                self::column('queued_at', Types::BIGINT, true), self::column('paused_at', Types::BIGINT, true), self::column('ended_at', Types::BIGINT, true),
            ], ['id'], unique: [['campaign_id', 'run_number']]),
        ];
    }

    /**
     * @param non-empty-string                       $name
     * @param list<Column>                           $columns
     * @param non-empty-list<non-empty-string>       $primary
     * @param list<non-empty-list<non-empty-string>> $indexes
     * @param list<non-empty-list<non-empty-string>> $unique
     */
    private static function table(string $name, array $columns, array $primary, array $indexes = [], array $unique = []): Table
    {
        $editor = Table::editor()
            ->setUnquotedName($name)
            ->setColumns(...$columns)
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames(...$primary)->create());

        foreach ($indexes as $i => $columnNames) {
            $editor->addIndex(Index::editor()->setUnquotedName($name.'_i'.$i)->setUnquotedColumnNames(...$columnNames)->create());
        }

        foreach ($unique as $i => $columnNames) {
            $editor->addUniqueConstraint(UniqueConstraint::editor()->setUnquotedName($name.'_u'.$i)->setUnquotedColumnNames(...$columnNames)->create());
        }

        return $editor->create();
    }

    /**
     * A column of a primary key, which no platform lets hold a null.
     *
     * @param non-empty-string $name
     */
    private static function key(string $name, int $length, bool $binary): Column
    {
        return self::string($name, $length, false, $binary);
    }

    /**
     * @param non-empty-string $name
     */
    private static function string(string $name, int $length, bool $nullable, bool $binary): Column
    {
        $column = Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->setNotNull(!$nullable);

        if ($binary && \in_array($name, self::BINARY_COLUMNS, true)) {
            $column->setCharset('utf8mb4')->setCollation('utf8mb4_bin');
        }

        return $column->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function column(string $name, string $type, bool $nullable = false): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName($type)->setNotNull(!$nullable)->create();
    }
}
