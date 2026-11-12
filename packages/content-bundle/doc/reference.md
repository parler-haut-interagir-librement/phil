# API reference

Every class and interface of the bundle, by layer. All namespaces are under `Phil\ContentBundle`. "Port" is an
interface the application may implement or replace; "implemented" is a class the bundle provides. Every entry
here is implemented and tested; what is not started is listed at the end.

## Bundle class

| Class | Role |
|---|---|
| `PhilContentBundle` | The `AbstractBundle`: configuration tree under `phil_content`, the tag constants (`DATA_MAPPER_TAG`, `MERGER_TAG`, `NORMALIZER_TAG`, `CAPABILITY_MAPPING_TAG`), the Doctrine configuration prepended with DoctrineBundle, and the services ([configuration.md](configuration.md)) |

## Domain

### `Domain\Content`

| Class | Kind | Role |
|---|---|---|
| `ContentRoot` | port | A root: `createDimensionContent(DimensionKey)` |
| `DimensionContent` | port | A row: `root()`, `dimension()` |
| `AbstractContentRoot` | implemented, mapped superclass | Base of the application's root entities: `dimensionContentClass()`, `resourceKey()` |
| `AbstractDimensionContent` | implemented, mapped superclass | Base of the application's row entities: identifier and dimension columns, final constructor |
| `DimensionContentId` | implemented | UUIDv7 surrogate identifier of a row (`Identifier` of `phil/ddd-bundle`) |

### `Domain\Dimension`

| Class | Kind | Role |
|---|---|---|
| `DimensionKey` | implemented | Locale (or none), stage and version of a row |
| `Stage` | implemented, enum | `Draft`, `Live` |
| `DimensionLocales` | implemented | A locale parsed and its chain found, refused as `InvalidDimension` |

`Locale`, `Version`, `FallbackChain` and `DeclaredLocales` are the value objects of `phil/locale-dimensions`.

### `Domain\Capability`

| Class | Kind | Role |
|---|---|---|
| `TemplateCapable`, `TemplateTrait` | port and trait | Template key and field values |
| `WorkflowCapable`, `WorkflowTrait` | port and trait | Workflow place and last publication date |
| `SeoCapable`, `SeoTrait` | port and trait | SEO title, description, keywords, canonical URL, three directives |
| `ExcerptCapable`, `ExcerptTrait` | port and trait | Teaser title, description, image reference |
| `LinkCapable`, `LinkTrait` | port and trait | A `Link` |
| `RoutableCapable`, `RoutableTrait` | port and trait | A `Slug` |
| `BodyCapable`, `BodyTrait` | port and trait | A `Body` |
| `CapabilityText` | implemented | The checks of free-text values (`text()`, `line()`, `reference()`) |

### `Domain\ValueObject`

| Class | Role |
|---|---|
| `Body` | Source, `MarkupFormat` and compiled HTML of a main text |
| `ContentReference` | `resource_key:identifier`, a root named from outside the storage |
| `Link` | A content root (key, identifier, optional locale) or a `Url` of `phil/url` |
| `Slug` | Lowercase words joined by hyphens, at most 255 characters |

### `Domain\Routing`

| Class | Role |
|---|---|
| `Route` | Entity of `phil_content_route`: one address, current or history |
| `RouteId` | UUIDv7 surrogate identifier of a route |

### `Domain\Draft`

| Class | Role |
|---|---|
| `WorkingCopy` | Entity of `phil_content_working_copy`: an author's autosave or a named draft of a content in a locale |
| `WorkingCopyId` | UUIDv7 identifier of a working copy |

### `Domain\Contract`

| Interface | Kind | Role |
|---|---|---|
| `WorkingCopyRepository` | port | Autosaves by author, named drafts by name, every working copy of a content |

### `Domain\Repository`

| Interface | Kind | Role |
|---|---|---|
| `DimensionContentRepository` | port | Rows by dimension, by stage and version, the versions of a locale, latest versions |
| `RouteRepository` | port | Routes by slug and by content, the current routes of many contents |
| `ContentRootRepository` | port | Roots by reference, references of roots |
| `PublishedRowRepository` | port | A page of the published rows of a kind in a locale |
| `PublishedRowQuery`, `PublishedOrder`, `PublishedRow` | value | What `PublishedRowRepository` reads, in which order, and each row found with its shared row |

### `Domain\Exception`

Every class implements `Phil\DddBundle\Domain\Exception\DomainError`.

