<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit;

use Phil\ContentBundle\Application\Content\ContentRootFactory;
use Phil\ContentBundle\Application\Content\SingleKinds;
use Phil\ContentBundle\Application\ContentManager;
use Phil\ContentBundle\Application\Contract\CanonicalAddresses;
use Phil\ContentBundle\Application\Contract\ConversionLosses;
use Phil\ContentBundle\Application\Contract\PublicationSchedule;
use Phil\ContentBundle\Application\Contract\RawHtmlPermission;
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
use Phil\ContentBundle\Application\Persistence\ContentTransaction;
use Phil\ContentBundle\Application\Pipeline\ContentAggregator;
use Phil\ContentBundle\Application\Pipeline\ContentDataMapper;
use Phil\ContentBundle\Application\Pipeline\ContentNormalizer;
use Phil\ContentBundle\Application\Pipeline\DataMapper;
use Phil\ContentBundle\Application\Pipeline\Merger;
use Phil\ContentBundle\Application\Pipeline\Normalizer;
use Phil\ContentBundle\Application\Publication\BodyConverter;
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
use Phil\ContentBundle\Domain\Contract\WorkingCopyRepository;
use Phil\ContentBundle\Domain\Repository\ContentRootRepository;
use Phil\ContentBundle\Domain\Repository\DimensionContentRepository;
use Phil\ContentBundle\Domain\Repository\PublishedRowRepository;
use Phil\ContentBundle\Domain\Repository\RouteRepository;
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
use Phil\ContentBundle\Infrastructure\Markup\RawHtmlRole;
use Phil\ContentBundle\Infrastructure\Markup\UjoBodyConverter;
use Phil\ContentBundle\Infrastructure\Scheduling\PublicationReferences;
use Phil\ContentBundle\Infrastructure\Scheduling\PublicationSubjects;
use Phil\ContentBundle\Infrastructure\Scheduling\ScheduledTransitionsPublicationSchedule;
use Phil\ContentBundle\Infrastructure\Search\ContentDocumentProvider;
use Phil\ContentBundle\Infrastructure\Sitemap\ContentUrlProvider;
use Phil\ContentBundle\Infrastructure\Twig\ContentExtension;
use Phil\ContentBundle\Infrastructure\Twig\ContentRuntime;
use Phil\ContentBundle\Infrastructure\Workflow\PublicationWorkflowPass;
use Phil\ContentBundle\PhilContentBundle;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Article;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Page;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\HtmlPurifier;
use Phil\Markup\MarkupCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Workflow\StateMachine;

/**
 * What the bundle declares, read on an uncompiled `ContainerBuilder`: no kernel, no Doctrine. DoctrineBundle
 * is stood for by its entry in `kernel.bundles`, which is all the bundle asks about.
 */
