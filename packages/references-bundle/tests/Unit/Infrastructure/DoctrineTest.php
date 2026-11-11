<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\ReferencesBundle\Domain\ValueObject\Usage;
use Phil\ReferencesBundle\Infrastructure\Doctrine\DbalUsageRepository;
use Phil\ReferencesBundle\Infrastructure\Doctrine\UsageSchemaListener;
use Phil\ReferencesBundle\Infrastructure\Doctrine\UsageTable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversClass(DbalUsageRepository::class)]
#[CoversClass(UsageTable::class)]
#[CoversClass(UsageSchemaListener::class)]
final class DoctrineTest extends TestCase
{
    public function testUsagesAreReplacedFoundAndForgotten(): void
    {
        $connection = $this->connection();
        $usages = new DbalUsageRepository($connection);
        $page = ResourceReference::of('page', '1');
        $other = ResourceReference::of('page', '2');
        $image = ResourceReference::of('image', '9');

        $usages->replace($page, [Usage::of($page, 'hero', $image), Usage::of($page, 'hero', $image), Usage::of($page, 'blocks.1', ResourceReference::of('image', '8'))]);
        $usages->replace($other, [Usage::of($other, 'avatar', $image)]);

        self::assertSame(['page:1 hero', 'page:2 avatar'], array_map(static fn (Usage $usage): string => $usage->source->toString().' '.$usage->field, $usages->usagesOf($image)));
        self::assertSame($image, $usages->usagesOf($image)[0]->target);
        self::assertSame(['image:8', 'image:9'], array_map(static fn (ResourceReference $target): string => $target->toString(), iterator_to_array($usages->targets(), false)));

        $usages->replace($page, [Usage::of($page, 'hero', ResourceReference::of('image', '7'))]);
        self::assertSame(['page:2 avatar'], array_map(static fn (Usage $usage): string => $usage->source->toString().' '.$usage->field, $usages->usagesOf($image)));

        $usages->forget($other);
        self::assertSame([], $usages->usagesOf($image));
        self::assertSame(['image:7'], array_map(static fn (ResourceReference $target): string => $target->toString(), iterator_to_array($usages->targets(), false)));
        self::assertEquals(1, $connection->fetchOne('SELECT COUNT(*) FROM phil_reference_usage'));

        $post = ResourceReference::of('post', '1');
        $usages->replace($post, [Usage::of($post, 'cover', ResourceReference::of('image', '7'))]);
        $usages->forget(ResourceReference::of('page', '1'));
        self::assertSame(['post:1'], array_map(static fn (Usage $usage): string => $usage->source->toString(), $usages->usagesOf(ResourceReference::of('image', '7'))));

        // Two sources of different kinds with the same identifier each keep their own usage of the same target.
        $usages->replace($page, [Usage::of($page, 'cover', ResourceReference::of('image', '7'))]);
        self::assertSame(['page:1', 'post:1'], array_map(static fn (Usage $usage): string => $usage->source->toString(), $usages->usagesOf(ResourceReference::of('image', '7'))));
    }

    public function testTheUsagesOfManyTargetsAreReadTargetAfterTarget(): void
    {
        $usages = new DbalUsageRepository($this->connection());
        $page = ResourceReference::of('page', '1');
        $post = ResourceReference::of('post', '1');
        $usages->replace($page, [Usage::of($page, 'hero', ResourceReference::of('image', '9')), Usage::of($page, 'body', ResourceReference::of('image', '9')), Usage::of($page, 'link', ResourceReference::of('page', '9'))]);
        $usages->replace($post, [Usage::of($post, 'cover', ResourceReference::of('image', '9')), Usage::of($post, 'cover', ResourceReference::of('image', '1'))]);

        $nine = ResourceReference::of('image', '9');

        self::assertSame(
            ['page:9 page:1 link', 'image:9 page:1 body', 'image:9 page:1 hero', 'image:9 post:1 cover', 'image:1 post:1 cover'],
            $this->described($usages->usagesOfAll([ResourceReference::of('page', '9'), $nine, ResourceReference::of('image', '404'), ResourceReference::of('image', '1')])),
        );
        self::assertSame($nine, $usages->usagesOfAll([$nine])[0]->target);
        self::assertSame([], $usages->usagesOfAll([]));

        $many = [];

        for ($i = 0; $i < 1200; ++$i) {
            $many[] = ResourceReference::of('image', 'n'.$i);
            $usages->replace(ResourceReference::of('note', (string) $i), [Usage::of(ResourceReference::of('note', (string) $i), 'image', ResourceReference::of('image', 'n'.$i))]);
        }

        $found = $usages->usagesOfAll(array_reverse($many));
        self::assertCount(1200, $found, 'read in several chunks');
        self::assertSame(['image:n1199 note:1199 image', 'image:n0 note:0 image'], $this->described([$found[0], $found[1199]]));
    }

