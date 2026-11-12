<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use DateTimeZone;
use Phil\ContentBundle\Application\Content\SingleKinds;
use Phil\ContentBundle\Application\ContentManager;
use Phil\ContentBundle\Application\Contract\RawHtmlPermission;
use Phil\ContentBundle\Application\DataMapper\BodyDataMapper;
use Phil\ContentBundle\Application\DataMapper\ExcerptDataMapper;
use Phil\ContentBundle\Application\DataMapper\LinkDataMapper;
use Phil\ContentBundle\Application\DataMapper\RoutableDataMapper;
use Phil\ContentBundle\Application\DataMapper\SeoDataMapper;
use Phil\ContentBundle\Application\DataMapper\TemplateDataMapper;
use Phil\ContentBundle\Application\Editing\AuthorRawHtml;
use Phil\ContentBundle\Application\Editing\ContentEditor;
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
use Phil\ContentBundle\Application\Pipeline\Merger;
use Phil\ContentBundle\Application\Publication\ContentPublisher;
use Phil\ContentBundle\Application\Publication\ContentVersions;
use Phil\ContentBundle\Application\Publication\ContentWorkflow;
use Phil\ContentBundle\Application\Publication\DimensionContentCopier;
use Phil\ContentBundle\Application\Publication\LiveContentReader;
use Phil\ContentBundle\Application\Publication\PageEvents;
use Phil\ContentBundle\Application\Publication\PublishedContents;
use Phil\ContentBundle\Application\Routing\RouteResolver;
use Phil\ContentBundle\Application\Routing\RouteUpdater;
use Phil\ContentBundle\Domain\Contract\WorkingCopyRepository;
use Phil\ContentBundle\Infrastructure\Markup\UjoBodyConverter;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\HtmlPurifier;
use Phil\Markup\MarkupCompiler;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * The whole application layer of the bundle over in-memory ports, wired as the bundle wires it.
 */
final readonly class InMemoryContents
{
    public InMemoryDimensionContentRepository $rows;

    public InMemoryRouteRepository $routes;

    public InMemoryContentRootRepository $roots;

    public InMemoryContentTransaction $transaction;

    public MockClock $clock;

    public DeclaredLocales $locales;

    public ContentWorkflow $workflow;

    public ContentVersions $versions;

    public ContentPublisher $publisher;

    public ContentEditor $editor;

    public LiveContentReader $reader;

    public RouteResolver $resolver;

    public ContentManager $manager;

    public ContentAggregator $aggregator;

    public InMemoryPublishedRows $publishedRows;

    public PublishedContents $published;

    public EventDispatcher $dispatcher;

    public PageEvents $pages;

    public ContentNormalizer $normalizer;

    /**
     * The permission of the author at work, wrapping the one given; `null` without one.
     */
    public ?AuthorRawHtml $rawHtml;

    /**
     * The body is compiled by `EchoMarkupCompiler` and purified by `MarkingHtmlPurifier` unless a test gives its own,
     * and published into UJO by `UjoBodyConverter` over `phil/markup`'s compiler.
     *
     * @param array<string, list<string>> $locales
     * @param list<string>                $singles the single kinds
     */
    public function __construct(
        array $locales = ['fr_CA' => ['fr'], 'fr' => [], 'en' => []],
        MarkupCompiler $compiler = new EchoMarkupCompiler(),
        HtmlPurifier $purifier = new MarkingHtmlPurifier(),
        array $singles = [],
        ?RawHtmlPermission $rawHtml = null,
    ) {
        $this->rows = new InMemoryDimensionContentRepository();
        $this->routes = new InMemoryRouteRepository();
        $this->roots = new InMemoryContentRootRepository();
        $this->transaction = new InMemoryContentTransaction($this->rows, $this->roots);
        $this->clock = new MockClock(new DateTimeImmutable('2026-10-31 12:00:00', new DateTimeZone('America/Montreal')));
        $this->locales = DeclaredLocales::fromArray($locales);
        $this->workflow = ContentWorkflow::create();

        $mergers = self::mergers();
        $copier = new DimensionContentCopier($mergers);
        $aggregator = new ContentAggregator($this->rows, $mergers);
        $this->aggregator = $aggregator;
        $routeUpdater = new RouteUpdater($this->routes, $this->roots);
        $this->normalizer = new ContentNormalizer([
            new TemplateNormalizer(),
            new WorkflowNormalizer(),
            new SeoNormalizer(),
            new ExcerptNormalizer(),
            new LinkNormalizer(),
            new RoutableNormalizer(),
            new BodyNormalizer($purifier),
        ]);
        $normalizer = $this->normalizer;

        $this->versions = new ContentVersions($this->rows, $copier, $this->transaction);
        $this->rawHtml = $rawHtml instanceof RawHtmlPermission ? new AuthorRawHtml($rawHtml) : null;
        $converter = new UjoBodyConverter($compiler instanceof DefaultCompiler ? $compiler : new DefaultCompiler(), $purifier, rawHtml: $rawHtml instanceof RawHtmlPermission);
        $this->publisher = new ContentPublisher($this->rows, $this->roots, $this->versions, $copier, $this->workflow, $routeUpdater, $this->transaction, $normalizer, $converter, $this->clock, $converter);
        $this->dispatcher = new EventDispatcher();
        $this->pages = new PageEvents($this->rows, $aggregator, new InMemoryCanonicalAddresses($this->routes), $this->dispatcher);
        $this->editor = new ContentEditor($this->rows, $this->versions, $this->workflow, new ContentDataMapper([
            new TemplateDataMapper(),
            new SeoDataMapper(),
            new ExcerptDataMapper(),
            new LinkDataMapper(),
            new RoutableDataMapper(),
            new BodyDataMapper($compiler, $purifier, $this->rawHtml),
        ]), $routeUpdater);
        $this->reader = new LiveContentReader($this->rows, $aggregator);
        $this->publishedRows = new InMemoryPublishedRows($this->rows, $this->roots);
        $this->published = new PublishedContents($this->publishedRows, $this->roots, $this->rows, $aggregator);
        $this->resolver = new RouteResolver($this->routes);
        $this->manager = new ContentManager(
            $this->roots,
            $this->roots,
            $this->rows,
            $this->editor,
            $this->publisher,
            $this->versions,
            $this->reader,
            $aggregator,
            $normalizer,
            $this->locales,
            $this->transaction,
            $this->routes,
            $this->pages,
            SingleKinds::fromArray($singles),
        );
    }

    /**
     * The manager of the same ports with its optional collaborators given or left out: every one is `null` unless given.
     */
    public function managerWith(?PageEvents $pages = null, ?SingleKinds $singles = null, ?WorkingCopyRepository $workingCopies = null): ContentManager
    {
        return new ContentManager(
            $this->roots,
            $this->roots,
            $this->rows,
            $this->editor,
            $this->publisher,
            $this->versions,
            $this->reader,
            $this->aggregator,
            $this->normalizer,
            $this->locales,
            $this->transaction,
            $this->routes,
            $pages,
            $singles,
            $workingCopies,
        );
    }

    /**
     * @return list<Merger>
     */
    public static function mergers(): array
    {
        return [new TemplateMerger(), new WorkflowMerger(), new SeoMerger(), new ExcerptMerger(), new LinkMerger(), new RoutableMerger(), new BodyMerger()];
    }
}
