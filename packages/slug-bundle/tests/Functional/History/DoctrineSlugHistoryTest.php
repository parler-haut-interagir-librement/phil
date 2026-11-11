<?php

declare(strict_types=1);

namespace Phil\SlugBundle\Tests\Functional\History;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\Slug\Slug;
use Phil\SlugBundle\Application\Contract\SlugHistory;
use Phil\SlugBundle\Domain\Exception\InvalidSlugHistory;
use Phil\SlugBundle\Infrastructure\Doctrine\DoctrineSlugHistory;
use Phil\SlugBundle\Infrastructure\Doctrine\SlugHistorySchemaListener;
use Phil\SlugBundle\Infrastructure\Doctrine\SlugHistoryTable;
use Phil\SlugBundle\Tests\Functional\HistoryApp\HistoryKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * The slug history on a real connection, wired by the bundle: the changes, the lookups, the chains it never keeps, and
 * the table added to the schema the ORM generates.
 */
#[CoversClass(DoctrineSlugHistory::class)]
#[CoversClass(SlugHistoryTable::class)]
#[CoversClass(SlugHistorySchemaListener::class)]
final class DoctrineSlugHistoryTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return HistoryKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $connection = $this->connection();
        $schemaManager = $connection->createSchemaManager();

        if ($schemaManager->tablesExist([SlugHistoryTable::NAME])) {
            $schemaManager->dropTable(SlugHistoryTable::NAME);
        }

        $table = $this->generatedSchema()->getTable(SlugHistoryTable::NAME);
        $schemaManager->createTable($table);
    }

    public function testTheSchemaToolGeneratesTheTable(): void
    {
        $table = $this->generatedSchema()->getTable(SlugHistoryTable::NAME);

        foreach (['scope' => 100, 'former_slug' => 255, 'subject' => 191, 'current_slug' => 255, 'recorded_at' => null] as $column => $length) {
            self::assertTrue($table->getColumn($column)->getNotnull(), $column.' is required');
            self::assertSame($length, $table->getColumn($column)->getLength(), $column);
        }

        $primaryKey = $table->getPrimaryKeyConstraint();
        self::assertNotNull($primaryKey);
        self::assertSame(['scope', 'former_slug'], array_map(static fn (UnqualifiedName $name): string => $name->toString(), $primaryKey->getColumnNames()));
        self::assertTrue($table->hasIndex('phil_slug_history_subject'));
    }

    public function testATableTheSchemaAlreadyHasIsLeftAsItIs(): void
    {
        $schema = $this->generatedSchema();

        self::assertSame($schema, SlugHistoryTable::configure($schema));
    }

    public function testTheBundleWiresTheHistory(): void
    {
        self::assertInstanceOf(DoctrineSlugHistory::class, self::getContainer()->get(HistoryKernel::HISTORY));
    }

    public function testAChangeIsLookedUpByItsFormerSlug(): void
    {
        $history = $this->history();
        $history->record('page', '7', $this->slug('old-title'), $this->slug('new-title'));

        self::assertSame('new-title', $history->currentSlug('page', $this->slug('old-title'))?->value);
        self::assertNull($history->currentSlug('page', $this->slug('new-title')));
        self::assertNull($history->currentSlug('post', $this->slug('old-title')), 'another scope');
        self::assertSame(['old-title' => 'new-title'], $this->formerSlugs($history, 'page'));
        self::assertSame([], $this->formerSlugs($history, 'post'));

        $row = $this->connection()->fetchAssociative('SELECT subject, recorded_at FROM '.SlugHistoryTable::NAME);
        self::assertIsArray($row);
        self::assertSame('7', $row['subject']);
        self::assertIsString($row['recorded_at']);
        self::assertStringStartsWith(HistoryKernel::NOW, $row['recorded_at']);
    }

    public function testASlugOfDigitsStaysAString(): void
    {
        $history = $this->history();
        $history->record('page', '7', $this->slug('2024'), $this->slug('2025'));

        foreach ($history->formerSlugs('page') as $former => $current) {
            self::assertSame(['2024', '2025'], [$former, $current->value]);
        }

        self::assertSame('2025', $history->currentSlug('page', $this->slug('2024'))?->value);
    }

    public function testASlugRecordedAsItsOwnFormerSlugChangesNothing(): void
    {
        $history = $this->history();
        $history->record('page', '7', $this->slug('same'), $this->slug('same'));

        self::assertSame([], $this->formerSlugs($history, 'page'));
    }

    /**
     * a → b → c: both former slugs point to c, so a redirect never chains.
     */
    public function testEveryFormerSlugPointsToTheNewest(): void
    {
        $history = $this->history();
        $history->record('page', '7', $this->slug('a'), $this->slug('b'));
        $history->record('page', '7', $this->slug('b'), $this->slug('c'));

        self::assertSame(['a' => 'c', 'b' => 'c'], $this->formerSlugs($history, 'page'));
    }

    /**
     * a → b → a: a is current again and stops being a former slug; b now points to a.
     */
    public function testASlugThatBecomesCurrentAgainIsNoLongerAFormerOne(): void
    {
        $history = $this->history();
        $history->record('page', '7', $this->slug('a'), $this->slug('b'));
        $history->record('page', '7', $this->slug('b'), $this->slug('a'));

        self::assertSame(['b' => 'a'], $this->formerSlugs($history, 'page'));
    }

    /**
     * Another subject that takes a former slug makes it live again: its old redirect goes.
     */
    public function testAFormerSlugTakenByAnotherSubjectIsNoLongerAFormerOne(): void
    {
        $history = $this->history();
        $history->record('page', '7', $this->slug('news'), $this->slug('archive'));
        $history->record('page', '8', $this->slug('draft'), $this->slug('news'));

        self::assertSame(['draft' => 'news'], $this->formerSlugs($history, 'page'));
    }

    /**
     * A former slug of one subject, given up by a second subject later, points to the second subject.
     */
    public function testAFormerSlugRecordedAgainBelongsToItsLatestSubject(): void
    {
        $history = $this->history();
        $history->record('page', '7', $this->slug('shared'), $this->slug('seven'));
        $history->record('page', '8', $this->slug('eight'), $this->slug('shared'));
        $history->record('page', '8', $this->slug('shared'), $this->slug('eight-bis'));

        self::assertSame(['eight' => 'eight-bis', 'shared' => 'eight-bis'], $this->formerSlugs($history, 'page'));
        self::assertSame('8', $this->connection()->fetchOne('SELECT subject FROM '.SlugHistoryTable::NAME." WHERE former_slug = 'shared'"));
    }

    public function testTheChangesOfOneSubjectLeaveTheOthersAlone(): void
    {
        $history = $this->history();
        $history->record('page', '7', $this->slug('seven-old'), $this->slug('seven'));
        $history->record('page', '8', $this->slug('eight-old'), $this->slug('eight'));
        $history->record('page:fr', '7', $this->slug('sept-ancien'), $this->slug('sept'));
        $history->record('page', '7', $this->slug('seven'), $this->slug('seven-new'));

        self::assertSame(['eight-old' => 'eight', 'seven' => 'seven-new', 'seven-old' => 'seven-new'], $this->formerSlugs($history, 'page'));
        self::assertSame(['sept-ancien' => 'sept'], $this->formerSlugs($history, 'page:fr'));
    }

    public function testForgettingASubjectRemovesItsFormerSlugsOnly(): void
    {
        $history = $this->history();
        $history->record('page', '7', $this->slug('seven-old'), $this->slug('seven'));
        $history->record('page', '8', $this->slug('eight-old'), $this->slug('eight'));
        $history->record('post', '7', $this->slug('post-old'), $this->slug('post'));

        $history->forget('page', '7');

        self::assertSame(['eight-old' => 'eight'], $this->formerSlugs($history, 'page'));
        self::assertSame(['post-old' => 'post'], $this->formerSlugs($history, 'post'));
    }

    /**
     * A row edited by hand into something that is not a slug is not answered.
     */
    public function testAStoredValueThatIsNotASlugIsNotAnswered(): void
    {
        $history = $this->history();
        $history->record('page', '7', $this->slug('good-old'), $this->slug('good'));
        $history->record('page', '8', $this->slug('bad-old'), $this->slug('bad'));
        $history->record('page', '9', $this->slug('worse-old'), $this->slug('worse'));
        $this->connection()->executeStatement('UPDATE '.SlugHistoryTable::NAME." SET current_slug = 'Not A Slug' WHERE subject = '8'");
        $this->connection()->executeStatement('UPDATE '.SlugHistoryTable::NAME." SET former_slug = 'Worse Old' WHERE subject = '9'");

        self::assertNull($history->currentSlug('page', $this->slug('bad-old')));
        self::assertSame(['good-old' => 'good'], $this->formerSlugs($history, 'page'));
    }

    public function testABadScopeOrSubjectIsRefusedBeforeAnyQuery(): void
    {
        $history = $this->history();

        foreach ([
            static fn () => $history->record('bad scope', '7', Slug::fromString('a'), Slug::fromString('b')),
            static fn () => $history->record('page', '', Slug::fromString('a'), Slug::fromString('b')),
            static fn (): ?Slug => $history->currentSlug('', Slug::fromString('a')),
            static fn (): array => iterator_to_array($history->formerSlugs('bad scope')),
            static fn () => $history->forget('page', "7\n"),
            static fn () => $history->forget('bad scope', '7'),
        ] as $i => $call) {
            try {
                $call();
                self::fail(\sprintf('Call %d was not refused.', $i));
            } catch (InvalidSlugHistory) {
                self::addToAssertionCount(1);
            }
        }

        self::assertEquals(0, $this->connection()->fetchOne('SELECT COUNT(*) FROM '.SlugHistoryTable::NAME));
    }

    /**
     * The schema the ORM generates for the connection, with the table the bundle's listener adds.
     */
    private function generatedSchema(): Schema
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return new SchemaTool($entityManager)->getSchemaFromMetadata([]);
    }

    private function history(): SlugHistory
    {
        $history = self::getContainer()->get(HistoryKernel::HISTORY);
        self::assertInstanceOf(SlugHistory::class, $history);

        return $history;
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }

    private function slug(string $value): Slug
    {
        return Slug::fromString($value);
    }

    /**
     * @return array<string, string>
     */
    private function formerSlugs(SlugHistory $history, string $scope): array
    {
        $slugs = [];

        foreach ($history->formerSlugs($scope) as $former => $current) {
            $slugs[$former] = $current->value;
        }

        return $slugs;
    }
}
