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
use Phil\SearchBundle\Infrastructure\Outbox\IndexEntity;
use Phil\SearchBundle\Infrastructure\Outbox\OutboxIndexer;
use Phil\SearchBundle\Infrastructure\Outbox\OutboxIndexingListener;
use Phil\SearchBundle\Infrastructure\Outbox\RemoveDocument;
use Phil\SearchBundle\Infrastructure\Outbox\SearchOutboxCodec;
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
 *
 * An index outside the database, Meilisearch or Loupe, learns of each flush through `phil/outbox-bundle`, recorded in
 * the flush's transaction and relayed once it commits; the table of the database is written after each flush.
 */
final class PhilSearchBundle extends AbstractBundle
{
    public const string PROVIDER_TAG = 'phil_search.provider';

    public const string SYNONYM_PROVIDER_TAG = 'phil_search.synonym_provider';

    /** The store of `phil/search-synonyms`, named and not imported: the bundle does not require the package. */
    public const string SYNONYM_STORE = SynonymStore::class;

    public const string FILE_SYNONYM_STORE = FileSynonymStore::class;

    private const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    /** The entities of a flush recorded in the outbox, in its transaction, and indexed once it is relayed. */
    public const string INDEX_OUTBOX = 'outbox';

    /** The entities of a flush indexed after it, in the same request. */
    public const string INDEX_DIRECT = 'direct';

    public const string INDEX_NONE = 'none';

    /** The adapter of `phil/search-loupe`, named and not imported: the bundle does not require the package. */
    public const string LOUPE_INDEX = LoupeIndex::class;

    public const string LOUPE_FACTORY = LoupeFactory::class;

    /** The outbox of `phil/outbox`, named by a string: the bundle neither requires nor imports the package. */
    public const string OUTBOX = 'Phil\\Outbox\\Contract\\Outbox';

    #[Override]
    protected string $extensionAlias = 'phil_search';

    /**
     * @param string $loupeIndex       the class of the Loupe adapter, which `adapter: loupe` needs installed
     * @param string $fileSynonymStore the class of the file store of `phil/search-synonyms`, which
     *                                 `synonyms.directory` needs installed
     * @param string $outbox           the interface of the outbox, which `index_entities: outbox` needs installed
     */
    public function __construct(
        private readonly string $loupeIndex = self::LOUPE_INDEX,
        private readonly string $fileSynonymStore = self::FILE_SYNONYM_STORE,
        private readonly string $outbox = self::OUTBOX,
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
                        ->scalarNode('http_client')->info('The service id of the HTTP client that calls Meilisearch.')->defaultValue('http_client')->cannotBeEmpty()->end()
                        ->floatNode('timeout')->info('The longest wait for a byte of an answer, in seconds.')->defaultValue(MeilisearchIndex::TIMEOUT)->min(0.001)->max(300.0)->end()
                        ->floatNode('max_duration')->info('The longest call, in seconds.')->defaultValue(MeilisearchIndex::MAX_DURATION)->min(0.001)->max(300.0)->end()
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
                ->enumNode('index_entities')
                    ->info('How the entities a Doctrine flush changes are indexed: outbox, recorded in the transaction of the flush and relayed by phil/outbox-bundle, the default with Meilisearch and Loupe; direct, after the flush, the default with the table of the database; none. true is the default of the adapter, false none.')
                    ->values([null, self::INDEX_OUTBOX, self::INDEX_DIRECT, self::INDEX_NONE])
                    ->beforeNormalization()
                        ->ifTrue(static fn (mixed $value): bool => \is_bool($value))
                        ->then(static fn (bool $value): ?string => $value ? null : self::INDEX_NONE)
                    ->end()
                    ->defaultNull()
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
                ->always(static function (array $config): array {
                    $config['index_entities'] ??= 'dbal' === $config['adapter'] ? self::INDEX_DIRECT : self::INDEX_OUTBOX;

                    return $config;
                })
            ->end()
            ->validate()
                ->ifTrue(fn (array $config): bool => self::INDEX_OUTBOX === $config['index_entities'] && !interface_exists($this->outbox))
                ->thenInvalid('phil_search.index_entities is "outbox", the default with Meilisearch and Loupe: the outbox is phil/outbox-bundle (composer require phil/outbox-bundle); or index after each flush with index_entities: direct.')
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
        /** @var array{adapter: string, connection: string, meilisearch: array{url: ?string, key: ?string, http_client: string, timeout: float, max_duration: float}, loupe: array{directory: string}, index_entities: string, batch: int, synonyms: array{languages: list<string>, directory: ?string, store: ?string, rejected_forms: list<string>}, kit_subdirectory: ?string} $config */
        $builder->registerForAutoconfiguration(DocumentProvider::class)->addTag(self::PROVIDER_TAG);
        $builder->registerForAutoconfiguration(SynonymProvider::class)->addTag(self::SYNONYM_PROVIDER_TAG);
        $services = $container->services();
        $connection = new Reference(\sprintf('doctrine.dbal.%s_connection', $config['connection']));

        if ('meilisearch' === $config['adapter']) {
            $services->set(MeilisearchIndex::class)->args([new Reference($config['meilisearch']['http_client']), $config['meilisearch']['url'], $config['meilisearch']['key'], $config['meilisearch']['timeout'], $config['meilisearch']['max_duration']]);
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

        if (self::INDEX_OUTBOX === $config['index_entities']) {
            $this->loadOutbox($config['connection'], $container);
        } elseif (self::INDEX_DIRECT === $config['index_entities']) {
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
     * The entities of each flush recorded in the outbox, in the flush's transaction; the codec of their records, and
     * the listener that indexes them once the relay dispatches them.
     */
    private function loadOutbox(string $connection, ContainerConfigurator $container): void
    {
        $services = $container->services();
        $listener = $services->set(OutboxIndexingListener::class)
            ->args([new Reference(Search::class), new Reference($this->outbox)])
            ->tag('kernel.reset', ['method' => 'reset']);

        foreach (['onFlush', 'postPersist', 'postUpdate', 'postRemove', 'onClear'] as $event) {
            $listener->tag('doctrine.event_listener', ['event' => $event, 'connection' => $connection]);
        }

        $services->set(SearchOutboxCodec::class)->args([new Reference('doctrine')])->tag('phil_outbox.codec');
        $services->set(OutboxIndexer::class)
            ->args([new Reference(Search::class), new Reference('doctrine')])
            ->tag('kernel.event_listener', ['event' => IndexEntity::class, 'method' => 'index'])
            ->tag('kernel.event_listener', ['event' => RemoveDocument::class, 'method' => 'remove']);
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
