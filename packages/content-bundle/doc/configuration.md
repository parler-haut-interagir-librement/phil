# Configuration reference

The bundle's configuration lives under `phil_content`. Every key is optional; an empty configuration builds a
container in which no locale is served, no root is declared and no route serves content.

## Complete example

```yaml
# config/packages/phil_content.yaml
phil_content:
    # The served locales, each with the locales tried after it, in order.
    # A fallback must itself be a served locale; a chain never repeats a locale.
    locales:
        fr_CA: [fr]          # a reader of fr_CA gets fr_CA, then fr
        fr: []               # a reader of fr gets fr only
        en: []

    # The content root classes; each names its row class.
    roots:
        - App\Content\News
        - App\Content\StaticPage

    # Service id of the entity manager the Doctrine adapters write through (the default).
    entity_manager: doctrine.orm.entity_manager

    # The Symfony routes that mount ContentController for each resource key.
    # The first route of a kind is the one its URLs are generated on, and takes a {slug} parameter.
    routes:
        news: news_show
        static_page: [page_show, privacy]

    # The Twig template of each template key.
    templates:
        news: content/news.html.twig

    # The template of a row whose template key is absent or not declared.
    default_template: content/page.html.twig

    # The trusted role whose holders write raw HTML; without it, raw HTML is shown and published as text.
    raw_html_role: ROLE_EDITOR

    # The kinds that have a single content: created once, found without an identifier.
    singles: [home_page, contact]

    # The kinds whose every publication stays readable, dated.
    archives: [now_page]

    # The index of phil/search-bundle the published contents are fed to (the default).
    search_index: content

    # The administration, linked from an EasyAdmin back office.
    admin:
        enabled: true
        role: ROLE_EDITOR
        layout: admin/content_layout.html.twig
        timezone: America/Toronto
```

## Keys

### `locales`

| | |
|---|---|
| Type | map of locale to list of locales |
| Default | `{}` (no locale served) |
| Wires | `Phil\LocaleDimensions\DeclaredLocales`, built by `DeclaredLocales::fromArray()` |

Each key is a served locale; its value is the list of locales tried after it, in order. The requested locale
is always tried first, so `fr_CA: [fr]` declares the chain `fr_CA → fr`.

Constraints, checked when the container is built (an `InvalidLocaleDimension` is thrown from the configuration's
validation):

- a locale has one spelling: two or three lowercase letters, optionally an underscore and two uppercase letters
  (`fr`, `fra`, `fr_CA`). The keys are **not** normalized, so `fr-CA` is refused instead of being read as
  `fr_CA`;
- every fallback must itself be a key of `locales`: a chain never ends on a locale the application does not
  serve;
- a chain never names a locale twice, its own locale included (`fr: [fr]` is refused).

Nothing derives a fallback: stripping the region or falling back to a default language is never done for you
([dimensions.md](dimensions.md)). A locale that is not a key here is refused by every writing method of
`ContentManager`, answers 404 in the controller, and makes the Twig functions answer `null`.

### `roots`

| | |
|---|---|
| Type | list of class names |
| Default | `[]` |
| Wires | `Phil\ContentBundle\Infrastructure\Doctrine\ContentRoots` (with DoctrineBundle), read by the mapping listener, `DoctrineContentRootRepository`, `DoctrinePublishedRowRepository`, `ConstructorContentRootFactory` and `phil:content:list` |

Each class must be a subclass of `Phil\ContentBundle\Domain\Content\AbstractContentRoot`. The list is validated
by `ContentRoots::fromClassNames()` whether or not DoctrineBundle is installed; an
`InvalidContentMapping` is thrown when:

- a class is not a subclass of `AbstractContentRoot`;
- two roots name the same row class (`dimensionContentClass()`);
- a root's resource key (`resourceKey()`) is not a lowercase letter followed by at most 63 lowercase letters,
  digits or underscores;
- two roots have the same resource key.

A row class that no declared root names is refused when Doctrine loads its metadata.

### `entity_manager`

| | |
|---|---|
| Type | string (a service id) |
| Default | `doctrine.orm.entity_manager` |
| Wires | the entity manager passed to `DoctrineDimensionContentRepository`, `DoctrineRouteRepository`, `DoctrineContentRootRepository`, `DoctrinePublishedRowRepository` and `DoctrineContentTransaction` |

Cannot be empty; a value that is not a string is refused with an `InvalidConfigurationException` when the
container is built with DoctrineBundle. Without DoctrineBundle the key is not used.

The ORM mappings of the bundle's base classes and of the route table are prepended to the `doctrine`
configuration in its single-manager form, which DoctrineBundle applies to the entity manager named `default`.
An application that points `entity_manager` at another manager maps the bundle's two directories on that manager
itself (`src/Domain/Content` under the prefix `Phil\ContentBundle\Domain\Content`, and `src/Domain/Routing` under
`Phil\ContentBundle\Domain\Routing`, both of type `attribute`).

### `routes`

