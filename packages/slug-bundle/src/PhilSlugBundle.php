<?php

declare(strict_types=1);

namespace Phil\SlugBundle;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use InvalidArgumentException;
use Override;
use Phil\SlugBundle\Application\Contract\SlugHistory;
use Phil\SlugBundle\Infrastructure\DependencyInjection\SluggerServices;
use Phil\SlugBundle\Infrastructure\Doctrine\DoctrineSlugHistory;
use Phil\SlugBundle\Infrastructure\Doctrine\SlugHistorySchemaListener;
use Phil\SlugBundle\Infrastructure\Twig\SlugExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Attribute\AsTwigFilter;

/**
 * `phil/slug` in a Symfony application: named sluggers, each a service an application can decorate, the default one
 * autowired as `Slugger`, every one as `Slugger $<name>Slugger`, the `phil_slug` filter in Twig, and, when enabled,
 * the history of former slugs in a Doctrine DBAL table, autowired as `SlugHistory`.
 */
final class PhilSlugBundle extends AbstractBundle
{
    /** The service id of the Doctrine slug history, aliased as `SlugHistory` when the history is enabled. */
    public const string HISTORY_ID = 'phil_slug.history';

    #[Override]
    protected string $extensionAlias = 'phil_slug';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->validate()
                ->ifTrue(static fn (array $config): bool => null !== self::missingDefault($config))
                ->then(static function (array $config): never {
                    throw new InvalidArgumentException((string) self::missingDefault($config));
                })
            ->end()
            ->children()
                ->scalarNode('default')
                    ->info('The name of the slugger used when none is named: the one autowired as Slugger.')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('sluggers')
                    ->info('The sluggers, by name: a lowercase letter followed by lowercase letters, digits or underscores. Without any, one slugger named "default" with every option at its default.')
                    ->useAttributeAsKey('name')
                    ->validate()
                        ->ifTrue(static fn (array $sluggers): bool => [] !== self::badNames($sluggers))
                        ->then(static function (array $sluggers): never {
                            throw new InvalidArgumentException(\sprintf('A slugger name is a lowercase letter followed by lowercase letters, digits or underscores; these are not: %s.', implode(', ', self::badNames($sluggers))));
                        })
                    ->end()
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('locale')
                                ->info('The locale of the transliteration — "de" writes ü as ue —; null for the generic one.')
                                ->defaultNull()
                            ->end()
                            ->integerNode('max_length')
                                ->info('The longest slug, cut at the last hyphen that fits.')
                                ->defaultValue(SluggerServices::MAX_LENGTH)
                                ->min(1)
                                ->max(SluggerServices::MAX_LENGTH)
                            ->end()
                            ->arrayNode('leading_words')
                                ->info('Words dropped once from the start of a slug when others follow, as slugs spell them: ["le", "la", "les", "l", "the"].')
                                ->scalarPrototype()
                                    ->validate()
                                        ->ifTrue(static fn (mixed $word): bool => !SluggerServices::isLeadingWord($word))
                                        ->thenInvalid('A leading word is one word of lowercase ASCII letters and digits, as slugs spell them; %s is not.')
                                    ->end()
                                ->end()
                                ->defaultValue([])
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('history')
                    ->info(\sprintf('The history of former slugs in the table phil_slug_history, through doctrine/dbal: "%s", autowired as SlugHistory.', self::HISTORY_ID))
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('connection')->info('The DBAL connection of the table.')->defaultValue('default')->cannotBeEmpty()->end()
                        ->scalarNode('clock')->info('Service id of the PSR-20 clock of the recorded changes.')->defaultValue('clock')->cannotBeEmpty()->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{default: string, sluggers: array<string, array{locale: ?string, max_length: int, leading_words: list<string>}>, history: array{enabled: bool, connection: string, clock: string}} $config */
        $sluggers = [] === $config['sluggers'] ? [$config['default'] => ['locale' => null, 'max_length' => SluggerServices::MAX_LENGTH, 'leading_words' => []]] : $config['sluggers'];
        SluggerServices::register($builder, $sluggers, $config['default']);

        if (class_exists(AsTwigFilter::class)) {
            $container->services()->set(SlugExtension::class)
                ->args([new Reference(SluggerServices::SLUGGERS_ID)])
                ->tag('twig.attribute_extension')
                ->tag('twig.runtime');
        }

        if ($config['history']['enabled']) {
            $this->loadHistory($config['history'], $container);
        }
    }

    /**
     * @param array{enabled: bool, connection: string, clock: string} $history
     */
    private function loadHistory(array $history, ContainerConfigurator $container): void
    {
        if (!class_exists(Connection::class)) {
            throw new InvalidArgumentException('phil_slug.history needs doctrine/dbal: composer require doctrine/dbal.');
        }

        $connection = new Reference(\sprintf('doctrine.dbal.%s_connection', $history['connection']));
        $services = $container->services();
        $services->set(self::HISTORY_ID, DoctrineSlugHistory::class)
            ->args([$connection, new Reference($history['clock'])]);
        $services->alias(SlugHistory::class, self::HISTORY_ID);

        if (class_exists(GenerateSchemaEventArgs::class)) {
            $services->set(SlugHistorySchemaListener::class)
                ->args([$connection])
                ->tag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $history['connection']]);
        }
    }

    /**
     * @param array<array-key, mixed> $sluggers
     *
     * @return array<int|string>
     */
    private static function badNames(array $sluggers): array
    {
        return array_filter(array_keys($sluggers), static fn (int|string $name): bool => 1 !== preg_match('/^[a-z][a-z0-9_]*$/D', (string) $name));
    }

    /**
     * Why the default names none of the sluggers given, or `null` when it names one or none are given.
     *
     * @param array<array-key, mixed> $config
     */
    private static function missingDefault(array $config): ?string
    {
        $sluggers = \is_array($config['sluggers'] ?? null) ? $config['sluggers'] : [];
        $default = \is_string($config['default'] ?? null) ? $config['default'] : '';

        if ([] === $sluggers || isset($sluggers[$default])) {
            return null;
        }

        return \sprintf('The default slugger "%s" is not one of the sluggers: %s.', $default, implode(', ', array_keys($sluggers)));
    }
}