| Class | Extends | When |
|---|---|---|
| `InvalidDimension` | `InvalidArgumentException` | A malformed locale or one not served, the `InvalidLocaleDimension` of `phil/locale-dimensions` kept as previous; a row merged over one that is not its counterpart |
| `InvalidContent` | `InvalidArgumentException` | A capability value, reference, slug, URL or link refused |
| `ContentNotFound` | `RuntimeException` | No row, root, live content, autosave or named draft where one is needed |
| `SlugTaken` | `RuntimeException` | The slug is another root's current address |
| `TransitionRefused` | `RuntimeException` | The workflow does not allow the operation |

## Application

| Class | Kind | Role |
|---|---|---|
| `ContentManager` | implemented | The PHP interface: create, edit, publish, unpublish, remove a draft, delete, read, list, status |
| `ContentStatus` | implemented | Where one locale of a root stands |
| `Content\SingleKinds` | implemented | The kinds that have a single content |
| `Contract\CanonicalAddresses` | port | The absolute address of a content in a locale |
| `Contract\PublicationSchedule` | port | Publishing or taking offline at a set moment |
| `Exception\PublicationLosesContent` | exception | A publication refused for what its body would lose in UJO, with the losses |
| `Editing\WorkingCopies` | implemented | The private autosave of each author and the named drafts of a content |
| `Editing\AuthorRawHtml` | implemented | The raw HTML permission of the author of the revision being written: the user at work, or the author of the working copy being applied |
| `Publication\PageEvents` | implemented | The `PagePublished` and `PageRetired` events of `phil/publishing`, read from the live row |
| `Publication\ContentArchive`, `Publication\ArchivedVersion` | implemented, value | The dated archive of a kind, and one publication in it |
| `Publication\ScheduledPublication` | value | A transition planned for a moment |
| `Content\ContentRootFactory` | port | Builds a new root of a kind |
| `Persistence\ContentTransaction` | port | The unit of work of an operation |
| `Pipeline\DataMapper`, `Pipeline\Merger`, `Pipeline\Normalizer` | ports | The three pipeline stages |
| `Pipeline\ContentDataMapper` | implemented | Runs every data mapper |
| `Pipeline\ContentAggregator` | implemented | Merges a localized row over its shared row, along a chain |
| `Pipeline\ContentNormalizer` | implemented | Runs every normalizer, refuses a repeated key |
| `DataMapper\TemplateDataMapper`, `SeoDataMapper`, `ExcerptDataMapper`, `LinkDataMapper`, `RoutableDataMapper`, `BodyDataMapper` | implemented | One data mapper per capability (none for the workflow) |
| `DataMapper\SubmittedData` | implemented | Typed readers of submitted data |
| `Contract\RawHtmlPermission` | port | Whether the user at work holds the trusted role: the bodies they write record it, show their raw HTML and keep it when published, by anyone |
| `Merger\TemplateMerger`, `WorkflowMerger`, `SeoMerger`, `ExcerptMerger`, `LinkMerger`, `RoutableMerger`, `BodyMerger` | implemented | One merger per capability |
| `Normalizer\TemplateNormalizer`, `WorkflowNormalizer`, `SeoNormalizer`, `ExcerptNormalizer`, `LinkNormalizer`, `RoutableNormalizer`, `BodyNormalizer` | implemented | One normalizer per capability |
| `Editing\ContentEditor` | implemented | Writes submitted data onto the draft of a locale, forking and creating rows |
| `Publication\ContentWorkflow` | implemented | The `symfony/workflow` state machine |
| `Publication\ContentPublisher` | implemented | Publish, unpublish, remove a draft; a published body in UJO |
| `Publication\BodyConverter` | port | The body of a draft converted into the UJO a published row stores |
| `Contract\ConversionLosses`, `Publication\ConversionLoss` | port, value | What the conversion of a body into UJO does not keep |
| `Publication\ContentVersions` | implemented | Latest draft version, frozen versions, forks |
| `Publication\DimensionContentCopier` | implemented | Copies a row to another dimension through the mergers |
| `Publication\LiveContentReader` | implemented | What a reader is served along a chain |
| `Routing\RouteUpdater` | implemented | Slug availability and current routes |
| `Routing\RouteResolver` | implemented | A slug to a route to serve or redirect to |
| `Routing\RouteMatch` | implemented | The result of a resolution |
| `Rendering\TemplateResolver` | implemented | Template key to Twig template |

## Infrastructure

### `Infrastructure\Doctrine`

