<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Phil\NewsletterArchive\Domain\Model\ArchivedIssue;
use Phil\NewsletterArchive\Domain\ValueObject\ArchiveFilter;
use Phil\NewsletterArchive\Domain\ValueObject\Tag;
use Phil\NewsletterArchive\Infrastructure\Dbal\ArchiveSchema;
use Phil\NewsletterArchive\Infrastructure\Dbal\DbalArchiveRepository;
use Phil\NewsletterArchive\Tests\Fixture\CountingConnection;
use Phil\NewsletterArchive\Tests\Fixture\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use UnexpectedValueException;

/**
 * The issues and their tags on SQLite: written and read back whole, in UTC; a page of the published issues of a site,
 * newest sent first, filtered by list and tag, counted; slugs taken per site; links found by their hash.
 */
#[CoversClass(DbalArchiveRepository::class)]
#[CoversClass(ArchiveSchema::class)]
final class DbalArchiveRepositoryTest extends TestCase
{
    private const string HASH = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private Stage $stage;

    protected function setUp(): void
    {
        $this->stage = new Stage();
    }

    public function testAnIssueIsReadBackWhole(): void
    {
        $issue = $this->issue('c1', 'hello', '2026-11-01 09:30:00', 'news', ['b-tag', 'a-tag']);
        $issue->publish(new DateTimeImmutable('2026-11-02 10:00:00', new DateTimeZone('America/Toronto')));
        $issue->share(self::HASH, new DateTimeImmutable('2026-11-03 11:00:00 UTC'));

        $this->stage->issues->save($issue);

        $read = $this->stage->issues->get('c1');
        self::assertInstanceOf(ArchivedIssue::class, $read);
        self::assertSame(['c1', 'default', 'hello', 'l-news', 'news', 'List news', 'Subject hello', 'Preheader', '<p>hello</p>'], [
            $read->campaignId, $read->site, $read->slug, $read->listId, $read->listKey, $read->listName, $read->subject, $read->preheader, $read->html,
        ]);
        self::assertSame(['a-tag', 'b-tag'], $read->tagValues());
        self::assertSame('2026-11-01T09:30:00+00:00', $read->sentAt->format(\DATE_ATOM));
        self::assertSame('2026-11-01T09:30:00+00:00', $read->renderedAt->format(\DATE_ATOM));
        self::assertSame('2026-11-02T15:00:00+00:00', $read->publishedAt?->format(\DATE_ATOM));
        self::assertSame(self::HASH, $read->linkHash);
        self::assertSame('2026-11-03T11:00:00+00:00', $read->linkIssuedAt?->format(\DATE_ATOM));
        self::assertSame('c1', $this->stage->issues->byLinkHash(self::HASH)?->campaignId);

        $read->unpublish();
        $read->retag([Tag::of('c-tag')]);

        $this->stage->issues->save($read);
        $again = $this->stage->issues->get('c1');
        self::assertNull($again?->publishedAt);
        self::assertNull($again?->linkHash);
        self::assertNull($again?->linkIssuedAt);
        self::assertSame(['c-tag'], $again?->tagValues());
        self::assertNull($this->stage->issues->byLinkHash(self::HASH));
        self::assertNull($this->stage->issues->get('none'));
        self::assertEquals(1, $this->stage->connection->fetchOne('SELECT COUNT(*) FROM '.ArchiveSchema::ISSUES));
    }

    public function testAPublishedIssueIsFoundAtItsSlugOnItsSite(): void
    {
        $published = $this->issue('c1', 'hello', '2026-11-01');
        $published->publish(new DateTimeImmutable());

        $this->stage->issues->save($published);
        $this->stage->issues->save($this->issue('c2', 'unlisted', '2026-11-01'));

        self::assertSame('c1', $this->stage->issues->published('default', 'hello')?->campaignId);
        self::assertNull($this->stage->issues->published('other', 'hello'));
        self::assertNull($this->stage->issues->published('default', 'unlisted'));
        self::assertTrue($this->stage->issues->slugTaken('default', 'unlisted'));
        self::assertTrue($this->stage->issues->slugTaken('default', 'hello'));
        self::assertFalse($this->stage->issues->slugTaken('other', 'hello'));
        self::assertFalse($this->stage->issues->slugTaken('default', 'none'));
    }