#[CoversClass(PhilContentBundle::class)]
#[CoversClass(MarkupServices::class)]
final class PhilContentBundleTest extends TestCase
{
    /**
     * @param class-string $stage
     */
    #[DataProvider('stages')]
    public function testEachPipelineStageIsTaggedByAutoconfiguration(string $stage, string $tag): void
    {
        $conditionals = $this->load()->getAutoconfiguredInstanceof();

        self::assertArrayHasKey($stage, $conditionals);
        self::assertInstanceOf(Definition::class, $conditionals[$stage]);
        self::assertSame([[]], $conditionals[$stage]->getTag($tag));
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function stages(): iterable
    {
        yield 'data mapper' => [DataMapper::class, 'phil_content.data_mapper'];
        yield 'merger' => [Merger::class, 'phil_content.merger'];
        yield 'normalizer' => [Normalizer::class, 'phil_content.normalizer'];
    }

    /**
     * @param class-string $service
     */
    #[DataProvider('ownStages')]
    public function testTheBundlesOwnStagesCarryTheTagsOfTheApplications(string $service, string $tag): void
    {
        self::assertSame([[]], $this->load()->getDefinition($service)->getTag($tag));
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function ownStages(): iterable
    {
        yield 'template merger' => [TemplateMerger::class, 'phil_content.merger'];
        yield 'workflow merger' => [WorkflowMerger::class, 'phil_content.merger'];
        yield 'template normalizer' => [TemplateNormalizer::class, 'phil_content.normalizer'];
        yield 'workflow normalizer' => [WorkflowNormalizer::class, 'phil_content.normalizer'];
        yield 'SEO data mapper' => [SeoDataMapper::class, 'phil_content.data_mapper'];
        yield 'excerpt data mapper' => [ExcerptDataMapper::class, 'phil_content.data_mapper'];
        yield 'link data mapper' => [LinkDataMapper::class, 'phil_content.data_mapper'];
        yield 'routable data mapper' => [RoutableDataMapper::class, 'phil_content.data_mapper'];
        yield 'template data mapper' => [TemplateDataMapper::class, 'phil_content.data_mapper'];
        yield 'body data mapper' => [BodyDataMapper::class, 'phil_content.data_mapper'];
        yield 'SEO merger' => [SeoMerger::class, 'phil_content.merger'];
        yield 'excerpt merger' => [ExcerptMerger::class, 'phil_content.merger'];
        yield 'link merger' => [LinkMerger::class, 'phil_content.merger'];
        yield 'routable merger' => [RoutableMerger::class, 'phil_content.merger'];
        yield 'body merger' => [BodyMerger::class, 'phil_content.merger'];
        yield 'SEO normalizer' => [SeoNormalizer::class, 'phil_content.normalizer'];
        yield 'excerpt normalizer' => [ExcerptNormalizer::class, 'phil_content.normalizer'];
        yield 'link normalizer' => [LinkNormalizer::class, 'phil_content.normalizer'];
        yield 'routable normalizer' => [RoutableNormalizer::class, 'phil_content.normalizer'];
        yield 'body normalizer' => [BodyNormalizer::class, 'phil_content.normalizer'];
    }

    public function testTheContentDataMapperTakesEveryDataMapper(): void
    {
        $this->assertTaggedIterator('phil_content.data_mapper', $this->load()->getDefinition(ContentDataMapper::class)->getArgument(0));
    }

    /**
     * The three ports of the body answer by an alias, which the application points to its own service.
     */
    public function testTheBodyPortsAreAliasedToTheBundlesAdapters(): void
    {
        $container = $this->load();

        self::assertTrue($container->hasDefinition(DefaultCompiler::class));
        self::assertSame(DefaultCompiler::class, (string) $container->getAlias(MarkupCompiler::class));
        self::assertSame(SanitizerPurifier::class, (string) $container->getAlias(HtmlPurifier::class));
        self::assertSame([SanitizerPurifier::class, 'defaultPolicies'], $container->getDefinition(SanitizerPurifier::class)->getFactory());
        self::assertTrue($container->getDefinition(BodyDataMapper::class)->isAutowired());
        self::assertTrue($container->getDefinition(BodyNormalizer::class)->isAutowired());
        self::assertSame(UjoBodyConverter::class, (string) $container->getAlias(BodyConverter::class));
        self::assertSame(UjoBodyConverter::class, (string) $container->getAlias(ConversionLosses::class));
        self::assertTrue($container->getDefinition(UjoBodyConverter::class)->isAutowired());
    }

    /**
     * Raw HTML is shown and published as text unless a trusted role is configured; that role is checked by Symfony's
     * authorization checker, and is the permission the body data mapper and the converter read.
     */
    public function testATrustedRoleLetsItsHoldersPublishRawHtml(): void
    {
        $container = $this->load();
        self::assertFalse($container->hasDefinition(RawHtmlRole::class));
        self::assertFalse($container->hasAlias(RawHtmlPermission::class));
        self::assertSame([], $container->getDefinition(UjoBodyConverter::class)->getArguments());

        $container = $this->load(['raw_html_role' => 'ROLE_EDITOR']);
        $role = $container->getDefinition(RawHtmlRole::class);
        $this->assertReference('security.authorization_checker', $role->getArgument(0));
        self::assertSame('ROLE_EDITOR', $role->getArgument(1));
        $this->assertReference(RawHtmlPermission::class, $container->getDefinition(UjoBodyConverter::class)->getArgument('$rawHtml'));
        self::assertSame(RawHtmlRole::class, (string) $container->getAlias(RawHtmlPermission::class));
    }

    public function testTheTrustedRoleCannotBeEmpty(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['raw_html_role' => '']);
    }

    public function testTheAggregatorTakesEveryMergerAndAutowiresItsRepository(): void
    {
        $aggregator = $this->load()->getDefinition(ContentAggregator::class);

        self::assertTrue($aggregator->isAutowired());
        $this->assertTaggedIterator('phil_content.merger', $aggregator->getArgument('$mergers'));
    }

    public function testTheContentNormalizerTakesEveryNormalizer(): void
    {
        $this->assertTaggedIterator('phil_content.normalizer', $this->load()->getDefinition(ContentNormalizer::class)->getArgument(0));
    }

    public function testTheDeclaredLocalesAreBuiltFromTheConfiguration(): void
    {
        $definition = $this->load(['locales' => ['fr_CA' => ['fr'], 'fr' => []]])->getDefinition(DeclaredLocales::class);

        self::assertSame([DeclaredLocales::class, 'fromArray'], $definition->getFactory());
        self::assertSame([['fr_CA' => ['fr'], 'fr' => []]], $definition->getArguments());
    }

    public function testNothingDeclaredDeclaresNoLocale(): void
    {
        self::assertSame([[]], $this->load()->getDefinition(DeclaredLocales::class)->getArguments());
    }

    /**
     * A locale has one spelling. Symfony's default would read the key `fr-CA` as `fr_CA` and accept it.
     */
    public function testALocaleKeyIsNotNormalized(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('"fr-CA" is not a locale');

        $this->load(['locales' => ['fr-CA' => []]]);
    }

    public function testAFallbackThatIsNotServedIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The fallback "fr" of "fr_CA" is not a served locale.');

        $this->load(['locales' => ['fr_CA' => ['fr']]]);
    }