    public function testAReplacementWritesOnlyWhatChanged(): void
    {
        $connection = $this->connection();
        $usages = new DbalUsageRepository($connection);
        $page = ResourceReference::of('page', '1');
        $hero = Usage::of($page, 'hero', ResourceReference::of('image', '9'));
        $usages->replace($page, [Usage::of($page, 'blocks.1', ResourceReference::of('image', '8')), $hero]);
        $rowid = static fn (): mixed => $connection->fetchOne("SELECT rowid FROM phil_reference_usage WHERE field = 'hero'");
        $before = $rowid();

        $usages->replace($page, [$hero, Usage::of($page, 'blocks.2', ResourceReference::of('image', '7')), $hero]);

        self::assertSame($before, $rowid(), 'the usage kept is not written again');
        self::assertSame(['blocks.2', 'hero'], $connection->fetchFirstColumn('SELECT field FROM phil_reference_usage ORDER BY field'));
        self::assertSame(UsageTable::keyOf('page', '1', 'hero', 'image', '9'), $connection->fetchOne("SELECT usage_key FROM phil_reference_usage WHERE field = 'hero'"));
        self::assertNotSame(UsageTable::keyOf('page', '1:x', 'y', 'image', '9'), UsageTable::keyOf('page', '1', 'x:y', 'image', '9'));
    }

    public function testAColumnThatIsNotTextIsEmpty(): void
    {
        $text = new ReflectionMethod(DbalUsageRepository::class, 'text');

        self::assertSame(['x', ''], [$text->invoke(null, 'x'), $text->invoke(null, 1)]);
    }

    public function testTheTableJoinsTheSchemaOfItsConnection(): void
    {
        $connection = $this->connection(false);
        $listener = new UsageSchemaListener($connection);
        $schema = new Schema();

        $elsewhere = new GenerateSchemaEventArgs($this->entityManager($this->connection(false)), $schema);
        $listener->postGenerateSchema($elsewhere);
        self::assertSame($schema, $elsewhere->getSchema(), 'the schema of another connection is left as it is');
        self::assertFalse($elsewhere->getSchema()->hasTable(UsageTable::NAME));

        $event = new GenerateSchemaEventArgs($this->entityManager($connection), $schema);

        $listener->postGenerateSchema($event);

        $schema = $event->getSchema();
        $table = $schema->getTable(UsageTable::NAME);
        self::assertSame([[64, 'string'], [64, 'string'], [255, 'string'], [255, 'string'], [64, 'string'], [255, 'string']], array_map(static fn (string $name): array => [$table->getColumn($name)->getLength(), Type::lookupName($table->getColumn($name)->getType())], ['usage_key', 'source_kind', 'source_id', 'field', 'target_kind', 'target_id']));
        self::assertTrue($table->getColumn('usage_key')->getFixed());
        self::assertSame(['usage_key'], array_map(static fn (object $name): string => $name->toString(), $table->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame(['target_kind', 'target_id'], $table->getIndex('phil_reference_usage_target')->getColumns());
        self::assertSame(['source_kind', 'source_id'], $table->getIndex('phil_reference_usage_source')->getColumns());

        // MySQL's InnoDB refuses a key longer than 3072 bytes; utf8mb4 counts 4 bytes a character.
        foreach ([['usage_key'], ...array_map(static fn (object $index): array => $index->getColumns(), array_values($table->getIndexes()))] as $columns) {
            self::assertLessThanOrEqual(3072, 4 * array_sum(array_map(static fn (string $name): int => $table->getColumn($name)->getLength() ?? 0, $columns)), implode(', ', $columns));
        }

        self::assertSame($schema, UsageTable::configure($schema));
        self::assertCount(1, $schema->getTables());
    }

    private function connection(bool $withTable = true): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        if ($withTable) {
            foreach ($connection->getDatabasePlatform()->getCreateTablesSQL(UsageTable::configure(new Schema())->getTables()) as $sql) {
                $connection->executeStatement($sql);
            }
        }

        return $connection;
    }

    private function entityManager(Connection $connection): EntityManagerInterface
    {
        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return $entityManager;
    }

    /**
     * @param list<Usage> $usages
     *
     * @return list<string> each usage's target, source and field
     */
    private function described(array $usages): array
    {
        return array_map(static fn (Usage $usage): string => $usage->target->toString().' '.$usage->source->toString().' '.$usage->field, $usages);
    }
}
