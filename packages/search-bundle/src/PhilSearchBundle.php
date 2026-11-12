<?php

declare(strict_types=1);

namespace Phil\SearchBundle;

use Loupe\Loupe\LoupeFactory;
use Override;
use Phil\SearchBundle\Application\Contract\SynonymProvider;
use Phil\SearchBundle\Application\DocumentProvider;
use Phil\SearchBundle\Application\RejectedFormSynonyms;
use Phil\SearchBundle\Application\Search;
use Phil\SearchBundle\Application\SearchIndex;
use Phil\SearchBundle\Application\SynonymExpander;
use Phil\SearchBundle\Infrastructure\Console\ReindexCommand;
use Phil\SearchBundle\Infrastructure\Console\SynonymsCommand;
use Phil\SearchBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\SearchBundle\Infrastructure\Doctrine\DbalSearchIndex;
use Phil\SearchBundle\Infrastructure\Doctrine\IndexOnFlushListener;
use Phil\SearchBundle\Infrastructure\Doctrine\SearchSchemaListener;
use Phil\SearchBundle\Infrastructure\Meilisearch\MeilisearchIndex;
use Phil\SearchBundle\Infrastructure\SearchSynonyms\SynonymStoreProvider;
use Phil\SearchBundle\Infrastructure\Twig\Components\SearchFiltersComponent;
use Phil\SearchBundle\Infrastructure\Twig\KitComponents;
use Phil\SearchLoupe\LoupeIndex;
use Phil\SearchSynonyms\Application\Contract\SynonymStore;
use Phil\SearchSynonyms\Infrastructure\Store\FileSynonymStore;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Search: resources turned into documents by their providers, kept in an index — a table of the database,
 * Meilisearch, or Loupe with `phil/search-loupe` — up to date at each Doctrine flush, searched with facets and the
 * synonyms of each language, and rebuilt by a command; the filters of a results page as a Twig component, with its
 * Stimulus controller, when Symfony UX Twig Component is installed.
 */
final class PhilSearchBundle extends AbstractBundle
{
    public const string PROVIDER_TAG = 'phil_search.provider';

    public const string SYNONYM_PROVIDER_TAG = 'phil_search.synonym_provider';

    /** The store of `phil/search-synonyms`, named and not imported: the bundle does not require the package. */
    public const string SYNONYM_STORE = SynonymStore::class;

    public const string FILE_SYNONYM_STORE = FileSynonymStore::class;

    private const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    /** The adapter of `phil/search-loupe`, named and not imported: the bundle does not require the package. */
    public const string LOUPE_INDEX = LoupeIndex::class;

    public const string LOUPE_FACTORY = LoupeFactory::class;

    #[Override]
    protected string $extensionAlias = 'phil_search';

