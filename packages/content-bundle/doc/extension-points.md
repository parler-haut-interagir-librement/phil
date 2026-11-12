# Extension points

What an application implements, replaces or relies on. Every port has a Doctrine adapter registered when the
kernel has DoctrineBundle; without it, the application provides the six persistence ports itself, by aliasing
each interface to its own service.

## Persistence ports

### `DimensionContentRepository`

`Phil\ContentBundle\Domain\Repository\DimensionContentRepository` — loads and stores the rows of a root. Doctrine
adapter: `DoctrineDimensionContentRepository`.

| Method | Contract |
|---|---|
| `find(ContentRoot $root, DimensionKey $dimension): ?DimensionContent` | the row of `$root` whose dimension equals `$dimension`, or `null`. An unlocalized key finds only the unlocalized row: no locale stands in for it |
| `findAll(ContentRoot $root, Stage $stage, Version $version): list<DimensionContent>` | every row of the root in that stage and version: **the unlocalized row first** when there is one, then the localized rows ordered by locale |
| `latestVersion(ContentRoot $root, Stage $stage): ?Version` | the highest version that has at least one row of the root in the stage, localized or not; `null` when none |
| `latestLocalizedVersion(ContentRoot $root, Stage $stage, Locale $locale): ?Version` | the highest version that has a row of the locale in the stage; `null` when none |
| `findVersions(ContentRoot $root, Stage $stage, Locale $locale): list<DimensionContent>` | every row of the locale in the stage, one per version, the latest version first: a locale's history in one lookup |
| `add(DimensionContent $row): void` | stores a new row, written when the transaction flushes |
| `remove(DimensionContent $row): void` | deletes a stored row when the transaction flushes |

Invariants an adapter keeps:

- **one row per `(root, stage, version, locale or none)`**; a second row of the same dimension is refused by the
  store (a unique constraint), never silently kept;
- a row removed and a row of the same dimension added are written in that order when `ContentTransaction::flush()`
  is called between them;
- `find()` and `findAll()` see the rows added and removed in the running transaction once it has flushed.

### `RouteRepository`

`Phil\ContentBundle\Domain\Repository\RouteRepository` — Doctrine adapter: `DoctrineRouteRepository`.

| Method | Contract |
|---|---|
| `findBySlug(string $resourceKey, Locale $locale, Slug $slug): ?Route` | the route, current or history, that holds the slug for the kind in the locale |
| `findCurrent(ContentReference $content, Locale $locale): ?Route` | the current route of the root in the locale; never a history route |
| `findCurrentAmong(string $resourceKey, Locale $locale, list<string> $resourceIds): list<Route>` | the current routes in the locale of the contents of one kind named by their identifiers, in one lookup; a content without one is missing, in no order |
| `findAllOf(ContentReference $content): list<Route>` | every route of the root, current and history, in every locale |
| `add(Route $route): void`, `remove(Route $route): void` | store and delete, written when the transaction flushes |

Invariant: **at most one route per `(resource key, locale, slug)`**, and at most one current route per root and
locale (the second is kept by `RouteUpdater`, which retires the previous current route).

### `ContentRootRepository`

`Phil\ContentBundle\Domain\Repository\ContentRootRepository` — Doctrine adapter: `DoctrineContentRootRepository`.

| Method | Contract |
|---|---|
| `find(ContentReference $reference): ?ContentRoot` | the root the reference names, or `null` when none exists or the identifier cannot be one of its kind |
| `findAll(string $resourceKey): list<ContentRoot>` | every root of the kind |
| `referenceOf(ContentRoot $root): ContentReference` | how the root is named outside the storage; throws `InvalidContent` when its identifier has no string form (or is not set yet) |
| `add(ContentRoot $root): void` | stores a new root |
| `remove(ContentRoot $root): void` | deletes it when the transaction flushes; its rows and routes are removed first by the caller (`ContentManager::delete()`) |

Invariant: `find($this->referenceOf($root))` returns `$root`.

### `PublishedRowRepository`

`Phil\ContentBundle\Domain\Repository\PublishedRowRepository` — reads the published rows of a kind in a few
queries, whatever the number of roots: what the sitemap, the feeds and an application's lists read. Doctrine adapter:
`DoctrinePublishedRowRepository`.

