<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\DependencyInjection;

use Doctrine\DBAL\Connection;
use Phil\PrivacyBundle\Application\Anonymization\AnonymizationRun;
use Phil\PrivacyBundle\Application\Anonymization\ProductionGuard;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizers;
use Phil\PrivacyBundle\Domain\Anonymization\DatabaseIdentity;
use Phil\PrivacyBundle\Domain\Anonymization\Locale\LocalePack;
use Phil\PrivacyBundle\Domain\Anonymization\Locale\LocalePacks;
use Phil\PrivacyBundle\Domain\Attribute\AsAnonymizer;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Infrastructure\Console\AnonymizeDatabaseCommand;
use Phil\PrivacyBundle\Infrastructure\Database\ConnectionIdentity;
use Phil\PrivacyBundle\Infrastructure\Database\DbalAnonymizationTarget;
use Phil\PrivacyBundle\Infrastructure\Doctrine\AnonymizationPlanReader;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The services of anonymization, declared from the bundle's configuration: the anonymizers, the locale packs and the
 * run, always; the database identity, the target, the plan reader, the guard and the command when the console and
 * Doctrine DBAL are installed — removed again by `DatabaseServicesPass` when the configured connection is not a
 * service.
 */
final class AnonymizationServices
{
    public const string ANONYMIZER_TAG = 'phil_privacy.anonymizer';

    public const string LOCALE_PACK_TAG = 'phil_privacy.locale_pack';

    public const string DATABASE_IDENTITY = 'phil_privacy.database_identity';

    /**
     * The connection the `database` section names.
     *
     * @param array<array-key, mixed> $config
     */
    public static function connection(array $config): Reference
    {
        $database = \is_array($config['database'] ?? null) ? $config['database'] : [];

        return new Reference(\is_string($database['connection'] ?? null) ? $database['connection'] : 'database_connection');
    }

    /**
     * Declares the services of the `database` and `anonymization` sections of the configuration.
     *
     * @param array<array-key, mixed> $config
     */
    public static function load(array $config, ServicesConfigurator $services, ContainerBuilder $builder): void
    {
        $anonymization = \is_array($config['anonymization'] ?? null) ? $config['anonymization'] : [];
        $guard = \is_array($anonymization['guard'] ?? null) ? $anonymization['guard'] : [];
        $categories = self::map($anonymization, 'categories', 'anonymization');
        $tables = self::tables($anonymization['tables'] ?? []);

        // Built once here so that a malformed type fails the container build rather than the first anonymization.
        try {
            new AnonymizationPlanReader(null, $categories, $tables)->plan();
        } catch (InvalidPrivacyValue $invalidPrivacyValue) {
            throw new InvalidConfigurationException('phil_privacy.anonymization: '.$invalidPrivacyValue->getMessage(), 0, $invalidPrivacyValue);
        }

        $builder->registerAttributeForAutoconfiguration(AsAnonymizer::class, static function (ChildDefinition $definition, AsAnonymizer $attribute): void {
            $definition->addTag(self::ANONYMIZER_TAG, ['type' => $attribute->type]);
        });
        $builder->registerForAutoconfiguration(LocalePack::class)->addTag(self::LOCALE_PACK_TAG);

        $services->set('phil_privacy.anonymizers.built_in', Anonymizers::class)->factory([Anonymizers::class, 'builtIn']);
        $services->set(Anonymizers::class)
            ->factory([new Reference('phil_privacy.anonymizers.built_in'), 'with'])
            ->args([new TaggedIteratorArgument(self::ANONYMIZER_TAG, 'type')]);
        $services->set('phil_privacy.locale_packs.built_in', LocalePacks::class)->factory([LocalePacks::class, 'builtIn']);
        $services->set(LocalePacks::class)
            ->factory([new Reference('phil_privacy.locale_packs.built_in'), 'with'])
            ->args([new TaggedIteratorArgument(self::LOCALE_PACK_TAG)]);
        $services->set(AnonymizationRun::class)
            ->args([new Reference(Anonymizers::class), new Reference(LocalePacks::class), $anonymization['locale'] ?? 'en_US'])
            ->public();

        if (!class_exists(Command::class) || !class_exists(Connection::class)) {
            return;
        }

        $connection = self::connection($config);

        $services->set(self::DATABASE_IDENTITY, DatabaseIdentity::class)
            ->factory(ConnectionIdentity::of(...))
            ->args([$connection]);
        $services->set(DbalAnonymizationTarget::class)->args([$connection, $anonymization['batch_size'] ?? 500]);
        $services->set(AnonymizationPlanReader::class)
            ->args([new Reference('doctrine', ContainerInterface::NULL_ON_INVALID_REFERENCE), $categories, $tables]);
        $services->set(ProductionGuard::class)->args([
            '%kernel.environment%',
            self::strings($guard, 'environments', 'anonymization.guard'),
            self::strings($guard, 'hosts', 'anonymization.guard'),
            self::strings($guard, 'databases', 'anonymization.guard'),
        ]);
        $services->set(AnonymizeDatabaseCommand::class)
            ->args([
                new Reference(AnonymizationPlanReader::class),
                new Reference(AnonymizationRun::class),
                new Reference(DbalAnonymizationTarget::class),
                new Reference(self::DATABASE_IDENTITY),
                new Reference(ProductionGuard::class),
            ])
            ->tag('console.command');
    }

