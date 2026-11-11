<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Application\Flag\FlagEvaluator;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationErrorCode;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationReason;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\UnreadableFlag;
use Phil\FeatureFlagsBundle\Infrastructure\Doctrine\DoctrineFlagDefinitions;
use Phil\FeatureFlagsBundle\Infrastructure\Doctrine\DoctrineFlagStore;
use Phil\FeatureFlagsBundle\Infrastructure\Doctrine\FlagSchemaListener;
use Phil\FeatureFlagsBundle\Infrastructure\Doctrine\FlagTables;
use Phil\FeatureFlagsBundle\PhilFeatureFlagsBundle;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The database store on a real connection: the conditional writes, the per-request reading, a row that no longer
 * reads, and the tables added to the schema the ORM generates.
 */
#[CoversClass(DoctrineFlagStore::class)]
#[CoversClass(DoctrineFlagDefinitions::class)]
#[CoversClass(FlagSchemaListener::class)]
#[CoversClass(FlagTables::class)]
#[CoversClass(UnreadableFlag::class)]
final class DoctrineStoreTest extends FunctionalTestCase
{
    public function testTheSchemaToolGeneratesTheTwoTables(): void
    {
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $schema = new SchemaTool($entityManager)->getSchemaFromMetadata([]);

        self::assertTrue($schema->hasTable(FlagTables::DEFINITIONS));
        self::assertTrue($schema->hasTable(FlagTables::STATES));

        foreach ([FlagTables::DEFINITIONS => ['name', 'definition', 'revision', 'updated_at'], FlagTables::STATES => ['name', 'active', 'updated_at']] as $table => $columns) {
            foreach ($columns as $column) {
                self::assertTrue($schema->getTable($table)->getColumn($column)->getNotnull(), $table.'.'.$column.' is required');
            }
        }
    }

    public function testAStateIsInsertedOnceThenComparedAndSet(): void
    {
        $store = $this->states();
        $flag = FlagName::of('beta');

        self::assertNull($store->isActive($flag));
        self::assertTrue($store->insert($flag, false));
        self::assertFalse($store->insert($flag, true));
        self::assertFalse($store->isActive($flag));

        self::assertFalse($store->compareAndSet($flag, true, false));
        self::assertTrue($store->compareAndSet($flag, false, true));
        self::assertTrue($store->isActive($flag));
        self::assertFalse($store->compareAndSet(FlagName::of('absent'), false, true));
    }

    /**
     * Read once per request: a write by another process is seen after the reset that ends the request.
     */
    public function testStatesAreReadOncePerRequest(): void
    {
        $store = $this->states();
        $flag = FlagName::of('beta');
        $store->insert($flag, false);
        $store->insert(FlagName::of('gamma'), true);
        self::assertFalse($store->isActive($flag));
        self::assertTrue($store->isActive(FlagName::of('gamma')));

        $this->connection()->executeStatement('UPDATE '.FlagTables::STATES." SET active = TRUE WHERE name = 'beta'");
        self::assertFalse($store->isActive($flag));

        $store->reset();
        self::assertTrue($store->isActive($flag));
    }

    public function testADefinitionIsCreatedOnceThenReplacedAgainstItsRevision(): void
    {
        $definitions = $this->definitions();
        $flag = FlagName::of('beta');

        self::assertTrue($definitions->create(new FlagDefinition($flag, false)));
        self::assertFalse($definitions->create(new FlagDefinition($flag, true)));

        $revised = $definitions->findRevised($flag);
        self::assertNotNull($revised);
        self::assertSame(1, $revised->revision);

        self::assertTrue($definitions->replace(new FlagDefinition($flag, true), 1));
        self::assertFalse($definitions->replace(new FlagDefinition($flag, false), 1));
        self::assertSame(2, $definitions->findRevised($flag)?->revision);
        self::assertTrue($definitions->find($flag)?->active);
        self::assertNull($definitions->findRevised(FlagName::of('absent')));
    }