| Method | Contract |
|---|---|
| `findPublished(PublishedRowQuery $query): list<PublishedRow>` | for each root of the kind with a live row of the query's locale, that row in the highest version the locale was published from, paired with the live shared row of the same version (or `null`); filtered, ordered and cut as the query says |

A `PublishedRowQuery` names the resource key and the locale, then:

| Argument | Default | Meaning |
|---|---|---|
| `order` | `PublishedOrder::Root` | `Root`, by the root's identifier; `LatestPublished`, the most recently published first and those never published last; `Slug`, by the localized row's slug. Every order ends on the root's identifier, so pages follow each other without overlap; a row class without the capability an order reads is ordered by root |
| `rootCriteria` | `[]` | fields of the root and the values they equal, `['genre' => 'podcast']`; a field the root does not map is an `InvalidContentMapping` |
| `resourceIds` | `null` | the roots kept, by identifier; `null` keeps all, `[]` none; an identifier its type refuses matches nothing |
| `withSlug` | `false` | keeps the rows with a slug, the only ones a route serves; none when the row class is not `RoutableCapable` |
| `offset`, `limit` | `0`, `null` | the page cut out of the order; a negative offset or a limit below one is an `InvalidContent` |

The Doctrine adapter reads a page in two queries: the localized rows with their roots (the highest version by a
correlated `MAX` over the `(root_id, stage)` prefix of the unique index), then the shared rows of those roots in one
`IN` list, the roots' identifiers bound as their own DBAL type stores them.

### `ContentTransaction`

`Phil\ContentBundle\Application\Persistence\ContentTransaction` — Doctrine adapter: `DoctrineContentTransaction`.

| Method | Contract |
|---|---|
| `run(callable $operation): mixed` | runs the operation in a transaction, flushes and commits, and returns its result; rolls back and rethrows on failure. Nested in a transaction already open, it nests (a savepoint) |
| `flush(): void` | writes what is pending now, inside the running transaction |

Invariant: **all of an operation or nothing is kept**.

### `ContentRootFactory`

`Phil\ContentBundle\Application\Content\ContentRootFactory` — Doctrine adapter: `ConstructorContentRootFactory`,
which calls the root's constructor without arguments.

| Method | Contract |
|---|---|
| `create(string $resourceKey): ContentRoot` | a new, unsaved root of the kind; `ContentManager::create()` adds it to the repository |

An application whose roots take constructor arguments aliases this interface to its own factory:

```php
namespace App\Content;

use Phil\ContentBundle\Application\Content\ContentRootFactory;
use Phil\ContentBundle\Domain\Content\ContentRoot;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ContentRootFactory::class)]
final readonly class SiteContentRootFactory implements ContentRootFactory
{
    public function __construct(private CurrentSite $site)
    {
    }

    public function create(string $resourceKey): ContentRoot
    {
        return match ($resourceKey) {
            'news' => new News($this->site->id()),
            'static_page' => new StaticPage($this->site->id()),
        };
    }
}
```

### An adapter without Doctrine

Without DoctrineBundle, alias the six ports in `config/services.yaml`:

```yaml
services:
    Phil\ContentBundle\Domain\Repository\DimensionContentRepository: '@App\Content\Storage\Rows'
    Phil\ContentBundle\Domain\Repository\RouteRepository: '@App\Content\Storage\Routes'
    Phil\ContentBundle\Domain\Repository\ContentRootRepository: '@App\Content\Storage\Roots'
    Phil\ContentBundle\Domain\Repository\PublishedRowRepository: '@App\Content\Storage\PublishedRows'
    Phil\ContentBundle\Application\Persistence\ContentTransaction: '@App\Content\Storage\Transaction'
    Phil\ContentBundle\Application\Content\ContentRootFactory: '@App\Content\Storage\Factory'
```

The pipeline, the publication services and `ContentManager` then work over them. The controller, the Twig
functions and the console commands are only registered with DoctrineBundle.

## Content classes

### `ContentRoot` and `DimensionContent`

