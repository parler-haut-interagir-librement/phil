<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Unit;

use LogicException;
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
use Phil\SearchBundle\PhilSearchBundle;
use Phil\SearchBundle\Tests\Unit\Fixtures\LoupeIndexStub;
use Phil\SearchSynonyms\Application\Contract\SynonymStore;
use Phil\SearchSynonyms\Infrastructure\Store\FileSynonymStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\AssetMapperBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilSearchBundle::class)]
final class PhilSearchBundleTest extends TestCase
{
    public function testTheDatabaseAdapter(): void
    {
        $container = $this->load([['connection' => 'content', 'batch' => 50]]);
        $connection = new Reference('doctrine.dbal.content_connection');

        self::assertSame('phil_search', $this->extension()->getAlias());
        self::assertSame(['service_container', DbalSearchIndex::class, SearchSchemaListener::class, SynonymExpander::class, Search::class, IndexOnFlushListener::class, ReindexCommand::class, SynonymsCommand::class, KitComponents::class, SearchFiltersComponent::class], array_keys($container->getDefinitions()));
        self::assertEquals([$connection], $container->getDefinition(DbalSearchIndex::class)->getArguments());
        self::assertSame(DbalSearchIndex::class, (string) $container->getAlias(SearchIndex::class));
        self::assertEquals([$connection], $container->getDefinition(SearchSchemaListener::class)->getArguments());
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'content']], $container->getDefinition(SearchSchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertEquals([new Reference(SearchIndex::class), new TaggedIteratorArgument('phil_search.provider'), 50, new Reference(SynonymExpander::class), []], $container->getDefinition(Search::class)->getArguments());
        self::assertEquals([new TaggedIteratorArgument('phil_search.synonym_provider')], $container->getDefinition(SynonymExpander::class)->getArguments());
        self::assertSame([['method' => 'reset']], $container->getDefinition(SynonymExpander::class)->getTag('kernel.reset'));
        self::assertTrue($container->getAutoconfiguredInstanceof()[SynonymProvider::class]->hasTag(PhilSearchBundle::SYNONYM_PROVIDER_TAG));
        self::assertEquals([new Reference(Search::class)], $container->getDefinition(SynonymsCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(SynonymsCommand::class)->hasTag('console.command'));
        self::assertSame([null], $container->getDefinition(KitComponents::class)->getArguments());
        self::assertEquals([new Reference(KitComponents::class)], $container->getDefinition(SearchFiltersComponent::class)->getArguments());
        self::assertTrue($container->getDefinition(SearchFiltersComponent::class)->isAutoconfigured());
        self::assertFalse($container->hasDefinition(SynonymStoreProvider::class));
        self::assertTrue($container->getDefinition(Search::class)->isPublic());
        self::assertEquals([new Reference(Search::class)], $container->getDefinition(IndexOnFlushListener::class)->getArguments());
        self::assertSame([['event' => 'onFlush', 'connection' => 'content'], ['event' => 'postFlush', 'connection' => 'content'], ['event' => 'onClear', 'connection' => 'content']], $container->getDefinition(IndexOnFlushListener::class)->getTag('doctrine.event_listener'));
        self::assertSame([['method' => 'reset']], $container->getDefinition(IndexOnFlushListener::class)->getTag('kernel.reset'));
        self::assertEquals([new Reference(Search::class)], $container->getDefinition(ReindexCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(ReindexCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getAutoconfiguredInstanceof()[DocumentProvider::class]->hasTag(PhilSearchBundle::PROVIDER_TAG));
        self::assertSame('phil_search.provider', PhilSearchBundle::PROVIDER_TAG);

        $defaults = $this->load([]);
        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $defaults->getDefinition(DbalSearchIndex::class)->getArguments());
        self::assertSame(500, $defaults->getDefinition(Search::class)->getArgument(2));
        self::assertFalse($this->load([['index_entities' => false]])->hasDefinition(IndexOnFlushListener::class));
        self::assertSame(1, $this->load([['batch' => 1]])->getDefinition(Search::class)->getArgument(2));
    }

    public function testTheMeilisearchAdapter(): void
    {
        $container = $this->load([['adapter' => 'meilisearch', 'meilisearch' => ['url' => 'http://meili:7700', 'key' => 'k']]]);

        self::assertEquals([new Reference('http_client'), 'http://meili:7700', 'k'], $container->getDefinition(MeilisearchIndex::class)->getArguments());
        self::assertSame(MeilisearchIndex::class, (string) $container->getAlias(SearchIndex::class));
        self::assertFalse($container->hasDefinition(DbalSearchIndex::class));
        self::assertFalse($container->hasDefinition(SearchSchemaListener::class));
        self::assertNull($this->load([['adapter' => 'meilisearch', 'meilisearch' => ['url' => 'http://meili']]])->getDefinition(MeilisearchIndex::class)->getArgument(2));

        foreach ([[['adapter' => 'meilisearch'], 'Meilisearch needs its URL: phil_search.meilisearch.url.'], [['batch' => 0], 'Should be greater than or equal to 1'], [['adapter' => 'elastic'], 'The value "elastic" is not allowed'], [['loupe' => ['directory' => '']], 'cannot contain an empty value']] as [$config, $message]) {
            try {
                $this->load([$config]);
                self::fail($message);
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString($message, $invalidConfigurationException->getMessage());
            }
        }

        self::assertTrue($this->load([['adapter' => 'dbal']])->hasDefinition(DbalSearchIndex::class));
    }

    public function testTheLoupeAdapterOfItsOwnPackage(): void
    {
        $container = $this->load([['adapter' => 'loupe', 'loupe' => ['directory' => '/srv/search']]], LoupeIndexStub::class);

        self::assertSame(PhilSearchBundle::LOUPE_FACTORY, $container->getDefinition(PhilSearchBundle::LOUPE_FACTORY)->getClass());
        $index = $container->getDefinition(LoupeIndexStub::class);
        self::assertSame(LoupeIndexStub::class, $index->getClass());
        self::assertEquals([new Reference(PhilSearchBundle::LOUPE_FACTORY), '/srv/search'], $index->getArguments());
        self::assertSame(LoupeIndexStub::class, (string) $container->getAlias(SearchIndex::class));
        self::assertFalse($container->hasDefinition(DbalSearchIndex::class));
        self::assertFalse($container->hasDefinition(SearchSchemaListener::class));
        self::assertSame('%kernel.project_dir%/var/loupe', $this->load([['adapter' => 'loupe']], LoupeIndexStub::class)->getDefinition(LoupeIndexStub::class)->getArgument(1));
        self::assertFalse($this->load([], LoupeIndexStub::class)->hasDefinition(LoupeIndexStub::class));
        self::assertFalse($this->load([['loupe' => ['directory' => '/srv/search']]], 'Missing\\LoupeIndex')->hasDefinition('Missing\\LoupeIndex'), 'the package missing, another adapter');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The Loupe adapter is phil/search-loupe: composer require phil/search-loupe.');
        $this->load([['adapter' => 'loupe']], 'Missing\\LoupeIndex');
    }

    public function testTheSynonyms(): void
    {
        $container = $this->load([['synonyms' => ['languages' => ['fr', 'en'], 'directory' => '/srv/synonyms', 'rejected_forms' => ['app.taxonomy_forms', 'app.other_forms']], 'kit_subdirectory' => 'ui']]);

        self::assertSame(['fr', 'en'], $container->getDefinition(Search::class)->getArgument(4));
        self::assertSame(['/srv/synonyms'], $container->getDefinition(FileSynonymStore::class)->getArguments());
        self::assertSame(FileSynonymStore::class, $container->getDefinition(FileSynonymStore::class)->getClass());
        self::assertEquals([new Reference(FileSynonymStore::class)], $container->getDefinition(SynonymStoreProvider::class)->getArguments());
        self::assertTrue($container->getDefinition(SynonymStoreProvider::class)->hasTag('phil_search.synonym_provider'));
        self::assertSame(RejectedFormSynonyms::class, $container->getDefinition('phil_search.rejected_forms.0')->getClass());
        self::assertEquals([new Reference('app.taxonomy_forms')], $container->getDefinition('phil_search.rejected_forms.0')->getArguments());
        self::assertEquals([new Reference('app.other_forms')], $container->getDefinition('phil_search.rejected_forms.1')->getArguments());
        self::assertTrue($container->getDefinition('phil_search.rejected_forms.1')->hasTag('phil_search.synonym_provider'));
        self::assertSame(['ui'], $container->getDefinition(KitComponents::class)->getArguments());

        $store = $this->load([['synonyms' => ['store' => 'app.synonyms']]]);
        self::assertEquals([new Reference('app.synonyms')], $store->getDefinition(SynonymStoreProvider::class)->getArguments());
        self::assertFalse($store->hasDefinition(FileSynonymStore::class));
        self::assertSame(SynonymStore::class, PhilSearchBundle::SYNONYM_STORE);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('A directory of synonyms is read by phil/search-synonyms: composer require phil/search-synonyms.');
        $this->load([['synonyms' => ['directory' => '/srv']]], fileSynonymStore: 'Missing\\FileStore');
    }

    public function testTheStimulusControllerIsMappedWhenAssetMapperIsThere(): void
    {
        $bundle = new PhilSearchBundle();
        $builder = new ContainerBuilder();
        $builder->registerExtension(new AssetMapperBundle()->getContainerExtension() ?? throw new LogicException('AssetMapper has an extension.'));

        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($builder);
        $bundle->build($builder);

        $paths = [];

        foreach ($builder->getExtensionConfig('asset_mapper') as $config) {
            self::assertIsArray($config);
            $given = $config['paths'] ?? null;
            self::assertIsArray($given);
            $paths = [...$paths, ...$given];
        }

        self::assertSame(['@phil/search-bundle'], array_values($paths));
        self::assertIsString(array_key_first($paths));
        self::assertFileExists(array_key_first($paths).'/filters_controller.js');
        $without = new ContainerBuilder();
        $extension->prepend($without);
        self::assertSame([], $without->getExtensionConfig('asset_mapper'));
        self::assertContains(KitSubdirectoryPass::class, array_map(static fn (object $pass): string => $pass::class, $builder->getCompilerPassConfig()->getBeforeOptimizationPasses()));
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs, string $loupeIndex = PhilSearchBundle::LOUPE_INDEX, string $fileSynonymStore = PhilSearchBundle::FILE_SYNONYM_STORE): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension($loupeIndex, $fileSynonymStore)->load($configs, $container);

        return $container;
    }

    private function extension(string $loupeIndex = PhilSearchBundle::LOUPE_INDEX, string $fileSynonymStore = PhilSearchBundle::FILE_SYNONYM_STORE): ExtensionInterface
    {
        $extension = new PhilSearchBundle($loupeIndex, $fileSynonymStore)->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
