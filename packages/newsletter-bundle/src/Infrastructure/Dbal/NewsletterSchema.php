<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Dbal;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\UniqueConstraint;
use Doctrine\DBAL\Types\Types;

/**
 * The tables of the newsletter, added to a schema that lacks them: the one a migration builds, or the one the ORM
 * generates, through `NewsletterSchemaListener`. Instants are Unix timestamps.
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

    public const array TABLES = [self::LISTS, self::SUBSCRIBERS, self::MEMBERSHIPS, self::CAMPAIGNS, self::LINKS, self::DELIVERIES, self::TEMPLATES, self::CLICKS, self::RECIPIENT_CLICKS, self::REASONS, self::SUPPRESSIONS];

    public static function configure(Schema $schema): Schema
    {
        $editor = $schema->edit();

        foreach (self::tables() as $table) {
            if (!$schema->hasTable($table->getObjectName()->toString())) {
                $editor->addTable($table);
            }
        }

        return $editor->create();
    }

    /**
     * @return list<Table>
     */
    private static function tables(): array
    {
        return [
            self::table(self::LISTS, [
                self::string('id', 36), self::string('site', 64), self::string('list_key', 64), self::string('name', 120),
                self::column('double_opt_in', Types::SMALLINT), self::string('sender_profile', 64), self::column('created_at', Types::BIGINT),
            ], ['id'], unique: [['site', 'list_key']]),
            self::table(self::SUBSCRIBERS, [
                self::string('id', 36), self::string('site', 64), self::string('email', 254), self::string('email_key', 254),
                self::string('external_id', 191, true), self::string('status', 16), self::column('created_at', Types::BIGINT),
                self::column('confirmed_at', Types::BIGINT, true), self::column('paused_until', Types::BIGINT, true), self::column('soft_bounces', Types::INTEGER),
            ], ['id'], [['external_id']], [['site', 'email_key']]),
            self::table(self::MEMBERSHIPS, [
                self::string('subscriber_id', 36), self::string('list_id', 36), self::string('status', 16), self::column('changed_at', Types::BIGINT),
            ], ['subscriber_id', 'list_id'], [['list_id', 'status', 'subscriber_id']]),
            self::table(self::CAMPAIGNS, [
                self::string('id', 36), self::string('site', 64), self::string('list_id', 36), self::string('subject', 200), self::column('body', Types::TEXT),
                self::string('preheader', 255), self::string('segment', 64, true), self::string('template', 64, true), self::string('sender_profile', 64, true),
                self::string('click_tracking', 16), self::string('status', 16), self::column('scheduled_at', Types::BIGINT, true), self::column('sent_at', Types::BIGINT, true),
            ], ['id'], [['status', 'scheduled_at']]),
            self::table(self::LINKS, [
                self::string('campaign_id', 36), self::column('link_index', Types::INTEGER), self::string('url_hash', 64), self::column('url', Types::TEXT),
            ], ['campaign_id', 'link_index'], unique: [['campaign_id', 'url_hash']]),
            self::table(self::DELIVERIES, [
                self::string('id', 32), self::string('subscriber_id', 36), self::string('campaign_id', 36, true), self::string('template', 64, true),
                self::string('status', 16), self::column('attempts', Types::INTEGER), self::column('created_at', Types::BIGINT), self::column('sent_at', Types::BIGINT, true),
            ], ['id'], [['campaign_id', 'status'], ['subscriber_id']]),
            self::table(self::TEMPLATES, [
                self::string('site', 64), self::string('template_key', 64), self::string('subject', 200), self::column('source', Types::TEXT), self::column('updated_at', Types::BIGINT),
            ], ['site', 'template_key']),
            self::table(self::CLICKS, [
                self::string('campaign_id', 36), self::column('link_index', Types::INTEGER), self::column('clicks', Types::BIGINT),
            ], ['campaign_id', 'link_index']),
            self::table(self::RECIPIENT_CLICKS, [
                self::string('subscriber_id', 36), self::string('campaign_id', 36), self::column('link_index', Types::INTEGER), self::column('clicked_at', Types::BIGINT),
            ], ['subscriber_id', 'campaign_id', 'link_index', 'clicked_at']),
            self::table(self::REASONS, [
                self::string('site', 64), self::string('list_id', 36), self::string('reason', 32), self::column('day', Types::BIGINT), self::column('total', Types::BIGINT),
            ], ['site', 'list_id', 'reason', 'day']),
            self::table(self::SUPPRESSIONS, [
                self::string('site', 64), self::string('email_hash', 64), self::string('reason', 16), self::column('created_at', Types::BIGINT),
            ], ['site', 'email_hash']),
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
     * @param non-empty-string $name
     */
    private static function string(string $name, int $length, bool $nullable = false): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->setNotNull(!$nullable)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function column(string $name, string $type, bool $nullable = false): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName($type)->setNotNull(!$nullable)->create();
    }
}