`Domain\Content\ContentRoot` is all the pipeline needs of a root: `createDimensionContent(DimensionKey): DimensionContent`,
a new, empty row of the application's own row class attached to the root, used as the target of merges and
copies so that the result carries the same capabilities. `Domain\Content\DimensionContent` is a row:
`root(): ContentRoot` and `dimension(): DimensionKey`.

With the Doctrine adapters, roots extend `AbstractContentRoot` and rows extend `AbstractDimensionContent`
([storage.md](storage.md)); with adapters of the application, any class implementing the two interfaces works.

## Pipeline stages

`DataMapper`, `Merger` and `Normalizer` (`Phil\ContentBundle\Application\Pipeline`) are tagged by
autoconfiguration; their contracts are in [pipeline.md](pipeline.md). Rules an application stage keeps:

- a **data mapper** handles one capability, ignores rows that lack it, honours "absent key changes nothing,
  `null` clears", and writes keys no other mapper writes;
- a **merger** copies only what its source sets, and never reads anything but `$source` and `$target`; it runs
  for aggregation **and** for copies (publication, forks), so a capability without a merger is neither read nor
  published;
- a **normalizer** returns an empty array for a row without its capability, and keys no other normalizer
  returns (`ContentNormalizer` refuses a repeated key with a `LogicException`).

### An application capability

```php
namespace App\Content\Capability;

interface EventDatesCapable
{
    public function getStartsAt(): ?\DateTimeImmutable;
    public function setStartsAt(?\DateTimeImmutable $startsAt): void;
}

trait EventDatesTrait
{
    private ?\DateTimeImmutable $startsAt = null;

    public function getStartsAt(): ?\DateTimeImmutable { return $this->startsAt; }
    public function setStartsAt(?\DateTimeImmutable $startsAt): void { $this->startsAt = $startsAt; }
}
```

```php
use Phil\ContentBundle\Application\DataMapper\SubmittedData;
use Phil\ContentBundle\Application\Pipeline\{DataMapper, Merger, Normalizer};
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\CapabilityMapping;

final class EventDatesCapabilityMapping extends CapabilityMapping
{
    public function capability(): string { return EventDatesCapable::class; }

    protected function fields(): array
    {
        return [['fieldName' => 'startsAt', 'type' => 'datetime_immutable', 'columnName' => 'starts_at', 'nullable' => true]];
    }
}

final readonly class EventDatesDataMapper implements DataMapper
{
    public function map(DimensionContent $unlocalized, DimensionContent $localized, array $data): void
    {
        if ($unlocalized instanceof EventDatesCapable && SubmittedData::has($data, 'startsAt')) {
            $value = SubmittedData::string($data, 'startsAt');
            $unlocalized->setStartsAt(null === $value ? null : new \DateTimeImmutable($value));   // shared by every locale
        }
    }
}

final readonly class EventDatesMerger implements Merger
{
    public function merge(DimensionContent $target, DimensionContent $source): void
    {
        if ($target instanceof EventDatesCapable && $source instanceof EventDatesCapable && null !== $source->getStartsAt()) {
            $target->setStartsAt($source->getStartsAt());
        }
    }
}

final readonly class EventDatesNormalizer implements Normalizer
{
    public function normalize(DimensionContent $content): array
    {
        return $content instanceof EventDatesCapable
            ? ['startsAt' => $content->getStartsAt()?->format(\DATE_RFC3339)]
            : [];
    }
}
```

With autoconfiguration on, the four services join the bundle's. A field listed by a `CapabilityMapping` is named
after the trait's property; a backed enum property names its class under `enumType`. A field the row class maps
itself is left alone.

## Markup ports

### `MarkupCompiler` and `HtmlPurifier`

| Port | Method | Contract | Default adapter |
|---|---|---|---|
| `Phil\Markup\MarkupCompiler` | `compile(string $source, MarkupFormat $format): string` | the source compiled to HTML. **The result is not safe to show**: a compiler only translates | `DefaultCompiler` |
| `Phil\Markup\HtmlPurifier` | `purify(string $html, Level $level): string` | removes everything that could run in a reader's browser or reach outside the page: scripts, event handlers, `javascript:` URLs, frames, forms, styles. Applied at `Level::All` before storing and again on every read | `SanitizerPurifier` (`defaultPolicies()`) |

Replace either by aliasing the interface to your own service:

```php
namespace App\Content;

use Phil\Markup\HtmlPurifier;
use Phil\Markup\Level;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

#[AsAlias(HtmlPurifier::class)]
final readonly class StrictPurifier implements HtmlPurifier
{
    public function __construct(private HtmlSanitizerInterface $contentSanitizer)   // a sanitizer of framework.html_sanitizer
    {
    }

    public function purify(string $html, Level $level): string
    {
        return $this->contentSanitizer->sanitize($html);
    }
}
```

Keep a purifier idempotent — HTML it already purified comes back unchanged — since every body is purified on
write and again on read.

### `BodyConverter`

| Port | Method | Contract | Default adapter |
|---|---|---|---|
| `Phil\ContentBundle\Application\Publication\BodyConverter` | `toUjo(Body $body, DateTimeImmutable $publishedAt): Body` | the body a published row stores: the source converted into UJO, its `time` the moment `$publishedAt` in milliseconds, the format `ujo`, the HTML compiled from that UJO and purified; `InvalidContent` when the source cannot be read | `UjoBodyConverter` |

An application that reads more formats, or another level, aliases the port to its own service with
`#[AsAlias(BodyConverter::class)]`. The body it returns is stored as it is: purify its HTML.

### `ConversionLosses`

| Port | Method | Contract | Default adapter |
|---|---|---|---|
| `Phil\ContentBundle\Application\Contract\ConversionLosses` | `of(string $source, MarkupFormat\|string $format): list<ConversionLoss>` | what the conversion into UJO does not keep, in the order of the source — each `ConversionLoss` an element, its path and the reason `phil/markup` names —, read as `toUjo()` reads it; `InvalidContent` when the source cannot be read | `UjoBodyConverter` |