    /**
     * A row that no longer reads as a definition is not a flag the provider lacks: asked for it, the provider says
     * why it cannot read it, the listing leaves it out, and the unreadable rows are reported with their reason —
     * a row whose name is not a flag name included.
     */
    public function testARowThatNoLongerReadsIsReportedUnreadable(): void
    {
        $definitions = $this->definitions();
        $this->connection()->insert(FlagTables::DEFINITIONS, ['name' => 'bad', 'definition' => '{"rules": "all"}', 'revision' => 1, 'updated_at' => '2026-01-01 00:00:00']);
        $this->connection()->insert(FlagTables::DEFINITIONS, ['name' => 'Not A Name', 'definition' => '{}', 'revision' => 1, 'updated_at' => '2026-01-01 00:00:00']);
        $definitions->create(new FlagDefinition(FlagName::of('good'), true));
        $definitions->create(new FlagDefinition(FlagName::of('other'), true));
        $definitions->reset();

        self::assertSame(['good', 'other'], array_map(static fn (FlagDefinition $definition): string => $definition->name->value, $definitions->all()));
        self::assertNull($definitions->find(FlagName::of('absent')));

        $unreadable = $definitions->unreadable();
        self::assertSame(['Not A Name', 'bad'], array_map(static fn (UnreadableFlag $flag): string => $flag->name, $unreadable));
        self::assertStringContainsString('Not A Name', $unreadable[0]->reason);
        self::assertSame('"rules" is a list of rules.', $unreadable[1]->reason);

        try {
            $definitions->find(FlagName::of('bad'));
            self::fail('An unreadable row was read as a definition.');
        } catch (InvalidValue $invalidValue) {
            self::assertSame('"rules" is a list of rules.', $invalidValue->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('"rules" is a list of rules.');
        $definitions->findRevised(FlagName::of('bad'));
    }

    /**
     * The evaluator answers `PARSE_ERROR` for a flag whose row no longer reads, `FLAG_NOT_FOUND` for one without a
     * row, and throws for neither.
     */
    public function testAnUnreadableRowEvaluatesAsAParseError(): void
    {
        $this->connection()->insert(FlagTables::DEFINITIONS, ['name' => 'bad', 'definition' => 'not json', 'revision' => 1, 'updated_at' => '2026-01-01 00:00:00']);
        $evaluator = $this->service(FlagEvaluator::class, FlagEvaluator::class);

        $bad = $evaluator->evaluate(FlagName::of('bad'));
        self::assertFalse($bad->enabled);
        self::assertSame(EvaluationReason::Error, $bad->reason);
        self::assertSame(EvaluationErrorCode::ParseError, $bad->errorCode);
        self::assertSame(EvaluationErrorCode::FlagNotFound, $evaluator->evaluate(FlagName::of('absent'))->errorCode);
    }

    /**
     * Read once per request: a definition another process writes is seen after the reset that ends the request.
     */
    public function testDefinitionsAreReadOncePerRequest(): void
    {
        $definitions = $this->definitions();
        $definitions->create(new FlagDefinition(FlagName::of('good'), true));
        self::assertCount(1, $definitions->all());

        $this->connection()->insert(FlagTables::DEFINITIONS, ['name' => 'later', 'definition' => '{}', 'revision' => 1, 'updated_at' => '2026-01-01 00:00:00']);
        self::assertCount(1, $definitions->all());

        self::assertNull($definitions->find(FlagName::of('later')));

        $definitions->reset();
        self::assertNotNull($definitions->find(FlagName::of('later')));
        self::assertCount(2, $definitions->all());
    }

    private function states(): DoctrineFlagStore
    {
        return $this->service(PhilFeatureFlagsBundle::DOCTRINE_STATE_STORE, DoctrineFlagStore::class);
    }

    private function definitions(): DoctrineFlagDefinitions
    {
        return $this->service(PhilFeatureFlagsBundle::DOCTRINE_PROVIDER, DoctrineFlagDefinitions::class);
    }
}
