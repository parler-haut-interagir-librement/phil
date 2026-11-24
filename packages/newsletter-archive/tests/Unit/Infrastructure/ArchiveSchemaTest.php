<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Phil\NewsletterArchive\Infrastructure\Dbal\ArchiveSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The two tables of the archive, column by column: the length of each, whether it may be empty, and the names and
 * columns of the keys that keep a slug and a link to one issue.
 */
#[CoversClass(ArchiveSchema::class)]
final class ArchiveSchemaTest extends TestCase
{
    public function testTheTableOfTheIssues(): void
    {
        $table = ArchiveSchema::configure(Schema::editor()->create())->getTable(ArchiveSchema::ISSUES);

        self::assertSame([
            'campaign_id' => 'string(36)!',
            'site' => 'string(64)!',
            'slug' => 'string(255)!',
            'list_id' => 'string(36)!',
            'list_key' => 'string(64)!',
            'list_name' => 'string(120)!',
            'subject' => 'string(200)!',
            'preheader' => 'string(255)!',
            'html' => 'text!',
            'sent_at' => 'datetime_utc_immutable!',
            'rendered_at' => 'datetime_utc_immutable!',
            'published_at' => 'datetime_utc_immutable',
            'link_hash' => 'string(64)',
            'link_issued_at' => 'datetime_utc_immutable',
        ], $this->columns($table));
        self::assertSame(['campaign_id'], $this->names($table->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame([
            'phil_newsletter_archive_slug' => ['site', 'slug'],
            'phil_newsletter_archive_link' => ['link_hash'],
        ], $this->uniques($table));
        self::assertSame(['phil_newsletter_archive_published' => ['site', 'published_at', 'sent_at']], $this->secondary($table));
    }

    public function testTheTableOfTheTags(): void
    {
        $table = ArchiveSchema::configure(Schema::editor()->create())->getTable(ArchiveSchema::TAGS);

        self::assertSame(['campaign_id' => 'string(36)!', 'tag' => 'string(64)!'], $this->columns($table));
        self::assertSame(['campaign_id', 'tag'], $this->names($table->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame([], $this->uniques($table));
        self::assertSame(['phil_newsletter_archive_tag_tag' => ['tag', 'campaign_id']], $this->secondary($table));
    }

    /**
     * @return array<string, string>
     */
    private function columns(Table $table): array
    {
        $columns = [];

        foreach ($table->getColumns() as $column) {
            $length = $column->getLength();
            $columns[$column->getObjectName()->toString()] = $column->getTypeName().(null === $length ? '' : '('.$length.')').($column->getNotnull() ? '!' : '');
        }

        return $columns;
    }

    /**
     * @return array<string, list<string>>
     */
    private function uniques(Table $table): array
    {
        $uniques = [];

        foreach ($table->getUniqueConstraints() as $constraint) {
            $uniques[$constraint->getObjectName()?->toString() ?? ''] = $this->names($constraint->getColumnNames());
        }

        return $uniques;
    }

    /**
     * @return array<string, list<string>>
     */
    private function secondary(Table $table): array
    {
        $indexes = [];

        foreach ($table->getIndexes() as $index) {
            if (IndexType::REGULAR === $index->getType()) {
                $indexes[$index->getObjectName()->toString()] = array_map(static fn (IndexedColumn $column): string => $column->getColumnName()->toString(), $index->getIndexedColumns());
            }
        }

        return $indexes;
    }

    /**
     * @param iterable<UnqualifiedName> $names
     *
     * @return list<string>
     */
    private function names(iterable $names): array
    {
        $list = [];

        foreach ($names as $name) {
            $list[] = $name->toString();
        }

        return $list;
    }
}
