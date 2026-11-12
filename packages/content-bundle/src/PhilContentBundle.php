<?php

declare(strict_types=1);

namespace Phil\ContentBundle;

use DateTimeZone;
use Exception;
use Override;
use Phil\ContentBundle\Application\Content\SingleKinds;
use Phil\ContentBundle\Application\ContentManager;
use Phil\ContentBundle\Application\Contract\CanonicalAddresses;
use Phil\ContentBundle\Application\Contract\PublicationSchedule;
use Phil\ContentBundle\Application\DataMapper\BodyDataMapper;
use Phil\ContentBundle\Application\DataMapper\ExcerptDataMapper;
use Phil\ContentBundle\Application\DataMapper\LinkDataMapper;
use Phil\ContentBundle\Application\DataMapper\RoutableDataMapper;
use Phil\ContentBundle\Application\DataMapper\SeoDataMapper;
use Phil\ContentBundle\Application\DataMapper\TemplateDataMapper;
use Phil\ContentBundle\Application\Editing\ContentEditor;
use Phil\ContentBundle\Application\Editing\WorkingCopies;
use Phil\ContentBundle\Application\Merger\BodyMerger;
use Phil\ContentBundle\Application\Merger\ExcerptMerger;
use Phil\ContentBundle\Application\Merger\LinkMerger;
use Phil\ContentBundle\Application\Merger\RoutableMerger;
use Phil\ContentBundle\Application\Merger\SeoMerger;
use Phil\ContentBundle\Application\Merger\TemplateMerger;
use Phil\ContentBundle\Application\Merger\WorkflowMerger;
use Phil\ContentBundle\Application\Normalizer\BodyNormalizer;
use Phil\ContentBundle\Application\Normalizer\ExcerptNormalizer;
use Phil\ContentBundle\Application\Normalizer\LinkNormalizer;
use Phil\ContentBundle\Application\Normalizer\RoutableNormalizer;
use Phil\ContentBundle\Application\Normalizer\SeoNormalizer;
use Phil\ContentBundle\Application\Normalizer\TemplateNormalizer;
use Phil\ContentBundle\Application\Normalizer\WorkflowNormalizer;
use Phil\ContentBundle\Application\Pipeline\ContentAggregator;
use Phil\ContentBundle\Application\Pipeline\ContentDataMapper;
use Phil\ContentBundle\Application\Pipeline\ContentNormalizer;
use Phil\ContentBundle\Application\Pipeline\DataMapper;
use Phil\ContentBundle\Application\Pipeline\Merger;
use Phil\ContentBundle\Application\Pipeline\Normalizer;
use Phil\ContentBundle\Application\Publication\ContentArchive;
use Phil\ContentBundle\Application\Publication\ContentPublisher;
use Phil\ContentBundle\Application\Publication\ContentVersions;
use Phil\ContentBundle\Application\Publication\ContentWorkflow;
use Phil\ContentBundle\Application\Publication\DimensionContentCopier;
use Phil\ContentBundle\Application\Publication\LiveContentReader;
use Phil\ContentBundle\Application\Publication\PageEvents;
use Phil\ContentBundle\Application\Publication\PublicationMarkingStore;
use Phil\ContentBundle\Application\Publication\PublicationWorkflow;
use Phil\ContentBundle\Application\Publication\PublishedContents;
use Phil\ContentBundle\Application\Rendering\TemplateResolver;
use Phil\ContentBundle\Application\Routing\RouteResolver;
use Phil\ContentBundle\Application\Routing\RouteUpdater;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Infrastructure\Console\CreateContentCommand;
use Phil\ContentBundle\Infrastructure\Console\EditContentCommand;
use Phil\ContentBundle\Infrastructure\Console\ListContentCommand;
use Phil\ContentBundle\Infrastructure\Console\PreviewLinkCommand;
use Phil\ContentBundle\Infrastructure\Console\PublishContentCommand;
use Phil\ContentBundle\Infrastructure\Console\UnpublishContentCommand;
use Phil\ContentBundle\Infrastructure\Doctrine\ConstructorContentRootFactory;
use Phil\ContentBundle\Infrastructure\Doctrine\ContentRoots;
use Phil\ContentBundle\Infrastructure\Doctrine\DimensionContentMappingListener;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrineContentRootRepository;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrineContentTransaction;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrineDimensionContentRepository;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrinePublishedRowRepository;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrineRouteRepository;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrineWorkingCopyRepository;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\BodyCapabilityMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\CapabilityMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\ExcerptCapabilityMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\LinkCapabilityMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\RoutableCapabilityMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\SeoCapabilityMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\TemplateCapabilityMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\WorkflowCapabilityMapping;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\DimensionContentIdType;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\LocaleType;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\RouteIdType;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\StageType;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\VersionType;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\WorkingCopyIdType;
use Phil\ContentBundle\Infrastructure\Http\ArchiveController;
use Phil\ContentBundle\Infrastructure\Http\ContentAdminController;
use Phil\ContentBundle\Infrastructure\Http\ContentCanonicalAddresses;
use Phil\ContentBundle\Infrastructure\Http\ContentController;
use Phil\ContentBundle\Infrastructure\Http\ContentUrlGenerator;
use Phil\ContentBundle\Infrastructure\Http\FeedController;
use Phil\ContentBundle\Infrastructure\Http\PreviewController;
use Phil\ContentBundle\Infrastructure\Http\PreviewLinks;
use Phil\ContentBundle\Infrastructure\Http\ResourceRoutes;
use Phil\ContentBundle\Infrastructure\Markup\MarkupServices;
use Phil\ContentBundle\Infrastructure\Markup\RawHtmlAuthorPass;
use Phil\ContentBundle\Infrastructure\Scheduling\PublicationReferences;
use Phil\ContentBundle\Infrastructure\Scheduling\PublicationSubjects;
use Phil\ContentBundle\Infrastructure\Scheduling\ScheduledTransitionsPublicationSchedule;
use Phil\ContentBundle\Infrastructure\Search\ContentDocumentProvider;
use Phil\ContentBundle\Infrastructure\Sitemap\ContentUrlProvider;
use Phil\ContentBundle\Infrastructure\Twig\ContentExtension;
use Phil\ContentBundle\Infrastructure\Twig\ContentRuntime;
use Phil\ContentBundle\Infrastructure\Workflow\PublicationWorkflowPass;
use Phil\LocaleDimensions\DeclaredLocales;
use ReflectionClass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Workflow\StateMachine;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * Dimensioned content storage: the pipeline services, the declared locales, and — when the kernel registers
 * DoctrineBundle — the Doctrine storage of the rows.
 *
 * ```yaml
 * phil_content:
 *     locales:
 *         fr_CA: [fr]
 *         fr: []
 *     roots:
 *         - App\Content\Article
 * ```
 *
 * The pipeline stages are tagged by autoconfiguration, and the bundle's own data mappers, mergers and
 * normalizers carry the same tags, so `ContentDataMapper`, `ContentAggregator` and `ContentNormalizer` see the
 * application's beside them. The body's two ports, `MarkupCompiler` and `HtmlPurifier`, are aliased to the
 * bundle's adapters, which the application replaces by aliasing them to its own. With
 * DoctrineBundle, the bundle also registers its DBAL types and the mapping of its two base classes, the
 * listener that maps rows and capabilities, and the repository adapter.
 */
