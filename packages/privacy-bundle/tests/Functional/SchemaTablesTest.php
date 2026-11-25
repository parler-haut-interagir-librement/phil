<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Override;
use Phil\PrivacyBundle\Application\AccountDeletion\AccountDeletions;
use Phil\PrivacyBundle\Application\Contract\IncidentRegister;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\PrivacyServicesPass;
use Phil\PrivacyBundle\Infrastructure\Doctrine\PrivacySchemaListener;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Phil\PrivacyBundle\Tests\Functional\App\TablesKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The tables an application gets are those of the features it uses with the table of the bundle as their store: the
 * migration `doctrine:migrations:diff` writes creates them, and only them.
 */
#[CoversClass(PrivacyServicesPass::class)]
#[CoversClass(PrivacySchemaListener::class)]
#[CoversClass(PrivacyTables::class)]
final class SchemaTablesTest extends KernelTestCase
{
    private const array INCIDENTS = [PrivacyTables::INCIDENTS, PrivacyTables::INCIDENT_REVISIONS, PrivacyTables::INCIDENT_JOURNAL];

    #[Override]
    protected function setUp(): void
    {
        FunctionalTestCase::skipWithoutEventDispatcher();
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        $configuration = $options['configuration'] ?? [];
        self::assertIsArray($configuration);

        /** @var array<string, mixed> $configuration */
        return new TablesKernel(true === ($options['security'] ?? false), $configuration);
    }

    /**
     * @return iterable<string, array{bool, array<string, mixed>, list<string>}>
     */
    public static function applications(): iterable
    {
        yield 'accounts and the defaults' => [true, [], [PrivacyTables::DELETIONS, PrivacyTables::PROOFS, ...self::INCIDENTS]];
        yield 'no accounts' => [false, [], [PrivacyTables::PROOFS, ...self::INCIDENTS]];
        yield 'no accounts, deletions turned on' => [false, ['account_deletion' => ['enabled' => true]], [PrivacyTables::DELETIONS, PrivacyTables::PROOFS, ...self::INCIDENTS]];
        yield 'accounts, deletions turned off' => [true, ['account_deletion' => ['enabled' => false]], [PrivacyTables::PROOFS, ...self::INCIDENTS]];
        yield 'register turned off' => [true, ['incidents' => ['enabled' => false]], [PrivacyTables::DELETIONS, PrivacyTables::PROOFS]];
        yield 'tracked emails turned on' => [true, ['email_tracking' => ['enabled' => true]], [PrivacyTables::DELETIONS, PrivacyTables::PROOFS, ...self::INCIDENTS, PrivacyTables::EMAIL_TRACKING]];
        yield 'stores of the application' => [true, [
            'account_deletion' => ['store' => 'app.deletions'],
            'consent' => ['proofs' => 'app.proofs'],
            'incidents' => ['store' => 'app.incidents'],
            'email_tracking' => ['enabled' => true, 'store' => 'app.email_tracking'],
        ], []];
    }

    /**
     * @param array<string, mixed> $configuration
     * @param list<string>         $expected
     */
    #[DataProvider('applications')]
    public function testTheSchemaGetsTheTablesOfTheFeaturesInUse(bool $security, array $configuration, array $expected): void
    {
        self::bootKernel(['security' => $security, 'configuration' => $configuration]);
        $container = self::getContainer();

        self::assertSame($expected, $container->getParameter(PhilPrivacyBundle::TABLES));

        $connection = $container->get('database_connection');
        self::assertInstanceOf(Connection::class, $connection);
        $listener = $container->get(TablesKernel::LISTENER);
        self::assertInstanceOf(PrivacySchemaListener::class, $listener);

        $manager = self::createStub(EntityManagerInterface::class);
        $manager->method('getConnection')->willReturn($connection);
        $event = new GenerateSchemaEventArgs($manager, Schema::editor()->create());
        $listener->postGenerateSchema($event);

        $diff = $connection->createSchemaManager()->createComparator()->compareSchemas(Schema::editor()->create(), $event->getSchema());
        $created = array_map(static fn (Table $table): string => $table->getObjectName()->getUnqualifiedName()->getValue(), $diff->getCreatedTables());
        sort($created);
        $sorted = $expected;
        sort($sorted);

        self::assertSame($sorted, $created);

        foreach ($connection->getDatabasePlatform()->getAlterSchemaSQL($diff) as $statement) {
            $connection->executeStatement($statement);
        }

        $names = array_map(static fn (OptionallyQualifiedName $name): string => $name->getUnqualifiedName()->getValue(), $connection->createSchemaManager()->introspectTableNames());
        sort($names);

        self::assertSame($sorted, $names);
    }

    public function testWithoutAccountsNothingOfTheDeletionsIsRegistered(): void
    {
        self::bootKernel(['security' => false]);

        self::assertFalse(self::getContainer()->has(AccountDeletions::class));
        self::assertTrue(self::getContainer()->has(IncidentRegister::class));
    }

    public function testWithAccountsTheDeletionsAreRegistered(): void
    {
        self::bootKernel(['security' => true, 'configuration' => ['incidents' => ['enabled' => false]]]);

        self::assertTrue(self::getContainer()->has(AccountDeletions::class));
        self::assertFalse(self::getContainer()->has(IncidentRegister::class));
    }
}