    public function testWithoutDoctrineNothingOfDoctrineIsRegistered(): void
    {
        $container = $this->load(['roots' => [Article::class]]);

        self::assertFalse($container->hasDefinition(DoctrineDimensionContentRepository::class));
        self::assertFalse($container->hasAlias(DimensionContentRepository::class));
        self::assertFalse($container->hasDefinition(DimensionContentMappingListener::class));
        self::assertArrayNotHasKey(CapabilityMapping::class, $container->getAutoconfiguredInstanceof());
    }

    public function testWithoutDoctrineNothingIsPrepended(): void
    {
        $container = new ContainerBuilder();
        $this->prepend($container);

        self::assertSame([], $container->getExtensionConfig('doctrine'));
    }

    /**
     * A kernel that registers other bundles, but not DoctrineBundle.
     */
    public function testAKernelWithoutDoctrineBundleGetsNoDoctrineService(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['FrameworkBundle' => 'FrameworkBundle']);
        $this->prepend($container);
        $this->load([], $container);

        self::assertSame([], $container->getExtensionConfig('doctrine'));
        self::assertFalse($container->hasDefinition(DoctrineDimensionContentRepository::class));
    }

    /**
     * A parameter that is not a list of bundles says nothing about DoctrineBundle.
     */
    public function testAKernelBundlesParameterThatIsNotAnArrayMeansNoDoctrine(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', 'DoctrineBundle');
        $this->load([], $container);

        self::assertFalse($container->hasDefinition(DoctrineDimensionContentRepository::class));
    }

    /**
     * The roots are checked when the configuration is read, not when Doctrine first loads a row.
     */
    public function testARootThatIsNotAStoredRootIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains(ExampleRoot::class);

        $this->load(['roots' => [ExampleRoot::class]]);
    }

    public function testWithDoctrineTheRepositoryAnswersForThePort(): void
    {
        $container = $this->loadWithDoctrine();

        $this->assertReference('doctrine.orm.entity_manager', $container->getDefinition(DoctrineDimensionContentRepository::class)->getArgument(0));
        self::assertSame(DoctrineDimensionContentRepository::class, (string) $container->getAlias(DimensionContentRepository::class));
    }

    public function testTheEntityManagerIsConfigurable(): void
    {
        $container = $this->loadWithDoctrine(['entity_manager' => 'doctrine.orm.content_entity_manager']);

        $this->assertReference('doctrine.orm.content_entity_manager', $container->getDefinition(DoctrineDimensionContentRepository::class)->getArgument(0));
    }

    public function testANonStringEntityManagerIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('"phil_content.entity_manager" must be a service id.');

        $this->loadWithDoctrine(['entity_manager' => 5]);
    }

    public function testTheListenerMapsOnLoadClassMetadataWithTheDeclaredRootsAndEveryCapability(): void
    {
        $container = $this->loadWithDoctrine(['roots' => [Article::class, Page::class]]);
        $listener = $container->getDefinition(DimensionContentMappingListener::class);
        $roots = $container->getDefinition(ContentRoots::class);

        self::assertSame([['event' => 'loadClassMetadata']], $listener->getTag('doctrine.event_listener'));
        $this->assertReference(ContentRoots::class, $listener->getArgument(0));
        $this->assertTaggedIterator('phil_content.capability_mapping', $listener->getArgument(1));
        self::assertSame([ContentRoots::class, 'fromClassNames'], $roots->getFactory());
        self::assertSame([[Article::class, Page::class]], $roots->getArguments());
    }

    public function testTheCapabilityMappingsAreTaggedTheBundlesAndTheApplications(): void
    {
        $container = $this->loadWithDoctrine();
        $conditionals = $container->getAutoconfiguredInstanceof();

        self::assertSame([[]], $container->getDefinition(TemplateCapabilityMapping::class)->getTag('phil_content.capability_mapping'));
        self::assertSame([[]], $container->getDefinition(WorkflowCapabilityMapping::class)->getTag('phil_content.capability_mapping'));
        self::assertSame([[]], $container->getDefinition(SeoCapabilityMapping::class)->getTag('phil_content.capability_mapping'));
        self::assertSame([[]], $container->getDefinition(ExcerptCapabilityMapping::class)->getTag('phil_content.capability_mapping'));
        self::assertSame([[]], $container->getDefinition(LinkCapabilityMapping::class)->getTag('phil_content.capability_mapping'));
        self::assertSame([[]], $container->getDefinition(RoutableCapabilityMapping::class)->getTag('phil_content.capability_mapping'));
        self::assertSame([[]], $container->getDefinition(BodyCapabilityMapping::class)->getTag('phil_content.capability_mapping'));
        self::assertArrayHasKey(CapabilityMapping::class, $conditionals);
        self::assertSame([[]], $conditionals[CapabilityMapping::class]->getTag('phil_content.capability_mapping'));
    }

    public function testWithDoctrineTheTypesAndTheBaseClassMappingArePrepended(): void
    {
        $container = $this->kernelWithDoctrine();
        $this->prepend($container);

        $config = $container->getExtensionConfig('doctrine');

        self::assertCount(1, $config);
        self::assertIsArray($config[0]);
        $dbal = $config[0]['dbal'] ?? null;
        $orm = $config[0]['orm'] ?? null;
        self::assertIsArray($dbal);
        self::assertIsArray($orm);
        self::assertIsArray($orm['mappings'] ?? null);
        self::assertSame([
            'phil_content_dimension_content_id' => DimensionContentIdType::class,
            'phil_content_locale' => LocaleType::class,
            'phil_content_route_id' => RouteIdType::class,
            'phil_content_stage' => StageType::class,
            'phil_content_version' => VersionType::class,
            'phil_content_working_copy_id' => WorkingCopyIdType::class,
        ], $dbal['types'] ?? null);
        $draft = $orm['mappings']['PhilContentBundleDraft'] ?? null;
        self::assertIsArray($draft);
        self::assertSame('attribute', $draft['type']);
        self::assertFalse($draft['is_bundle']);
        self::assertSame('Phil\ContentBundle\Domain\Draft', $draft['prefix']);
        self::assertIsString($draft['dir']);
        self::assertFileExists($draft['dir'].'/WorkingCopy.php');
        $mapping = $orm['mappings']['PhilContentBundle'] ?? null;
        self::assertIsArray($mapping);
        self::assertSame('attribute', $mapping['type']);
        self::assertFalse($mapping['is_bundle']);
        self::assertSame('Phil\ContentBundle\Domain\Content', $mapping['prefix']);
        self::assertIsString($mapping['dir']);
        self::assertFileExists($mapping['dir'].'/AbstractDimensionContent.php');
        $routing = $orm['mappings']['PhilContentBundleRouting'] ?? null;
        self::assertIsArray($routing);
        self::assertSame('attribute', $routing['type']);
        self::assertFalse($routing['is_bundle']);
        self::assertSame('Phil\ContentBundle\Domain\Routing', $routing['prefix']);
        self::assertIsString($routing['dir']);
        self::assertFileExists($routing['dir'].'/Route.php');
    }

    public function testThePublicationServicesAreRegisteredWithoutDoctrine(): void
    {
        $container = $this->load();

        foreach ([ContentVersions::class, LiveContentReader::class, PublishedContents::class, RouteUpdater::class, RouteResolver::class, ContentEditor::class, ContentManager::class, ContentPublisher::class] as $service) {
            self::assertTrue($container->getDefinition($service)->isAutowired(), $service);
        }

        self::assertSame([ContentWorkflow::class, 'create'], $container->getDefinition(ContentWorkflow::class)->getFactory());
        $this->assertReference('event_dispatcher', $container->getDefinition(ContentWorkflow::class)->getArgument(0));
        $this->assertReference('clock', $container->getDefinition(ContentPublisher::class)->getArgument('$clock'));
        self::assertTrue($container->getDefinition(PublicationMarkingStore::class)->isAutowired());
        $publication = $container->getDefinition(PublicationWorkflow::SERVICE);
        self::assertSame(StateMachine::class, $publication->getClass());
        self::assertSame([PublicationWorkflow::class, 'create'], $publication->getFactory());
        self::assertTrue($publication->isPublic());
        $this->assertReference(PublicationMarkingStore::class, $publication->getArgument(0));
        $this->assertReference('event_dispatcher', $publication->getArgument(1));
        $this->assertTaggedIterator('phil_content.merger', $container->getDefinition(DimensionContentCopier::class)->getArgument(0));
        self::assertSame([[]], $container->getDefinition(TemplateDataMapper::class)->getTag('phil_content.data_mapper'));
        self::assertFalse($container->hasDefinition(CreateContentCommand::class));
        self::assertFalse($container->hasDefinition(ContentController::class));
    }

    public function testTheRoutesAndTemplatesAreReadFromTheConfiguration(): void
    {
        $container = $this->load([
            'routes' => ['news' => 'news_show'],
            'templates' => ['news' => 'content/news.html.twig'],
            'default_template' => 'content/page.html.twig',
        ]);

        self::assertSame([['news' => ['news_show']]], $container->getDefinition(ResourceRoutes::class)->getArguments());
        self::assertSame([['news' => 'content/news.html.twig'], 'content/page.html.twig'], $container->getDefinition(TemplateResolver::class)->getArguments());
    }

    /**
     * A key is the application's own name: Symfony's default would read `news-item` as `news_item`.
     */
    public function testRouteAndTemplateKeysAreNotNormalized(): void
    {
        $container = $this->load(['routes' => ['news-item' => 'news_show'], 'templates' => ['news-item' => 'news.html.twig']]);

        self::assertSame([['news-item' => ['news_show']]], $container->getDefinition(ResourceRoutes::class)->getArguments());
        self::assertSame([['news-item' => 'news.html.twig'], null], $container->getDefinition(TemplateResolver::class)->getArguments());
    }

    public function testAKindMayBeMountedOnSeveralRoutes(): void
    {
        $container = $this->load(['routes' => ['news' => 'news_show', 'static_page' => ['page_show', 'privacy']]]);

        self::assertSame([['news' => ['news_show'], 'static_page' => ['page_show', 'privacy']]], $container->getDefinition(ResourceRoutes::class)->getArguments());
    }

    public function testARouteServingTwoKindsIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The route "page_show" serves both "news" and "static_page"');

        $this->load(['routes' => ['news' => 'page_show', 'static_page' => ['page_show']]]);
    }

    public function testAKindWithoutRouteIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['routes' => ['static_page' => []]]);
    }

    public function testNothingDeclaredDeclaresNoRouteAndNoTemplate(): void
    {
        $container = $this->load();

        self::assertSame([[]], $container->getDefinition(ResourceRoutes::class)->getArguments());
        self::assertSame([[], null], $container->getDefinition(TemplateResolver::class)->getArguments());
    }

    public function testWithDoctrineThePersistencePortsAndTheCommandsAreRegistered(): void
    {
        $container = $this->loadWithDoctrine();

        self::assertSame(DoctrineRouteRepository::class, (string) $container->getAlias(RouteRepository::class));
        self::assertSame(DoctrineContentRootRepository::class, (string) $container->getAlias(ContentRootRepository::class));
        self::assertSame(DoctrinePublishedRowRepository::class, (string) $container->getAlias(PublishedRowRepository::class));
        self::assertSame(DoctrineContentTransaction::class, (string) $container->getAlias(ContentTransaction::class));
        self::assertSame(ConstructorContentRootFactory::class, (string) $container->getAlias(ContentRootFactory::class));
        $this->assertReference('doctrine.orm.entity_manager', $container->getDefinition(DoctrineRouteRepository::class)->getArgument(0));
        $this->assertReference('doctrine.orm.entity_manager', $container->getDefinition(DoctrineContentRootRepository::class)->getArgument(0));
        $this->assertReference(ContentRoots::class, $container->getDefinition(DoctrineContentRootRepository::class)->getArgument(1));
        $this->assertReference('doctrine.orm.entity_manager', $container->getDefinition(DoctrinePublishedRowRepository::class)->getArgument(0));
        $this->assertReference(ContentRoots::class, $container->getDefinition(DoctrinePublishedRowRepository::class)->getArgument(1));
        $this->assertReference('doctrine.orm.entity_manager', $container->getDefinition(DoctrineContentTransaction::class)->getArgument(0));
        $this->assertReference(ContentRoots::class, $container->getDefinition(ConstructorContentRootFactory::class)->getArgument(0));
        $this->assertReference('doctrine.orm.entity_manager', $container->getDefinition(DoctrineWorkingCopyRepository::class)->getArgument(0));
        self::assertSame(DoctrineWorkingCopyRepository::class, (string) $container->getAlias(WorkingCopyRepository::class));
        $workingCopies = $container->getDefinition(WorkingCopies::class);
        self::assertTrue($workingCopies->isAutowired());
        $this->assertReference('clock', $workingCopies->getArgument('$clock'));

        foreach ([CreateContentCommand::class, EditContentCommand::class, PublishContentCommand::class, UnpublishContentCommand::class, ListContentCommand::class] as $command) {
            self::assertSame([[]], $container->getDefinition($command)->getTag('console.command'), $command);
        }

        self::assertFalse($container->hasDefinition(ContentController::class), 'The controller needs TwigBundle.');
        self::assertFalse($container->hasDefinition(ContentExtension::class));
    }

    public function testWithTwigTheControllerAndTheTwigFunctionsAreRegistered(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['DoctrineBundle' => 'DoctrineBundle', 'TwigBundle' => 'TwigBundle']);
        $this->load([], $container);

        $controller = $container->getDefinition(ContentController::class);
        self::assertTrue($controller->isPublic());
        self::assertSame([[]], $controller->getTag('controller.service_arguments'));
        $this->assertReference('twig', $controller->getArgument('$twig'));
        $this->assertReference('router', $controller->getArgument('$urlGenerator'));
        $this->assertReference('router', $container->getDefinition(ContentUrlGenerator::class)->getArgument('$urlGenerator'));
        self::assertSame([[]], $container->getDefinition(ContentExtension::class)->getTag('twig.extension'));
        self::assertSame([[]], $container->getDefinition(ContentRuntime::class)->getTag('twig.runtime'));
        $this->assertReference('request_stack', $container->getDefinition(ContentRuntime::class)->getArgument('$requests'));

        $links = $container->getDefinition(PreviewLinks::class);
        $this->assertReference('router', $links->getArgument(0));
        self::assertSame(['%kernel.secret%', 604_800], [$links->getArgument(1), $links->getArgument(3)]);
        $this->assertReference('clock', $links->getArgument(2));
        self::assertTrue($container->getDefinition(PreviewLinkCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(PreviewLinkCommand::class)->isAutowired());

        self::assertTrue($container->getDefinition(ContentUrlProvider::class)->isAutowired());
        self::assertSame([['after' => 'Phil\\SitemapBundle\\Infrastructure\\Routing\\RouteUrlProvider']], $container->getDefinition(ContentUrlProvider::class)->getTag('phil_sitemap.url_provider'));

        foreach ([PreviewController::class, FeedController::class] as $endpoint) {
            $definition = $container->getDefinition($endpoint);
            self::assertTrue($definition->isPublic(), $endpoint);
            self::assertTrue($definition->isAutowired(), $endpoint);
            self::assertSame([[]], $definition->getTag('controller.service_arguments'), $endpoint);
        }

        $this->assertReference('twig', $container->getDefinition(PreviewController::class)->getArgument('$twig'));
        self::assertSame(20, $container->getDefinition(FeedController::class)->getArgument('$limit'));
    }

    public function testWithScheduledTransitionsThePublicationScheduleIsRegistered(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['DoctrineBundle' => 'DoctrineBundle', 'PhilScheduledTransitionsBundle' => 'PhilScheduledTransitionsBundle']);
        $this->load([], $container);

        self::assertSame(ScheduledTransitionsPublicationSchedule::class, (string) $container->getAlias(PublicationSchedule::class));
        self::assertSame([[]], $container->getDefinition(PublicationSubjects::class)->getTag('phil_scheduled_transitions.subject_resolver'));
        self::assertSame([PublicationReferences::class, 'fromRoots'], $container->getDefinition(PublicationReferences::class)->getFactory());
        self::assertTrue($container->getDefinition(ScheduledTransitionsPublicationSchedule::class)->isAutowired());
        $this->assertReference(ContentRoots::class, $container->getDefinition(PublicationReferences::class)->getArgument(0));
        self::assertFalse($this->loadWithDoctrine()->hasAlias(PublicationSchedule::class), 'Without the bundle, nothing is scheduled.');
    }

    public function testWithTheSearchBundleThePublishedContentsAreFedToItsIndex(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['DoctrineBundle' => 'DoctrineBundle', 'PhilSearchBundle' => 'PhilSearchBundle']);
        $this->load(['search_index' => 'pages'], $container);

        $provider = $container->getDefinition(ContentDocumentProvider::class);
        self::assertSame([[]], $provider->getTag('phil_search.provider'));
        self::assertSame('pages', $provider->getArgument('$index'));
        self::assertTrue($provider->isAutowired());
        self::assertFalse($this->loadWithDoctrine()->hasDefinition(ContentDocumentProvider::class), 'Without the bundle, nothing is fed.');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notIndexNames(): iterable
    {
        yield 'a capital' => ['Pages'];
        yield 'a dash' => ['pages-all'];
        yield 'a new line at the end' => ["pages\n"];
        yield 'a digit first' => ['1pages'];
        yield 'too long' => ['a'.str_repeat('b', 64)];
    }

    #[DataProvider('notIndexNames')]
    public function testASearchIndexThatIsNotAnIndexNameIsRefused(string $index): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['search_index' => $index]);
    }

    public function testASearchIndexOfTheLongestNameIsAccepted(): void
    {
        $index = 'a'.str_repeat('b', 63);
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['DoctrineBundle' => 'DoctrineBundle', 'PhilSearchBundle' => 'PhilSearchBundle']);

        $this->load(['search_index' => $index], $container);

        self::assertSame($index, $container->getDefinition(ContentDocumentProvider::class)->getArgument('$index'));
    }

    public function testTheAdministrationIsOffUntilEnabled(): void
    {
        $twig = static function (): ContainerBuilder {
            $container = new ContainerBuilder();
            $container->setParameter('kernel.bundles', ['DoctrineBundle' => 'DoctrineBundle', 'TwigBundle' => 'TwigBundle']);

            return $container;
        };

        self::assertFalse($this->load([], $twig())->hasDefinition(ContentAdminController::class));

        $admin = $this->load(['roots' => [Article::class], 'admin' => ['enabled' => true, 'role' => 'ROLE_EDITOR', 'timezone' => 'America/Montreal']], $twig())->getDefinition(ContentAdminController::class);
        self::assertTrue($admin->isPublic());
        self::assertSame([[]], $admin->getTag('controller.service_arguments'));
        self::assertSame('ROLE_EDITOR', $admin->getArgument('$role'));
        self::assertSame(PhilContentBundle::ADMIN_LAYOUT, $admin->getArgument('$layout'));
        self::assertSame('America/Montreal', $admin->getArgument('$timezone'));
        self::assertSame([Article::resourceKey()], $admin->getArgument('$resourceKeys'));
        $this->assertReference('security.token_storage', $admin->getArgument('$tokens'));
        $this->assertReference('security.authorization_checker', $admin->getArgument('$authorizationChecker'));
        $this->assertReference('security.csrf.token_manager', $admin->getArgument('$csrfTokens'));
        $this->assertReference(PublicationSchedule::class, $admin->getArgument('$schedule'));
    }

    public function testATimeZoneThatIsNotAStringIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['admin' => ['enabled' => true, 'timezone' => 123]]);
    }

    public function testAnUnknownTimeZoneIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['admin' => ['enabled' => true, 'timezone' => 'Mars/Olympus']]);
    }

    public function testThePageEventsReadTheDispatcherAndTheCanonicalAddresses(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['DoctrineBundle' => 'DoctrineBundle', 'TwigBundle' => 'TwigBundle']);
        $this->load([], $container);

        $events = $container->getDefinition(PageEvents::class);
        $dispatcher = $events->getArgument('$dispatcher');
        self::assertInstanceOf(Reference::class, $dispatcher);
        self::assertSame('event_dispatcher', (string) $dispatcher);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $dispatcher->getInvalidBehavior());
        $this->assertReference(CanonicalAddresses::class, $events->getArgument('$addresses'));
        self::assertSame(ContentCanonicalAddresses::class, (string) $container->getAlias(CanonicalAddresses::class));
        self::assertSame('%kernel.debug%', $container->getDefinition(ContentCanonicalAddresses::class)->getArgument('$allowLocalhost'));
        self::assertTrue($container->getDefinition(ArchiveController::class)->isPublic());
        self::assertSame([[]], $container->getDefinition(ArchiveController::class)->getTag('controller.service_arguments'));
    }

    public function testTheSinglesAndTheArchivesAreReadFromTheConfiguration(): void
    {
        $container = $this->load(['singles' => ['home_page', 'contact'], 'archives' => ['now_page', 'now_page']]);

        self::assertSame([['home_page', 'contact']], $container->getDefinition(SingleKinds::class)->getArguments());
        self::assertSame([SingleKinds::class, 'fromArray'], $container->getDefinition(SingleKinds::class)->getFactory());
        self::assertSame(['now_page', 'about_page'], $this->load(['archives' => ['now_page', 'about_page']])->getDefinition(ContentArchive::class)->getArgument('$resourceKeys'));
        self::assertSame(['now_page'], $container->getDefinition(ContentArchive::class)->getArgument('$resourceKeys'));
        self::assertSame([], $this->load()->getDefinition(ContentArchive::class)->getArgument('$resourceKeys'));
    }

    public function testASingleThatIsNotAResourceKeyIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['singles' => ['Home']]);
    }

    public function testAnArchiveThatIsNotAResourceKeyIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('"phil_content.archives" lists resource keys; "now-page" is not one.');

        $this->load(['archives' => ['now-page']]);
    }

    public function testThePreviewAndTheFeedsAreConfigurable(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['DoctrineBundle' => 'DoctrineBundle', 'TwigBundle' => 'TwigBundle']);
        $this->load(['preview' => ['secret' => 's3cret', 'ttl' => 60], 'feed_limit' => 500], $container);

        self::assertSame(['s3cret', 60], [$container->getDefinition(PreviewLinks::class)->getArgument(1), $container->getDefinition(PreviewLinks::class)->getArgument(3)]);
        self::assertSame(500, $container->getDefinition(FeedController::class)->getArgument('$limit'));

        foreach ([[['preview' => ['ttl' => 59]], 'Should be greater than or equal to 60'], [['preview' => ['secret' => '']], 'cannot contain an empty value'], [['feed_limit' => 0], 'Should be greater than or equal to 1'], [['feed_limit' => 501], 'Should be less than or equal to 500']] as [$config, $message]) {
            try {
                $this->load($config);
                self::fail($message);
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString($message, $invalidConfigurationException->getMessage());
            }
        }

        self::assertSame(1, $this->load(['feed_limit' => 1], (static function (): ContainerBuilder {
            $twig = new ContainerBuilder();
            $twig->setParameter('kernel.bundles', ['DoctrineBundle' => 'DoctrineBundle', 'TwigBundle' => 'TwigBundle']);

            return $twig;
        })())->getDefinition(FeedController::class)->getArgument('$limit'));
    }

    public function testTheBuildAddsThePassOfThePublicationWorkflow(): void
    {
        $container = new ContainerBuilder();
        new PhilContentBundle()->build($container);

        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getPasses(), static fn (object $pass): bool => $pass instanceof PublicationWorkflowPass));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config = [], ?ContainerBuilder $container = null): ContainerBuilder
    {
        $container ??= new ContainerBuilder();
        $extension = new PhilContentBundle()->getContainerExtension();
        self::assertNotNull($extension);
        self::assertSame('phil_content', $extension->getAlias());

        $extension->load([$config], $container);

        return $container;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function loadWithDoctrine(array $config = []): ContainerBuilder
    {
        return $this->load($config, $this->kernelWithDoctrine());
    }

    /**
     * What the kernel sets before any extension is loaded, in every container it loads them into.
     */
    private function kernelWithDoctrine(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['FrameworkBundle' => 'FrameworkBundle', 'DoctrineBundle' => 'DoctrineBundle']);

        return $container;
    }

    private function prepend(ContainerBuilder $container): void
    {
        $extension = new PhilContentBundle()->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);

        $extension->prepend($container);
    }

    private function assertTaggedIterator(string $tag, mixed $argument): void
    {
        self::assertInstanceOf(TaggedIteratorArgument::class, $argument);
        self::assertSame($tag, $argument->getTag());
    }

    private function assertReference(string $id, mixed $argument): void
    {
        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame($id, (string) $argument);
    }
}