| Class | Role |
|---|---|
| `DoctrineDimensionContentRepository` | Adapter of `DimensionContentRepository` |
| `DoctrineRouteRepository` | Adapter of `RouteRepository` |
| `DoctrineContentRootRepository` | Adapter of `ContentRootRepository` |
| `DoctrinePublishedRowRepository` | Adapter of `PublishedRowRepository` |
| `RootIdentifiers` | A root's identifier from its string, and bound in an `IN` list as its DBAL type stores it |
| `DoctrineContentTransaction` | Adapter of `ContentTransaction` |
| `ConstructorContentRootFactory` | Adapter of `ContentRootFactory`, constructor without arguments |
| `ContentRoots` | The declared root classes, their row classes and resource keys |
| `DimensionContentMappingListener` | `loadClassMetadata`: root association, unique dimension, capability columns |
| `InvalidContentMapping` | `LogicException` for content classes that do not fit together |
| `Mapping\CapabilityMapping` | Abstract base of the columns of one capability (extension point) |
| `Mapping\TemplateCapabilityMapping`, `WorkflowCapabilityMapping`, `SeoCapabilityMapping`, `ExcerptCapabilityMapping`, `LinkCapabilityMapping`, `RoutableCapabilityMapping`, `BodyCapabilityMapping` | The columns of each capability |
| `DoctrineWorkingCopyRepository` | Adapter of `WorkingCopyRepository` |
| `Type\LocaleType`, `StageType`, `VersionType`, `DimensionContentIdType`, `RouteIdType`, `WorkingCopyIdType` | The DBAL types |

### `Infrastructure\Http`

| Class | Role |
|---|---|
| `ContentController` | Serves live content by slug: 200, 301, 404 |
| `ContentUrlGenerator` | The URL of a root's current route |
| `ResourceRoutes` | Which Symfony routes serve each kind |
| `ContentCanonicalAddresses` | Adapter of `CanonicalAddresses` over the router |
| `ArchiveController` | Serves one dated version of a content of an archived kind |
| `ContentAdminController` | The administration: list, creation, editing page, autosave |
| `FeedController`, `PreviewController`, `PreviewLinks` | Atom feeds, previews of drafts and their signed links |

### `Infrastructure\EasyAdmin`

| Class | Role |
|---|---|
| `ContentAdmin` | The menu item of an EasyAdmin dashboard that links to the administration |

### `Infrastructure\Scheduling`

| Class | Role |
|---|---|
| `ScheduledTransitionsPublicationSchedule` | Adapter of `PublicationSchedule` over `phil/scheduled-transitions-bundle` |
| `PublicationReferences` | A publication named `news:42@fr` for that bundle, and read back |
| `PublicationSubjects` | The resolver that bundle's workflow handler finds the publications by |

### `Infrastructure\Search`

| Class | Role |
|---|---|
| `ContentDocumentProvider` | The `DocumentProvider` of `phil/search-bundle` that feeds it the live rows |

### `Infrastructure\Markup`

| Class | Role |
|---|---|
| `Markup\MarkupServices` | Registers the default adapters of `phil/markup` and the bundle's converter, and aliases the three ports to them |
| `Markup\UjoBodyConverter` | Adapter of `BodyConverter` and `ConversionLosses`: `phil/markup`'s readers, `phil/ujo`'s encoder, compiled and purified |
| `Markup\RawHtmlRole` | Adapter of `RawHtmlPermission`: the trusted role of `raw_html_role`, checked by Symfony's authorization checker |
| `Markup\RawHtmlAuthorPass` | Compiler pass: with a `RawHtmlPermission`, wraps it in `AuthorRawHtml` for `BodyDataMapper` and `WorkingCopies`, and tells `UjoBodyConverter` that raw HTML is configured |

### `Infrastructure\Twig`

| Class | Role |
|---|---|
| `ContentExtension` | Declares the seven Twig functions |
| `ContentRuntime` | Implements them, built lazily |

### `Infrastructure\Console`

| Class | Role |
|---|---|
| `ContentCommand` | Abstract base: data options, `DomainError` as an error message |
| `CreateContentCommand`, `EditContentCommand`, `PublishContentCommand`, `UnpublishContentCommand`, `ListContentCommand` | `phil:content:create`, `edit`, `publish`, `unpublish`, `list` |
| `SubmittedFields` | Reads `--file`, `--field` and `--json` |

`ContentManager::removeDraft()` and `ContentManager::delete()` have no console command; they are called from PHP or,
for the first, from the administration.