    public function testAPageIsFilteredSortedAndCounted(): void
    {
        $this->publish($this->issue('c1', 'one', '2026-11-01', 'news', ['games']));
        $this->publish($this->issue('c2', 'two', '2026-11-03', 'events', ['games', 'news']));
        $this->publish($this->issue('c3', 'three', '2026-11-02', 'news'));
        $this->publish($this->issue('c4', 'four', '2026-11-02', 'news', ['games']));
        $this->publish($this->issue('c5', 'five', '2026-11-04', 'news', [], 'other'));
        $this->stage->issues->save($this->issue('c6', 'six', '2026-11-05', 'news', ['games']));

        $all = $this->stage->issues->page(new ArchiveFilter('default'));
        self::assertSame(['c2', 'c4', 'c3', 'c1'], $this->ids($all->issues));
        self::assertSame(4, $all->total);
        self::assertSame(['games', 'news'], $all->issues[0]->tagValues());
        self::assertSame([], $all->issues[2]->tagValues());

        self::assertSame(['c4', 'c3', 'c1'], $this->ids($this->stage->issues->page(new ArchiveFilter('default', 'news'))->issues));
        self::assertSame(['c2', 'c4', 'c1'], $this->ids($this->stage->issues->page(new ArchiveFilter('default', null, Tag::of('games')))->issues));
        self::assertSame(['c4', 'c1'], $this->ids($this->stage->issues->page(new ArchiveFilter('default', 'news', Tag::of('games')))->issues));
        self::assertSame(2, $this->stage->issues->page(new ArchiveFilter('default', 'news', Tag::of('games')))->total);

        $second = $this->stage->issues->page(new ArchiveFilter('default', page: 2, pageSize: 3));
        self::assertSame(['c1'], $this->ids($second->issues));
        self::assertSame(4, $second->total);

        $beyond = $this->stage->issues->page(new ArchiveFilter('default', page: 3, pageSize: 3));
        self::assertSame([], $beyond->issues);
        self::assertSame(4, $beyond->total);

        self::assertSame(['events' => 'List events', 'news' => 'List news'], $this->stage->issues->lists('default'));
        self::assertSame(['games', 'news'], $this->stage->issues->tags('default'));
        self::assertSame(['news' => 'List news'], $this->stage->issues->lists('other'));
    }

    public function testAnIssueWithoutAListIsNotAFilter(): void
    {
        $this->publish($this->issue('c1', 'one', '2026-11-01', ''));

        self::assertSame([], $this->stage->issues->lists('default'));
    }

    public function testAnEmptyPageAsksTheStoreForItsCountAlone(): void
    {
        $stage = new Stage(120, CountingConnection::class);
        $connection = $stage->connection;
        self::assertInstanceOf(CountingConnection::class, $connection);

        $connection->queries = 0;

        self::assertSame(0, $stage->issues->page(new ArchiveFilter('default'))->total);
        self::assertSame(1, $connection->queries);

        $issue = $this->issue('c1', 'one', '2026-11-01');
        $issue->publish(new DateTimeImmutable());

        $stage->issues->save($issue);
        $connection->queries = 0;

        self::assertSame([], $stage->issues->page(new ArchiveFilter('default', null, null, 2, 1))->issues);
        self::assertSame(1, $connection->queries);

        self::assertCount(1, $stage->issues->page(new ArchiveFilter('default'))->issues);
        self::assertSame(1 + 2 + 1, $connection->queries);
    }

    public function testTwoNamesOfOneListGiveTheFirstInOrder(): void
    {
        $this->publish($this->issue('c1', 'one', '2026-11-01', 'news', [], 'default', 'B name'));
        $this->publish($this->issue('c2', 'two', '2026-11-02', 'news', [], 'default', 'A name'));

        self::assertSame(['news' => 'A name'], $this->stage->issues->lists('default'));
    }

    public function testTagsOfTheStoreAreStrings(): void
    {
        $connection = self::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn([123]);

        $this->expectException(UnexpectedValueException::class);
        new DbalArchiveRepository($connection)->tags('default');
    }

