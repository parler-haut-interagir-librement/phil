<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Query\Query;
use Phil\OrganizationsBundle\Infrastructure\Dbal\OrganizationTables;
use Phil\OrganizationsBundle\PhilOrganizationsBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipes: each registers the bundle and copies a configuration the bundle accepts; the recipe of 0.2 also
 * imports the administration pages of the units and copies the migration that adds the tables of the units to a
 * database of 0.1, or every table to an empty one.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPES = __DIR__.'/../../recipe';

    /**
     * @return iterable<string, array{string}>
     */
    public static function versions(): iterable
    {
        yield '0.1' => ['0.1'];
        yield '0.2' => ['0.2'];
    }

    #[DataProvider('versions')]
    public function testTheBundleAcceptsTheConfigurationOfTheRecipe(string $version): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPES.'/'.$version.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($manifest);
        self::assertSame([PhilOrganizationsBundle::class => ['all']], $manifest['bundles']);
        self::assertIsArray($manifest['copy-from-recipe']);
        self::assertSame('%CONFIG_DIR%/', $manifest['copy-from-recipe']['config/']);
        self::assertIsArray($manifest['env']);
        self::assertArrayHasKey('ORGANIZATIONS_INVITATION_KEY', $manifest['env']);

        $config = Yaml::parseFile(self::RECIPES.'/'.$version.'/config/packages/phil_organizations.yaml');
        self::assertIsArray($config);
        self::assertSame(['phil_organizations'], array_keys($config));

        $extension = new PhilOrganizationsBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        self::assertIsArray(new Processor()->processConfiguration($configuration, [$config['phil_organizations']]));
    }

    public function testTheRecipeOfTheUnitsImportsThePagesAndCopiesTheMigration(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPES.'/0.2/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($manifest);
        self::assertSame(['config/' => '%CONFIG_DIR%/', 'migrations/' => 'migrations/'], $manifest['copy-from-recipe']);
        self::assertSame(
            ['phil_organizations_units' => ['resource' => '@PhilOrganizationsBundle/config/routes/units.php', 'prefix' => '/admin/organizations']],
            Yaml::parseFile(self::RECIPES.'/0.2/config/routes/phil_organizations.yaml'),
        );
        self::assertFileExists(\dirname(__DIR__, 2).'/config/routes/units.php');
        $first = json_decode((string) file_get_contents(self::RECIPES.'/0.1/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($first);
        self::assertSame(['config/' => '%CONFIG_DIR%/'], $first['copy-from-recipe']);
    }

    public function testTheMigrationAddsTheTablesOfTheUnitsAndTakesThemAway(): void
    {
        require_once self::RECIPES.'/0.2/migrations/Version20261120000000.php';
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $manager = $connection->createSchemaManager();

        $fresh = $this->migration($connection);
        self::assertSame('phil/organizations-bundle 0.2: the tables of the units and their members.', $fresh->getDescription());
        $fresh->up($manager->introspectSchema());
        self::assertCount(8, $this->creates($fresh->getSql()));

        foreach (OrganizationTables::for($connection->getDatabasePlatform())->tables() as $table) {
            if (!\in_array($table->getObjectName()->toString(), [OrganizationTables::UNIT, OrganizationTables::UNIT_MEMBER], true)) {
                $manager->createTable($table);
            }
        }

        $upgrade = $this->migration($connection);
        $upgrade->up($manager->introspectSchema());
        self::assertSame(['phil_organization_unit', 'phil_organization_unit_member'], $this->creates($upgrade->getSql()));

        $down = $this->migration($connection);
        $down->down($manager->introspectSchema());
        self::assertSame(['DROP TABLE phil_organization_unit_member', 'DROP TABLE phil_organization_unit'], array_map(static fn (Query $query): string => $query->getStatement(), $down->getSql()));
    }

    /**
     * The migration the recipe copies, found among the classes once its file is loaded.
     */
    private function migration(Connection $connection): AbstractMigration
    {
        $class = array_find(get_declared_classes(), static fn (string $class): bool => is_subclass_of($class, AbstractMigration::class) && str_ends_with($class, '\\Version20261120000000'));
        self::assertIsString($class);
        $migration = new $class($connection, new NullLogger());
        self::assertInstanceOf(AbstractMigration::class, $migration);

        return $migration;
    }

    /**
     * @param array<Query> $queries
     *
     * @return list<string> the tables created
     */
    private function creates(array $queries): array
    {
        $tables = [];

        foreach ($queries as $query) {
            if (1 === preg_match('/^CREATE TABLE (\w+)/', $query->getStatement(), $match)) {
                $tables[] = $match[1];
            }
        }

        return $tables;
    }
}