final class PhilContentBundle extends AbstractBundle
{
    public const string DATA_MAPPER_TAG = 'phil_content.data_mapper';

    public const string MERGER_TAG = 'phil_content.merger';

    public const string NORMALIZER_TAG = 'phil_content.normalizer';

    public const string CAPABILITY_MAPPING_TAG = 'phil_content.capability_mapping';

    public const string ADMIN_LAYOUT = '@PhilContent/admin/layout.html.twig';

    /**
     * The index of phil/search-bundle fed by default, named here so that the configuration never loads
     * ContentDocumentProvider, whose interface only exists when the search bundle is installed.
     */
    public const string SEARCH_INDEX = 'content';

    #[Override]
    protected string $extensionAlias = 'phil_content';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new PublicationWorkflowPass());
        $container->addCompilerPass(new RawHtmlAuthorPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('locales')
                    ->info('The served locales, each with the locales tried after it, in order: "fr_CA: [fr]".')
                    // A locale has one spelling: the default normalization would read "fr-CA" as "fr_CA".
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('locale')
                    ->arrayPrototype()
                        ->scalarPrototype()->end()
                    ->end()
                    ->validate()
                        ->always(static function (array $locales): array {
                            DeclaredLocales::fromArray($locales);

                            return $locales;
                        })
                    ->end()
                ->end()
                ->arrayNode('roots')
                    ->info('The content root classes; each names its row class.')
                    ->scalarPrototype()->end()
                    ->validate()
                        ->always(static function (array $roots): array {
                            ContentRoots::fromClassNames($roots);

                            return $roots;
                        })
                    ->end()
                ->end()
                ->arrayNode('singles')
                    ->info('The resource keys of the kinds that have a single content: the home page, the contact details.')
                    ->scalarPrototype()->end()
                    ->validate()
                        ->always(static function (array $singles): array {
                            SingleKinds::fromArray($singles);

                            return $singles;
                        })
                    ->end()
                ->end()
                ->arrayNode('archives')
                    ->info('The resource keys of the kinds whose every publication stays readable, dated: a "now" page.')
                    ->scalarPrototype()->end()
                    ->validate()
                        ->always(static function (array $archives): array {
                            foreach ($archives as $archive) {
                                if (!\is_string($archive) || !ContentReference::isResourceKey($archive)) {
                                    throw new InvalidConfigurationException(\sprintf('"phil_content.archives" lists resource keys; "%s" is not one.', \is_string($archive) ? $archive : get_debug_type($archive)));
                                }
                            }

                            return $archives;
                        })
                    ->end()
                ->end()
                ->scalarNode('entity_manager')
                    ->info('Service id of the entity manager that stores the rows.')
                    ->defaultValue('doctrine.orm.entity_manager')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('routes')
                    ->info('The Symfony routes that mount ContentController for each resource key, the one URLs are generated on first: "news: news_show", "static_page: [page_show, privacy]".')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('resource_key')
                    ->arrayPrototype()
                        ->beforeNormalization()->castToArray()->end()
                        ->requiresAtLeastOneElement()
                        ->scalarPrototype()->cannotBeEmpty()->end()
                    ->end()
                    ->validate()
                        ->always(static function (array $routes): array {
                            ResourceRoutes::fromConfiguration($routes);

                            return $routes;
                        })
                    ->end()
                ->end()
                ->arrayNode('templates')
                    ->info('The Twig template of each template key: "news: content/news.html.twig".')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('template_key')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->scalarNode('default_template')
                    ->info('The Twig template of a row whose template key is absent or not declared.')
                    ->defaultNull()
                ->end()
                ->arrayNode('preview')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('secret')
                            ->info('The secret preview links are signed with.')
                            ->defaultValue('%kernel.secret%')
                            ->cannotBeEmpty()
                        ->end()
                        ->integerNode('ttl')
                            ->info('How many seconds a preview link stays valid.')
                            ->min(60)
                            ->defaultValue(604_800)
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('raw_html_role')
                    ->info('The trusted role whose holders write raw HTML, shown in their drafts and published as phil/ujo\'s "raw" profile allows: "ROLE_EDITOR". Without it, raw HTML is shown and published as text. Needs SecurityBundle.')
                    ->defaultNull()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('search_index')
                    ->info('The index of phil/search-bundle the published contents are fed to.')
                    ->defaultValue(self::SEARCH_INDEX)
                    ->validate()
                        ->ifTrue(static fn (mixed $index): bool => !\is_string($index) || 1 !== preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $index))
                        ->thenInvalid('An index is 1 to 64 lower-case letters, digits or "_", starting with a letter; %s is not.')
                    ->end()
                ->end()
                ->arrayNode('admin')
                    ->info('The administration of the contents, mounted from config/routes_admin.php behind the firewall and linked from an EasyAdmin back office. Needs SecurityBundle and the CSRF protection.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('role')->defaultValue('ROLE_ADMIN')->cannotBeEmpty()->end()
                        ->scalarNode('layout')
                            ->info('The Twig layout the pages extend; it defines the "title" and "content" blocks.')
                            ->defaultValue(self::ADMIN_LAYOUT)
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('timezone')
                            ->info('The time zone of the moments the administration shows and schedules.')
                            ->defaultValue('UTC')
                            ->cannotBeEmpty()
                            ->validate()
                                ->ifTrue(static fn (mixed $timezone): bool => !self::isTimezone($timezone))
                                ->thenInvalid('%s is not a time zone identifier.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->integerNode('feed_limit')
                    ->info('How many entries a feed holds.')
                    ->min(1)
                    ->max(500)
                    ->defaultValue(20)
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        $builder->prependExtensionConfig('doctrine', [
            'dbal' => [
                'types' => [
                    DimensionContentIdType::NAME => DimensionContentIdType::class,
                    LocaleType::NAME => LocaleType::class,
                    RouteIdType::NAME => RouteIdType::class,
                    StageType::NAME => StageType::class,
                    VersionType::NAME => VersionType::class,
                    WorkingCopyIdType::NAME => WorkingCopyIdType::class,
                ],
            ],
            'orm' => [
                'mappings' => [
                    'PhilContentBundle' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__.'/Domain/Content',
                        'prefix' => 'Phil\ContentBundle\Domain\Content',
                    ],
                    'PhilContentBundleRouting' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__.'/Domain/Routing',
                        'prefix' => 'Phil\ContentBundle\Domain\Routing',
                    ],
                    'PhilContentBundleDraft' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__.'/Domain/Draft',
                        'prefix' => 'Phil\ContentBundle\Domain\Draft',
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->registerForAutoconfiguration(DataMapper::class)->addTag(self::DATA_MAPPER_TAG);
        $builder->registerForAutoconfiguration(Merger::class)->addTag(self::MERGER_TAG);
        $builder->registerForAutoconfiguration(Normalizer::class)->addTag(self::NORMALIZER_TAG);

        $services = $container->services();

        $services->set(DeclaredLocales::class)
            ->factory([DeclaredLocales::class, 'fromArray'])
            ->args([$config['locales'] ?? []]);

        $services->set(SingleKinds::class)
            ->factory([SingleKinds::class, 'fromArray'])
            ->args([$config['singles'] ?? []]);

        /** @var ?string $rawHtmlRole */
        $rawHtmlRole = $config['raw_html_role'];
        MarkupServices::register($services, $rawHtmlRole);

        foreach ([TemplateDataMapper::class, SeoDataMapper::class, ExcerptDataMapper::class, LinkDataMapper::class, RoutableDataMapper::class] as $mapper) {
            $services->set($mapper)->tag(self::DATA_MAPPER_TAG);
        }

        $services->set(BodyDataMapper::class)
            ->autowire()
            ->tag(self::DATA_MAPPER_TAG);

        foreach ([TemplateMerger::class, WorkflowMerger::class, SeoMerger::class, ExcerptMerger::class, LinkMerger::class, RoutableMerger::class, BodyMerger::class] as $merger) {
            $services->set($merger)->tag(self::MERGER_TAG);
        }

        foreach ([TemplateNormalizer::class, WorkflowNormalizer::class, SeoNormalizer::class, ExcerptNormalizer::class, LinkNormalizer::class, RoutableNormalizer::class] as $normalizer) {
            $services->set($normalizer)->tag(self::NORMALIZER_TAG);
        }

        $services->set(BodyNormalizer::class)
            ->autowire()
            ->tag(self::NORMALIZER_TAG);

        $services->set(ContentDataMapper::class)
            ->args([tagged_iterator(self::DATA_MAPPER_TAG)]);

        $services->set(ContentNormalizer::class)
            ->args([tagged_iterator(self::NORMALIZER_TAG)]);

        // The repository is autowired by its port: the Doctrine adapter below when DoctrineBundle is
        // installed, the application's own adapter otherwise.
        $services->set(ContentAggregator::class)
            ->autowire()
            ->arg('$mergers', tagged_iterator(self::MERGER_TAG));

        $this->loadPublication($config, $container);

        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        $builder->registerForAutoconfiguration(CapabilityMapping::class)->addTag(self::CAPABILITY_MAPPING_TAG);

        $services->set(DoctrineDimensionContentRepository::class)
            ->args([service($this->entityManagerId($config))]);

        // The adapter answers for the port it implements, named through the adapter: the bundle class wires
        // the infrastructure, and never reaches into the domain itself.
        foreach (new ReflectionClass(DoctrineDimensionContentRepository::class)->getInterfaceNames() as $port) {
            $services->alias($port, DoctrineDimensionContentRepository::class);
        }

        $services->set(ContentRoots::class)
            ->factory([ContentRoots::class, 'fromClassNames'])
            ->args([$config['roots'] ?? []]);

        $capabilityMappings = [
            TemplateCapabilityMapping::class,
            WorkflowCapabilityMapping::class,
            SeoCapabilityMapping::class,
            ExcerptCapabilityMapping::class,
            LinkCapabilityMapping::class,
            RoutableCapabilityMapping::class,
            BodyCapabilityMapping::class,
        ];

        foreach ($capabilityMappings as $mapping) {
            $services->set($mapping)->tag(self::CAPABILITY_MAPPING_TAG);
        }

        $services->set(DimensionContentMappingListener::class)
            ->args([service(ContentRoots::class), tagged_iterator(self::CAPABILITY_MAPPING_TAG)])
            ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);

        $this->loadDoctrinePersistence($config, $container);

        if ($this->hasBundle($builder, 'PhilScheduledTransitionsBundle')) {
            $this->loadScheduling($container);
        }

        if ($this->hasBundle($builder, 'PhilSearchBundle')) {
            // The search bundle's tag is named by its string, without depending on the bundle.
            $services->set(ContentDocumentProvider::class)
                ->autowire()
                ->arg('$index', $config['search_index'] ?? self::SEARCH_INDEX)
                ->tag('phil_search.provider');
        }

        if ($this->hasBundle($builder, 'TwigBundle')) {
            $this->loadRendering($config, $container);
        }
    }

    /**
     * The publication, versioning, routing and editing services: plain services over the ports, autowired, so
     * an application without DoctrineBundle provides the ports and gets them all the same.
     *
     * @param array<array-key, mixed> $config
     */
    private function loadPublication(array $config, ContainerConfigurator $container): void
    {
        $services = $container->services();

        $services->set(ContentWorkflow::class)
            ->factory([ContentWorkflow::class, 'create'])
            ->args([service('event_dispatcher')->nullOnInvalid()]);

        $services->set(DimensionContentCopier::class)
            ->args([tagged_iterator(self::MERGER_TAG)]);

        foreach ([ContentVersions::class, LiveContentReader::class, PublishedContents::class, RouteUpdater::class, RouteResolver::class, ContentEditor::class, ContentManager::class] as $service) {
            $services->set($service)->autowire();
        }

        $services->set(ContentArchive::class)
            ->autowire()
            ->arg('$resourceKeys', SingleKinds::fromArray(\is_array($config['archives'] ?? null) ? $config['archives'] : [])->all());

        $services->set(ContentPublisher::class)
            ->autowire()
            ->arg('$clock', service('clock'));

        // The events of phil/publishing: dispatched when the container has a dispatcher and the canonical addresses
        // (the router, with TwigBundle).
        $services->set(PageEvents::class)
            ->autowire()
            ->arg('$addresses', service(CanonicalAddresses::class)->nullOnInvalid())
            ->arg('$dispatcher', service('event_dispatcher')->nullOnInvalid());

        $services->set(PublicationMarkingStore::class)->autowire();
        $services->set(PublicationWorkflow::SERVICE, StateMachine::class)
            ->factory(PublicationWorkflow::create(...))
            ->args([service(PublicationMarkingStore::class), service('event_dispatcher')->nullOnInvalid()])
            ->public();

        $services->set(TemplateResolver::class)
            ->args([$config['templates'] ?? [], $config['default_template'] ?? null]);

        $services->set(ResourceRoutes::class)
            ->args([$config['routes'] ?? []]);
    }

    /**
     * The Doctrine adapters of the persistence ports, and the console commands that write through them.
     *
     * @param array<array-key, mixed> $config
     */
    private function loadDoctrinePersistence(array $config, ContainerConfigurator $container): void
    {
        $services = $container->services();
        $entityManager = service($this->entityManagerId($config));

        $services->set(DoctrineRouteRepository::class)->args([$entityManager]);
        $services->set(DoctrineContentRootRepository::class)->args([$entityManager, service(ContentRoots::class)]);
        $services->set(DoctrinePublishedRowRepository::class)->args([$entityManager, service(ContentRoots::class)]);
        $services->set(DoctrineContentTransaction::class)->args([$entityManager]);
        $services->set(DoctrineWorkingCopyRepository::class)->args([$entityManager]);
        $services->set(ConstructorContentRootFactory::class)->args([service(ContentRoots::class)]);

        foreach ([DoctrineRouteRepository::class, DoctrineContentRootRepository::class, DoctrinePublishedRowRepository::class, DoctrineContentTransaction::class, DoctrineWorkingCopyRepository::class, ConstructorContentRootFactory::class] as $adapter) {
            foreach (new ReflectionClass($adapter)->getInterfaceNames() as $port) {
                $services->alias($port, $adapter);
            }
        }

        $services->set(WorkingCopies::class)
            ->autowire()
            ->arg('$clock', service('clock'));

        foreach ([CreateContentCommand::class, EditContentCommand::class, PublishContentCommand::class, UnpublishContentCommand::class, ListContentCommand::class] as $command) {
            $services->set($command)->autowire()->tag('console.command');
        }
    }

    /**
     * The schedule of publications on `phil/scheduled-transitions-bundle`, and the resolver its workflow handler finds
     * the publications by. The bundle's tag is named by its string, without depending on the bundle.
     */
    private function loadScheduling(ContainerConfigurator $container): void
    {
        $services = $container->services();

        $services->set(PublicationReferences::class)
            ->factory([PublicationReferences::class, 'fromRoots'])
            ->args([service(ContentRoots::class)]);
        $services->set(PublicationSubjects::class)
            ->autowire()
            ->tag('phil_scheduled_transitions.subject_resolver');
        $services->set(ScheduledTransitionsPublicationSchedule::class)->autowire();
        $services->alias(PublicationSchedule::class, ScheduledTransitionsPublicationSchedule::class);
    }

    /**
     * The controllers — content, feeds, previews —, the pages of the sitemap, the URL generator, the preview links
     * and the Twig functions, with TwigBundle.
     *
     * @param array<array-key, mixed> $config
     */
    private function loadRendering(array $config, ContainerConfigurator $container): void
    {
        /** @var array{preview: array{secret: string, ttl: int}, feed_limit: int, admin: array{enabled: bool, role: string, layout: string, timezone: string}, roots?: list<string>} $config */
        $services = $container->services();

        $services->set(PreviewLinks::class)
            ->args([service('router'), $config['preview']['secret'], service('clock'), $config['preview']['ttl']]);
        $services->set(PreviewLinkCommand::class)->autowire()->tag('console.command');

        // After the pages of the routes, as the sitemap bundle places every provider; its route provider is named by
        // its class, without depending on the sitemap bundle: the constraint is ignored when it is not installed.
        $services->set(ContentUrlProvider::class)->autowire()->tag('phil_sitemap.url_provider', ['after' => 'Phil\\SitemapBundle\\Infrastructure\\Routing\\RouteUrlProvider']);
        $services->set(PreviewController::class)
            ->autowire()
            ->arg('$twig', service('twig'))
            ->public()
            ->tag('controller.service_arguments');
        $services->set(ArchiveController::class)
            ->autowire()
            ->arg('$twig', service('twig'))
            ->public()
            ->tag('controller.service_arguments');
        $services->set(FeedController::class)
            ->autowire()
            ->arg('$limit', $config['feed_limit'])
            ->public()
            ->tag('controller.service_arguments');

        $services->set(ContentUrlGenerator::class)
            ->autowire()
            ->arg('$urlGenerator', service('router'));

        $services->set(ContentCanonicalAddresses::class)
            ->autowire()
            ->arg('$allowLocalhost', '%kernel.debug%');
        $services->alias(CanonicalAddresses::class, ContentCanonicalAddresses::class);

        $services->set(ContentController::class)
            ->autowire()
            ->arg('$twig', service('twig'))
            ->arg('$urlGenerator', service('router'))
            ->public()
            ->tag('controller.service_arguments');

        $admin = $config['admin'];

        if ($admin['enabled']) {
            $services->set(ContentAdminController::class)
                ->autowire()
                ->arg('$twig', service('twig'))
                ->arg('$urls', service('router'))
                ->arg('$tokens', service('security.token_storage'))
                ->arg('$authorizationChecker', service('security.authorization_checker'))
                ->arg('$csrfTokens', service('security.csrf.token_manager'))
                ->arg('$resourceKeys', ContentRoots::fromClassNames($config['roots'] ?? [])->resourceKeys())
                ->arg('$role', $admin['role'])
                ->arg('$layout', $admin['layout'])
                ->arg('$timezone', $admin['timezone'])
                ->arg('$schedule', service(PublicationSchedule::class)->nullOnInvalid())
                ->public()
                ->tag('controller.service_arguments');
        }

        $services->set(ContentExtension::class)->tag('twig.extension');
        $services->set(ContentRuntime::class)
            ->autowire()
            ->arg('$requests', service('request_stack'))
            ->tag('twig.runtime');
    }

    /**
     * Whether the kernel registers `$bundle` (DoctrineBundle, TwigBundle), read from `kernel.bundles`.
     *
     * Not from `hasExtension('doctrine')`: the kernel loads each extension into a container of its own, where
     * no other extension is registered, so the question would always be answered no while loading.
     */
    private function hasBundle(ContainerBuilder $builder, string $bundle): bool
    {
        // Through the bag rather than `getParameter()`, which static analysis types from the container of
        // whatever application analyses the bundle, not from the kernel that loads it.
        $bundles = $builder->getParameterBag()->all()['kernel.bundles'] ?? null;

        return \is_array($bundles) && isset($bundles[$bundle]);
    }

    /**
     * Whether PHP knows the time zone, an identifier of the IANA database or one of its links: `America/Montreal`.
     */
    private static function isTimezone(mixed $timezone): bool
    {
        if (!\is_string($timezone) || '' === $timezone) {
            return false;
        }

        try {
            new DateTimeZone($timezone);
        } catch (Exception) {
            return false;
        }

        return true;
    }

    /**
     * A scalar node also accepts an integer or a boolean; a service id is only ever a string.
     *
     * @param array<array-key, mixed> $config
     */
    private function entityManagerId(array $config): string
    {
        $id = $config['entity_manager'] ?? null;

        if (!\is_string($id)) {
            throw new InvalidConfigurationException('"phil_content.entity_manager" must be a service id.');
        }

        return $id;
    }
}