| | |
|---|---|
| Type | map of resource key to a route name, or to a non-empty list of route names |
| Default | `{}` |
| Wires | `Phil\ContentBundle\Infrastructure\Http\ResourceRoutes`, read by `ContentController` and `ContentUrlGenerator` |

The routes are Symfony routes the application declares, whose controller is
`Phil\ContentBundle\Infrastructure\Http\ContentController`. A single string is read as a list of one. **The
first route of a kind is the one its URLs are generated on**, and it takes a `{slug}` parameter; the routes after
it serve the same kind at addresses of their own, usually with the slug as a route default
([routing.md](routing.md)).

Constraints, checked when the container is built:

- the keys are not normalized (`static_page` stays `static_page`);
- a kind lists at least one route, and no route name is empty;
- a route serves one kind only: a route listed under two keys, or twice, is refused with an
  `InvalidArgumentException`.

The keys are not checked against the declared roots: a key that no root declares fails at the first request
that matches its route.

### `templates`

| | |
|---|---|
| Type | map of template key to Twig template name |
| Default | `{}` |
| Wires | `Phil\ContentBundle\Application\Rendering\TemplateResolver` |

The template key is the row's `templateKey` (`TemplateCapable`). The keys are not normalized; a template name
cannot be empty.

### `default_template`

| | |
|---|---|
| Type | string or `null` |
| Default | `null` |
| Wires | `Phil\ContentBundle\Application\Rendering\TemplateResolver` |

The template of a row without a template key, of a row whose key is not under `templates`, and of a row class
that is not `TemplateCapable`. Without it, such a row makes `TemplateResolver::resolve()` throw a
`LogicException`: a key nobody declared is a mistake of the configuration, not of the request.

### `preview`

| | |
|---|---|
| Type | map: `secret` (string, not empty), `ttl` (integer, 60 or more) |
| Default | `secret: '%kernel.secret%'`, `ttl: 604800` (a week) |
| Wires | `Phil\ContentBundle\Infrastructure\Http\PreviewLinks` |

What preview links are signed with, and how many seconds they stay valid ([sitemap-feeds-previews.md](sitemap-feeds-previews.md)).

### `feed_limit`

| | |
|---|---|
| Type | integer, 1 to 500 |
| Default | `20` |
| Wires | `Phil\ContentBundle\Infrastructure\Http\FeedController` |

How many entries a feed holds.

### `raw_html_role`

| | |
|---|---|
| Type | string, not empty |
| Default | `null`: nobody writes raw HTML |
| Wires | `Phil\ContentBundle\Infrastructure\Markup\RawHtmlRole`, aliased as `Phil\ContentBundle\Application\Contract\RawHtmlPermission`, wrapped by `RawHtmlAuthorPass` in `AuthorRawHtml`: the `$rawHtml` argument of `BodyDataMapper` and `WorkingCopies`; `UjoBodyConverter` gets `$rawHtml: true` |
| Requires | SecurityBundle (`symfony/security-core` and its `security.authorization_checker` service) |