`ContentPublisher` refuses a publication whose body loses content, and `phil:content:publish` and the administration
show the losses before publishing ([publication.md](publication.md#a-publication-that-loses-content-is-refused)).
Without an implementation, nothing is refused for its losses.

### `CanonicalAddresses`

| Port | Method | Contract | Default adapter |
|---|---|---|---|
| `Phil\ContentBundle\Application\Contract\CanonicalAddresses` | `of(ContentReference $content, Locale $locale): ?Url` | the absolute address of the content in the locale, a `Phil\Url\Url`, or `null` when it has none | `ContentCanonicalAddresses`, with TwigBundle: the router's absolute URL of the current route, `localhost` accepted in debug mode only |

The page events carry it ([publication.md](publication.md#page-events)). An application whose pages live on several
hosts — a site per tenant — aliases the port to its own service with `#[AsAlias(CanonicalAddresses::class)]`.

### `PublicationSchedule`

| Port | Methods | Default adapter |
|---|---|---|
| `Phil\ContentBundle\Application\Contract\PublicationSchedule` | `schedule(Publication, string $transition, DateTimeImmutable $at)`, `cancel(Publication, ?string $transition = null): int`, `pending(Publication): list<ScheduledPublication>` | `ScheduledTransitionsPublicationSchedule`, with `phil/scheduled-transitions-bundle` |

An application with its own scheduler implements it, applying `publish` or `unpublish` through the
`phil_content_publication` workflow when due ([publication.md](publication.md#scheduled-publication)).

## Working copies

### `WorkingCopyRepository`

| Port | Methods | Default adapter |
|---|---|---|
| `Phil\ContentBundle\Domain\Contract\WorkingCopyRepository` | `findAutosave(ContentReference, Locale, string $author)`, `findNamed(ContentReference, Locale, string $name)`, `findAllNamed(ContentReference, Locale)` by name, `findAllOf(ContentReference)`, `add()`, `remove()` | `DoctrineWorkingCopyRepository`, with DoctrineBundle |

It writes nothing itself: `WorkingCopies` and `ContentManager::delete()` run it inside a `ContentTransaction`.

## Search

`ContentDocumentProvider` is a `DocumentProvider` of `phil/search-bundle`, registered with that bundle. An application
that wants other fields decorates it, or writes its own provider for the same live rows and leaves `search_index` to an
index of its own ([search.md](search.md)).

## Page events

`ContentManager` dispatches `Phil\Publishing\PagePublished` and `Phil\Publishing\PageRetired` of `phil/publishing`
on the container's `event_dispatcher` ([publication.md](publication.md#page-events)). A listener of either follows
what readers can see.

### `RawHtmlPermission`

| Port | Method | Contract | Default adapter |
|---|---|---|---|
| `Phil\ContentBundle\Application\Contract\RawHtmlPermission` | `isGranted(): bool` | whether the user at work writes raw HTML: `BodyDataMapper` then compiles their draft for `Level::All` and `UjoBodyConverter` publishes `raw` blocks | `RawHtmlRole`, with `raw_html_role` |

An application that decides by something else than a role — a permission of its own, a voter on the content —
aliases the port to its own service with `#[AsAlias(RawHtmlPermission::class)]`. It answers for the request at work,
and holds no state between two requests.

## Workflow events

When the container has an `event_dispatcher`, the workflow named `phil_content` dispatches the events of
`symfony/workflow` (`workflow.phil_content.guard.<transition>`, `workflow.phil_content.completed.<transition>`,
…) with the localized draft row as subject. A guard that blocks makes the operation throw `TransitionRefused`
([publication.md](publication.md#guarding-a-transition)).

## Service ids, tags and constants

| What | Value |
|---|---|
| Configuration alias | `phil_content` |
| Tag of data mappers | `phil_content.data_mapper` (`PhilContentBundle::DATA_MAPPER_TAG`) |
| Tag of mergers | `phil_content.merger` (`PhilContentBundle::MERGER_TAG`) |
| Tag of normalizers | `phil_content.normalizer` (`PhilContentBundle::NORMALIZER_TAG`) |
| Tag of capability mappings | `phil_content.capability_mapping` (`PhilContentBundle::CAPABILITY_MAPPING_TAG`), with DoctrineBundle |
| Workflow names | `phil_content` (`ContentWorkflow::NAME`), the draft row's; `phil_content_publication` (`PublicationWorkflow::NAME`), a content in one locale's, service `phil_content.publication_workflow`, added to `workflow.registry` when there is one |
| Services | every service under its class name ([configuration.md](configuration.md#services-and-conditions)); the ports `MarkupCompiler`, `HtmlPurifier`, `BodyConverter`, `ConversionLosses`, `CanonicalAddresses`, `PublicationSchedule`, `DimensionContentRepository`, `RouteRepository`, `ContentRootRepository`, `PublishedRowRepository`, `WorkingCopyRepository`, `ContentTransaction` and `ContentRootFactory` are aliases |
| Tags of other bundles | `phil_sitemap.url_provider` on `ContentUrlProvider`; `phil_scheduled_transitions.subject_resolver` on `PublicationSubjects`; `phil_search.provider` on `ContentDocumentProvider` — named by their strings, without depending on those bundles |
| Container services used | `clock`; when present, `event_dispatcher`; with TwigBundle, `twig`, `router`, `request_stack`; with the administration, `security.token_storage`, `security.authorization_checker`, `security.csrf.token_manager` |
| DBAL types | `phil_content_locale`, `phil_content_stage`, `phil_content_version`, `phil_content_dimension_content_id`, `phil_content_route_id`, `phil_content_working_copy_id` |
| Tables | `phil_content_route`, `phil_content_working_copy` |
| Doctrine listener | `DimensionContentMappingListener` on `loadClassMetadata` |
| Console commands | `phil:content:create`, `phil:content:edit`, `phil:content:publish`, `phil:content:unpublish`, `phil:content:list`, `phil:content:preview-link` |
| Twig functions | `content`, `content_single`, `content_archive`, `content_url`, `content_link_url`, `content_seo`, `content_schema` |
| Template variables | `content`, `root`, `reference`, `locale`, `resolved_locale`, `left_language`; on an archive page, `archived` and `archive` |
| Routes of the administration | `phil_content_admin_list`, `phil_content_admin_create`, `phil_content_admin_edit`, `phil_content_admin_autosave` (`config/routes_admin.php`) |
| Translation domain | `phil_content`, in English and French |
| Stimulus controller | `phil--content-bundle--autosave` |
| CSRF token id | `phil_content_admin` |

The bundle declares no PHP attribute; the events it dispatches are those of `phil/publishing`.