    /**
     * The configured tables, every option a scalar or null.
     *
     * @return array<string, array<string, array{type: string, options: array<string, scalar|null>}>>
     */
    private static function tables(mixed $tables): array
    {
        $typed = [];

        foreach (\is_array($tables) ? $tables : [] as $table => $columns) {
            foreach (\is_array($columns) ? $columns : [] as $column => $rule) {
                if (!\is_string($table) || !\is_string($column)) {
                    throw self::invalidRule((string) $table, (string) $column);
                }

                $typed[$table][$column] = self::rule($rule, $table, $column);
            }
        }

        return $typed;
    }

    /**
     * One column's rule: a type, and named options whose values are scalars or null.
     *
     * @return array{type: string, options: array<string, scalar|null>}
     */
    private static function rule(mixed $rule, string $table, string $column): array
    {
        $type = \is_array($rule) ? ($rule['type'] ?? null) : null;
        $options = \is_array($rule) && \is_array($rule['options'] ?? null) ? $rule['options'] : [];
        $scalars = [];

        foreach ($options as $name => $value) {
            if (\is_string($name) && (null === $value || \is_scalar($value))) {
                $scalars[$name] = $value;
            }
        }

        if (!\is_string($type) || \count($scalars) !== \count($options)) {
            throw self::invalidRule($table, $column);
        }

        return ['type' => $type, 'options' => $scalars];
    }

    private static function invalidRule(string $table, string $column): InvalidConfigurationException
    {
        return new InvalidConfigurationException(\sprintf('phil_privacy.anonymization.tables.%s.%s needs a type, and named options whose values are scalars or null.', $table, $column));
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @return array<string, string>
     */
    private static function map(array $config, string $key, string $section): array
    {
        $map = [];

        foreach (\is_array($config[$key] ?? null) ? $config[$key] : [] as $name => $value) {
            if (!\is_string($name) || !\is_string($value)) {
                throw new InvalidConfigurationException(\sprintf('phil_privacy.%s.%s must map names to strings.', $section, $key));
            }

            $map[$name] = $value;
        }

        return $map;
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @return list<string>
     */
    private static function strings(array $config, string $key, string $section): array
    {
        $values = $config[$key] ?? [];

        if (!\is_array($values)) {
            throw new InvalidConfigurationException(\sprintf('phil_privacy.%s.%s must be a list of strings.', $section, $key));
        }

        $strings = [];

        foreach ($values as $value) {
            if (!\is_string($value)) {
                throw new InvalidConfigurationException(\sprintf('phil_privacy.%s.%s must be a list of strings.', $section, $key));
            }

            $strings[] = $value;
        }

        return $strings;
    }
}