The trusted role whose holders write raw HTML. **The role is checked on the author of each revision of a body, never on
whoever publishes it**: the body records whether the editor who wrote its source held the role, and its raw HTML —
written in Markdown, BBCode, MediaWiki, DokuWiki, Creole or a format an add-on brings, or a `raw` block of UJO — is then
published as a `raw` block, kept as `phil/ujo`'s `raw` profile allows, by any editor; the draft's HTML, which its
preview shows, renders it the same way. The raw HTML of an author without the role is shown and published as text, even
by a publisher who holds it. A working copy keeps the role of the editor who saved it, whoever applies it
([capabilities.md](capabilities.md#bodycapable)).

### `singles`

| | |
|---|---|
| Type | list of resource keys |
| Default | `[]` |
| Wires | `Phil\ContentBundle\Application\Content\SingleKinds`, read by `ContentManager` and `ContentRuntime` |

The kinds that have a single content: the home page, the contact details, the « about » page. `ContentManager::create()`
refuses a second root of such a kind, `ContentManager::single()` finds its root, and the Twig function
`content_single()` reads it ([content-manager.md](content-manager.md), [rendering.md](rendering.md#twig-functions)). A
key that is not a resource key — a lower-case letter, then up to 63 lower-case letters, digits or `_` — is refused.

### `archives`

| | |
|---|---|
| Type | list of resource keys |
| Default | `[]` |
| Wires | `Phil\ContentBundle\Application\Publication\ContentArchive`, read by `ArchiveController` and `ContentRuntime` |

The kinds whose every publication stays readable at its version, with its date: a « now » page, a changelog, a
statement that changes over time ([publication.md](publication.md#the-dated-archive)). Any other kind keeps its history
to its editors.

### `search_index`

| | |
|---|---|
| Type | string, an index name: a lower-case letter, then up to 63 lower-case letters, digits or `_` |
| Default | `content` |
| Wires | `Phil\ContentBundle\Infrastructure\Search\ContentDocumentProvider` |
| Requires | `phil/search-bundle` in the kernel |

The index the published contents are fed to ([search.md](search.md)).

### `admin`

| Key | Type | Default | Holds |
|---|---|---|---|
| `enabled` | boolean | `false` | Whether the administration is registered |
| `role` | string | `ROLE_ADMIN` | The role every page of the administration asks |
| `layout` | string | `@PhilContent/admin/layout.html.twig` | The Twig layout the pages extend, with a `title` and a `content` block |
| `timezone` | string | `UTC` | The time zone of the moments the administration shows and schedules, an identifier PHP knows: `America/Toronto` |

| | |
|---|---|
| Wires | `Phil\ContentBundle\Infrastructure\Http\ContentAdminController` |
| Requires | DoctrineBundle, TwigBundle, SecurityBundle with the CSRF protection, `symfony/translation` for its labels |

The administration of the contents ([administration.md](administration.md)).

## Services and conditions

The services are registered under their class name and are private, except the controllers — `ContentController`,
`FeedController`, `PreviewController`, `ArchiveController` and `ContentAdminController` —, tagged
`controller.service_arguments`, and the workflow `phil_content.publication_workflow`, which `PublicationWorkflowPass`
adds to `workflow.registry`. Autowire
them by class.

| Service | Registered | Configured by |
|---|---|---|
| `DeclaredLocales` | always | `locales` |
| `ContentDataMapper`, `ContentAggregator`, `ContentNormalizer`, the bundle's data mappers, mergers and normalizers | always | the tags of [pipeline.md](pipeline.md) |
| `DefaultCompiler` (alias `MarkupCompiler`), `SanitizerPurifier` (alias `HtmlPurifier`), of `phil/markup`; `UjoBodyConverter` (alias `BodyConverter`) | always | — |
| `RawHtmlRole` (alias `RawHtmlPermission`) | with `raw_html_role` | `raw_html_role`, the container's `security.authorization_checker` |
| `AuthorRawHtml` | with a `RawHtmlPermission`, by `RawHtmlAuthorPass` | the service `RawHtmlPermission` names |
| `ContentWorkflow`, `ContentPublisher`, `ContentVersions`, `DimensionContentCopier`, `LiveContentReader`, `PublishedContents`, `RouteUpdater`, `RouteResolver`, `ContentEditor`, `ContentManager` | always, autowired over the ports | the container's `clock`, and its `event_dispatcher` when present |
| `PublicationMarkingStore`, `phil_content.publication_workflow` | always; added to `workflow.registry` when the container has it | the container's `event_dispatcher` when present |
| `TemplateResolver` | always | `templates`, `default_template` |
| `ResourceRoutes` | always | `routes` |
| `ContentRoots`, `DimensionContentMappingListener`, the capability mappings | with DoctrineBundle | `roots` |
| `DoctrineDimensionContentRepository`, `DoctrineRouteRepository`, `DoctrineContentRootRepository`, `DoctrinePublishedRowRepository`, `DoctrineContentTransaction`, `ConstructorContentRootFactory`, aliased to their ports | with DoctrineBundle | `entity_manager`, `roots` |
| the five `phil:content:*` commands | with DoctrineBundle | — |
| `ContentController`, `ContentUrlGenerator`, `ContentExtension`, `ContentRuntime` | with DoctrineBundle and TwigBundle | `routes`, `templates`, `default_template` |
| `ContentUrlProvider`, `FeedController`, `PreviewController`, `PreviewLinks`, `phil:content:preview-link` | with DoctrineBundle and TwigBundle | `routes`, `preview`, `feed_limit` |
| `SingleKinds` | always | `singles` |
| `ContentArchive` | always | `archives` |
| `PageEvents` | always; it builds events with an `event_dispatcher` and `CanonicalAddresses` | — |
| `ContentCanonicalAddresses` (alias `CanonicalAddresses`), `ArchiveController` | with DoctrineBundle and TwigBundle | `routes`, the kernel's debug mode |
| `DoctrineWorkingCopyRepository` (alias `WorkingCopyRepository`), `WorkingCopies` | with DoctrineBundle | `entity_manager`, the container's `clock` |
| `PublicationReferences`, `PublicationSubjects`, `ScheduledTransitionsPublicationSchedule` (alias `PublicationSchedule`) | with DoctrineBundle and `phil/scheduled-transitions-bundle` | `roots` |
| `ContentDocumentProvider` | with DoctrineBundle and `phil/search-bundle` | `search_index`, `roots`, `locales` |
| `ContentAdminController` | with DoctrineBundle, TwigBundle and `admin.enabled` | `admin`, `roots` |

With DoctrineBundle, the bundle also prepends to the `doctrine` configuration the DBAL types
`phil_content_dimension_content_id`, `phil_content_locale`, `phil_content_route_id`, `phil_content_stage`,
`phil_content_version` and `phil_content_working_copy_id`, and the mappings `PhilContentBundle`,
`PhilContentBundleRouting` and `PhilContentBundleDraft`.

Every class is listed with its namespace in [reference.md](reference.md).
