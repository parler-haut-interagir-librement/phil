<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use InvalidArgumentException;
use Phil\Lti\Service\Scope;
use Phil\LtiBundle\Infrastructure\Dbal\LtiTables;
use Phil\LtiBundle\Infrastructure\DependencyInjection\LtiServices;
use Phil\LtiBundle\PhilLtiBundle;
use Phil\LtiBundle\Tests\Unit\Fixture\TestKeys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Every service the configuration registers, with its class, factory, arguments, tags and visibility, against a
 * reviewed text: a changed argument, a lost tag or a service made public by accident shows as a line of the difference.
 */
#[CoversClass(LtiServices::class)]
#[CoversClass(PhilLtiBundle::class)]
#[CoversClass(LtiTables::class)]
final class ServicesSnapshotTest extends TestCase
{
    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function scenarios(): iterable
    {
        $platforms = [['issuer' => 'https://lms.example.org', 'client_id' => 'c', 'authorization_url' => 'https://lms.example.org/a', 'token_url' => 'https://lms.example.org/t', 'key_set_url' => 'https://lms.example.org/j', 'deployments' => ['1']]];
        $tools = [['client_id' => 'c', 'login_url' => 'https://tool.example.com/l', 'redirect_urls' => ['https://tool.example.com/r'], 'key_set_url' => 'https://tool.example.com/j', 'scopes' => [Scope::SCORE]]];

        yield 'tables, both sides' => ['tables-both', [
            'connection' => 'other', 'table_prefix' => 'x_', 'clock' => 'my.clock', 'cache' => 'my.cache', 'http_client' => 'my.http', 'message_bus' => 'my.bus', 'allow_localhost' => true, 'kit_subdirectory' => 'kit',
            'keys' => ['kms' => 'my.kms', 'master_key' => 'm', 'lifetime' => 100_000, 'overlap' => 4_000, 'bits' => 3072],
            'tool' => ['state_lifetime' => 120, 'platforms' => $platforms],
            'platform' => ['issuer' => 'https://courses.example.com', 'token_url' => 'https://courses.example.com/token', 'token_lifetime' => 120, 'tools' => $tools],
        ]];
        yield 'tables, tool only' => ['tables-tool', ['keys' => ['master_key' => 'm'], 'tool' => ['platforms' => $platforms]]];
        yield 'tables, platform only, keys of the configuration' => ['tables-platform', [
            'keys' => ['source' => 'config', 'private_key' => TestKeys::pem(), 'key_id' => 'k1', 'passphrase' => 'pp', 'retired_public_keys' => ['pem']],
            'platform' => ['issuer' => 'https://courses.example.com', 'token_url' => 'https://courses.example.com/token', 'tools' => $tools],
        ]];
        yield 'memory, both sides' => ['memory-both', [
            'store' => 'memory', 'allow_localhost' => false,
            'keys' => ['source' => 'config', 'private_key' => TestKeys::pem()],
            'tool' => ['platforms' => $platforms],
            'platform' => ['issuer' => 'https://courses.example.com', 'token_url' => 'https://courses.example.com/token', 'tools' => $tools],
        ]];
        yield 'memory, neither side' => ['memory-none', ['store' => 'memory', 'tool' => ['enabled' => false], 'keys' => ['source' => 'config', 'private_key' => TestKeys::pem()]]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('scenarios')]
    public function testTheServicesOfAConfiguration(string $name, array $config): void
    {
        $container = new ContainerBuilder();
        $extension = new PhilLtiBundle()->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load([$config], $container);

        $file = __DIR__.'/../Snapshot/services-'.$name.'.txt';
        $actual = $this->dump($container);

        if (!is_file($file) && '1' === getenv('PHIL_UPDATE_SNAPSHOTS')) {
            file_put_contents($file, $actual);
        }

        self::assertStringEqualsFile($file, $actual);
    }

    public function testTheTablesAreNamedByThePrefix(): void
    {
        $tables = new LtiTables('x_');

        self::assertSame(['x_nonce', 'x_login_state', 'x_access_token', 'x_key', 'x_line_item', 'x_score', 'x_pending_score'], [$tables->nonces, $tables->loginStates, $tables->accessTokens, $tables->keys, $tables->lineItems, $tables->scores, $tables->pendingScores]);
    }

    /**
     * @return iterable<string, array{string, AbstractPlatform}>
     */
    public static function platforms(): iterable
    {
        yield 'SQLite' => ['sqlite', new SQLitePlatform()];
        yield 'MySQL' => ['mysql', new MySQLPlatform()];
        yield 'PostgreSQL' => ['postgresql', new PostgreSQLPlatform()];
    }

    #[DataProvider('platforms')]
    public function testTheTablesOfAPlatform(string $name, AbstractPlatform $platform): void
    {
        $lines = [];

        foreach (new LtiTables('x_')->definitions($platform) as $table) {
            $lines[] = 'table '.$table->getObjectName()->toString();

            foreach ($table->getColumns() as $column) {
                $lines[] = \sprintf('    column %s %s length=%s notnull=%s charset=%s collation=%s', $column->getObjectName()->toString(), $column->getTypeName(), var_export($column->getLength(), true), var_export($column->getNotnull(), true), var_export($column->getCharset(), true), var_export($column->getCollation(), true));
            }

            $lines[] = '    primary '.implode(', ', array_map(static fn (UnqualifiedName $column): string => $column->toString(), $table->getPrimaryKeyConstraint()?->getColumnNames() ?? []));

            foreach ($table->getIndexes() as $index) {
                $lines[] = '    index '.$index->getObjectName()->toString().' ('.implode(', ', array_map(static fn (IndexedColumn $column): string => $column->getColumnName()->toString(), $index->getIndexedColumns())).')';
            }
        }

        $actual = implode("\n", $lines)."\n";
        $file = __DIR__.'/../Snapshot/tables-'.$name.'.txt';

        if (!is_file($file) && '1' === getenv('PHIL_UPDATE_SNAPSHOTS')) {
            file_put_contents($file, $actual);
        }

        self::assertStringEqualsFile($file, $actual);
    }

    public function testTheNamesAreTheSevenTables(): void
    {
        self::assertSame(['x_nonce', 'x_login_state', 'x_access_token', 'x_key', 'x_line_item', 'x_score', 'x_pending_score'], new LtiTables('x_')->names());
    }

    public function testASchemaGetsOnlyTheTablesItLacks(): void
    {
        $platform = new SQLitePlatform();
        $tables = new LtiTables('x_');
        $schema = $tables->configureSchema(Schema::editor()->create(), $platform);
        self::assertCount(7, $schema->getTables());

        $again = $tables->configureSchema($schema, $platform);
        self::assertCount(7, $again->getTables());
        self::assertTrue($again->hasTable('x_scores') || $again->hasTable('x_score'));
    }

    public function testAPrefixThatIsRefusedIsCutInTheMessage(): void
    {
        $long = str_repeat('A', 75);

        try {
            new LtiTables($long);
            self::fail('The prefix was accepted.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('"'.str_repeat('A', 59).'…" is not a prefix of table names: 1 to 40 lowercase letters, digits or "_", not starting with a digit.', $invalidArgumentException->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('"Bad" is not');
        new LtiTables('Bad');
    }

    private function dump(ContainerBuilder $container): string
    {
        $lines = [];

        foreach ($container->getParameterBag()->all() as $name => $value) {
            $lines[] = 'parameter '.$name.' = '.json_encode($value);
        }

        $definitions = $container->getDefinitions();
        ksort($definitions);

        foreach ($definitions as $id => $definition) {
            if ('service_container' === $id) {
                continue;
            }

            $lines[] = 'service '.$id.' ['.($definition->isPublic() ? 'public' : 'private').'] class='.$definition->getClass();

            $factory = $definition->getFactory();

            if (null !== $factory) {
                $lines[] = '    factory '.json_encode($factory);
            }

            foreach ($definition->getArguments() as $index => $argument) {
                $lines[] = '    arg '.$index.' = '.self::value($argument);
            }

            foreach ($definition->getTags() as $tag => $attributes) {
                $lines[] = '    tag '.$tag.' '.json_encode($attributes);
            }
        }

        $aliases = $container->getAliases();
        ksort($aliases);

        foreach ($aliases as $id => $alias) {
            $lines[] = 'alias '.$id.' -> '.$alias.' ['.($alias->isPublic() ? 'public' : 'private').']';
        }

        return implode("\n", $lines)."\n";
    }

    private static function value(mixed $value): string
    {
        if ($value instanceof Reference) {
            return '@'.$value.' (behavior '.$value->getInvalidBehavior().')';
        }

        if ($value instanceof Definition) {
            return 'definition '.$value->getClass();
        }

        // The test key is generated on each machine, so the snapshot names it instead of holding its bytes.
        if (TestKeys::pem() === $value) {
            return '<TestKeys::pem()>';
        }

        if (\is_array($value)) {
            return '['.implode(', ', array_map(self::value(...), $value)).']';
        }

        return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }
}