    /**
     * @param string $loupeIndex       the class of the Loupe adapter, which `adapter: loupe` needs installed
     * @param string $fileSynonymStore the class of the file store of `phil/search-synonyms`, which
     *                                 `synonyms.directory` needs installed
     */
    public function __construct(
        private readonly string $loupeIndex = self::LOUPE_INDEX,
        private readonly string $fileSynonymStore = self::FILE_SYNONYM_STORE,
    ) {
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new KitSubdirectoryPass());
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // The controller under the name Symfony UX gives the controllers of a package, for AssetMapper to serve.
        if ($builder->hasExtension('asset_mapper')) {
            $container->extension('asset_mapper', ['paths' => [self::CONTROLLERS_DIR => '@phil/search-bundle']]);
        }
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('adapter')
                    ->info('Where documents are indexed: dbal, a table of the database; meilisearch; or loupe, SQLite files without a server, with phil/search-loupe.')
                    ->values(['dbal', 'meilisearch', 'loupe'])
                    ->defaultValue('dbal')
                ->end()
                ->scalarNode('connection')
                    ->info('The Doctrine connection of the table, and whose entities are indexed.')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('meilisearch')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('url')->defaultNull()->end()
                        ->scalarNode('key')->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('loupe')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('directory')
                            ->info('Where Loupe keeps its indexes, a directory each.')
                            ->defaultValue('%kernel.project_dir%/var/loupe')
                            ->cannotBeEmpty()
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('index_entities')
                    ->info('Whether the entities a Doctrine flush changes are indexed.')
                    ->defaultTrue()
                ->end()
                ->integerNode('batch')
                    ->info('How many documents a rebuild sends at once.')
                    ->min(1)
                    ->defaultValue(500)
                ->end()
                ->arrayNode('synonyms')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('languages')
                            ->info('The languages whose synonyms an engine that keeps them itself, Meilisearch, is given.')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                        ->end()
                        ->scalarNode('directory')
                            ->info('A directory of synonyms files, one per language in the Solr format (fr.txt), with phil/search-synonyms.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('store')
                            ->info("The service of a store of phil/search-synonyms, the application's own.")
                            ->defaultNull()
                        ->end()
                        ->arrayNode('rejected_forms')
                            ->info('The services giving the forms a vocabulary rejects, each a RejectedForms.')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('kit_subdirectory')
                    ->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null.')
                    ->defaultNull()
                ->end()
            ->end()
            ->validate()
                ->ifTrue($this->lacksMeilisearchUrl(...))
                ->thenInvalid('Meilisearch needs its URL: phil_search.meilisearch.url.')
            ->end()
            ->validate()
                ->ifTrue(fn (array $config): bool => 'loupe' === $config['adapter'] && !class_exists($this->loupeIndex))
                ->thenInvalid('The Loupe adapter is phil/search-loupe: composer require phil/search-loupe.')
            ->end()
            ->validate()
                ->ifTrue($this->lacksSynonymsPackage(...))
                ->thenInvalid('A directory of synonyms is read by phil/search-synonyms: composer require phil/search-synonyms.')
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{adapter: string, connection: string, meilisearch: array{url: ?string, key: ?string}, loupe: array{directory: string}, index_entities: bool, batch: int, synonyms: array{languages: list<string>, directory: ?string, store: ?string, rejected_forms: list<string>}, kit_subdirectory: ?string} $config */
        $builder->registerForAutoconfiguration(DocumentProvider::class)->addTag(self::PROVIDER_TAG);
        $builder->registerForAutoconfiguration(SynonymProvider::class)->addTag(self::SYNONYM_PROVIDER_TAG);
        $services = $container->services();
        $connection = new Reference(\sprintf('doctrine.dbal.%s_connection', $config['connection']));

        if ('meilisearch' === $config['adapter']) {
            $services->set(MeilisearchIndex::class)->args([new Reference('http_client'), $config['meilisearch']['url'], $config['meilisearch']['key']]);
            $services->alias(SearchIndex::class, MeilisearchIndex::class);
        } elseif ('loupe' === $config['adapter']) {
            $services->set(self::LOUPE_FACTORY, self::LOUPE_FACTORY);
            $services->set($this->loupeIndex, $this->loupeIndex)->args([new Reference(self::LOUPE_FACTORY), $config['loupe']['directory']]);
            $services->alias(SearchIndex::class, $this->loupeIndex);
        } else {
            $services->set(DbalSearchIndex::class)->args([$connection]);
            $services->alias(SearchIndex::class, DbalSearchIndex::class);
            $services->set(SearchSchemaListener::class)
                ->args([$connection])
                ->tag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }

        $this->loadSynonyms($config['synonyms'], $container);
        $services->set(Search::class)
            ->args([new Reference(SearchIndex::class), new TaggedIteratorArgument(self::PROVIDER_TAG), $config['batch'], new Reference(SynonymExpander::class), $config['synonyms']['languages']])
            ->public();

        if ($config['index_entities']) {
            $services->set(IndexOnFlushListener::class)
                ->args([new Reference(Search::class)])
                ->tag('doctrine.event_listener', ['event' => 'onFlush', 'connection' => $config['connection']])
                ->tag('doctrine.event_listener', ['event' => 'postFlush', 'connection' => $config['connection']])
                ->tag('doctrine.event_listener', ['event' => 'onClear', 'connection' => $config['connection']])
                ->tag('kernel.reset', ['method' => 'reset']);
        }

        if (class_exists(Command::class)) {
            $services->set(ReindexCommand::class)->args([new Reference(Search::class)])->tag('console.command');
            $services->set(SynonymsCommand::class)->args([new Reference(Search::class)])->tag('console.command');
        }

        if (class_exists(AsTwigComponent::class)) {
            $services->set(KitComponents::class)->args([$config['kit_subdirectory']]);
            $services->set(SearchFiltersComponent::class)->args([new Reference(KitComponents::class)])->autoconfigure();
        }
    }

    /**
     * The synonyms: the expander, reset between two requests, and the providers the configuration names.
     *
     * @param array{languages: list<string>, directory: ?string, store: ?string, rejected_forms: list<string>} $synonyms
     */
    private function loadSynonyms(array $synonyms, ContainerConfigurator $container): void
    {
        $services = $container->services();
        $services->set(SynonymExpander::class)
            ->args([new TaggedIteratorArgument(self::SYNONYM_PROVIDER_TAG)])
            ->tag('kernel.reset', ['method' => 'reset']);
        $store = $synonyms['store'];

        if (null !== $synonyms['directory']) {
            $services->set($this->fileSynonymStore, $this->fileSynonymStore)->args([$synonyms['directory']]);
            $store = $this->fileSynonymStore;
        }

        if (null !== $store) {
            $services->set(SynonymStoreProvider::class)->args([new Reference($store)])->tag(self::SYNONYM_PROVIDER_TAG);
        }

        foreach ($synonyms['rejected_forms'] as $position => $forms) {
            $services->set('phil_search.rejected_forms.'.$position, RejectedFormSynonyms::class)->args([new Reference($forms)])->tag(self::SYNONYM_PROVIDER_TAG);
        }
    }

    /**
     * @param array{synonyms: array{directory: ?string}} $config
     */
    private function lacksSynonymsPackage(array $config): bool
    {
        return null !== $config['synonyms']['directory'] && !class_exists($this->fileSynonymStore);
    }

    /**
     * @param array{adapter: string, meilisearch: array{url: ?string}} $config
     */
    private function lacksMeilisearchUrl(array $config): bool
    {
        return 'meilisearch' === $config['adapter'] && null === $config['meilisearch']['url'];
    }
}