    public function testARowWithoutTheDateItWasSentAtIsRefused(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE '.ArchiveSchema::ISSUES.' (campaign_id TEXT, site TEXT, slug TEXT, list_id TEXT, list_key TEXT, list_name TEXT, subject TEXT, preheader TEXT, html TEXT, sent_at TEXT, rendered_at TEXT, published_at TEXT, link_hash TEXT, link_issued_at TEXT)');
        $connection->executeStatement('CREATE TABLE '.ArchiveSchema::TAGS.' (campaign_id TEXT, tag TEXT)');

        $repository = new DbalArchiveRepository($connection);

        $connection->insert(ArchiveSchema::ISSUES, ['campaign_id' => 'c1', 'site' => 's', 'slug' => 'x', 'list_id' => 'l', 'list_key' => '', 'list_name' => '', 'subject' => 'S', 'preheader' => '', 'html' => '', 'sent_at' => null, 'rendered_at' => '2026-11-01 00:00:00']);

        try {
            $repository->get('c1');
            self::fail('A row without a date was read.');
        } catch (UnexpectedValueException $unexpectedValueException) {
            self::assertSame('An archived issue has a date it was sent.', $unexpectedValueException->getMessage());
        }

        $connection->update(ArchiveSchema::ISSUES, ['sent_at' => '2026-11-01 00:00:00', 'rendered_at' => null], ['campaign_id' => 'c1']);

        $this->expectExceptionMessageIsOrContains('An archived issue has a date it was rendered.');
        $repository->get('c1');
    }

    public function testACountIsAWholeNumber(): void
    {
        $integer = new ReflectionMethod(DbalArchiveRepository::class, 'integer');
        $repository = $this->stage->issues;

        self::assertSame(5, $integer->invoke($repository, 5));
        self::assertSame(5, $integer->invoke($repository, '5'));

        foreach (['abc', '', 5.5, null, true] as $value) {
            try {
                $integer->invoke($repository, $value);
                self::fail('A count of '.var_export($value, true).' was accepted.');
            } catch (UnexpectedValueException) {
                self::assertTrue(true);
            }
        }
    }

    public function testTheSchemaAddsWhatIsMissingOnlyOnce(): void
    {
        $manager = $this->stage->connection->createSchemaManager();
        $schema = $manager->introspectSchema();

        self::assertTrue($schema->hasTable(ArchiveSchema::ISSUES));
        self::assertTrue($schema->hasTable(ArchiveSchema::TAGS));
        self::assertTrue($manager->createComparator()->compareSchemas($schema, ArchiveSchema::configure($schema))->isEmpty());
        self::assertSame([ArchiveSchema::ISSUES, ArchiveSchema::TAGS], ArchiveSchema::TABLES);
    }

    public function testKeysCompareByteByByteOnMySql(): void
    {
        $schema = ArchiveSchema::configure(Schema::editor()->create(), new MySQLPlatform());
        $issues = $schema->getTable(ArchiveSchema::ISSUES);

        self::assertSame('utf8mb4_bin', $issues->getColumn('slug')->getCollation());
        self::assertSame('utf8mb4_bin', $issues->getColumn('link_hash')->getCollation());
        self::assertNull($issues->getColumn('subject')->getCollation());
        self::assertSame('utf8mb4_bin', $schema->getTable(ArchiveSchema::TAGS)->getColumn('tag')->getCollation());
        self::assertNull(ArchiveSchema::configure(Schema::editor()->create())->getTable(ArchiveSchema::ISSUES)->getColumn('slug')->getCollation());
        self::assertSame('datetime_utc_immutable', $issues->getColumn('sent_at')->getTypeName());
        self::assertFalse($issues->getColumn('published_at')->getNotnull());
        self::assertTrue($issues->getColumn('sent_at')->getNotnull());
    }

    private function publish(ArchivedIssue $issue): void
    {
        $issue->publish(new DateTimeImmutable());
        $this->stage->issues->save($issue);
    }

    /**
     * @param list<string> $tags
     */
    private function issue(string $id, string $slug, string $sentAt, string $list = 'news', array $tags = [], string $site = 'default', ?string $listName = null): ArchivedIssue
    {
        $at = new DateTimeImmutable($sentAt.' UTC');

        return new ArchivedIssue($id, $site, $slug, 'l-'.$list, $list, $listName ?? ('' === $list ? '' : 'List '.$list), 'Subject '.$slug, 'Preheader', '<p>'.$slug.'</p>', $at, $at, Tag::list($tags));
    }

    /**
     * @param list<ArchivedIssue> $issues
     *
     * @return list<string>
     */
    private function ids(array $issues): array
    {
        return array_map(static fn (ArchivedIssue $issue): string => $issue->campaignId, $issues);
    }
}
