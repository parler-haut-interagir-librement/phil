# phil/content-bundle

**Pages and articles stored by locale, stage and version, and published from draft to live.**

phil/content-bundle stores the content of a Symfony site with Doctrine. A piece of content is a **root** and its
**dimension rows**: one row per locale, per stage (`draft` or `live`) and per version, plus one **unlocalized row** for
the values every locale shares. On top of that storage, the bundle publishes drafts to the live stage, freezes what it
published and forks the next version for the next edit. It keeps a route table that answers old slugs with permanent
redirects, serves live content through a controller that renders the application's Twig templates, gives the sitemap its
pages, writes Atom feeds and signs preview links to drafts. A draft's text keeps the markup its author writes in —
Markdown, BBCode, MediaWiki, DokuWiki, Creole, HTML, plain text, UJO or a format an add-on brings —; publishing converts
it into UJO (Universal JSON Output), the block document format of `phil/ujo`, stored with its HTML on the live row.
Content is written from an administration linked from an EasyAdmin back office, from the console, from PHP or from a
Doctrine migration, through `ContentManager`. A publication that would lose part of the author's text is refused until
the editor accepts it. Each page that goes online or offline dispatches `PagePublished` or `PageRetired` of
`phil/publishing`, for the sitemap, a cache or a ping to follow. An author's changes are saved by themselves, privately,
beside the draft, and named drafts keep alternatives. A kind may have a single content — the home page —, or keep every
publication readable, dated — a « now » page. The published contents feed a search index of `phil/search-bundle`, and
`phil/scheduled-transitions-bundle` publishes them at a set moment.

**What it does not do**: no media library (an excerpt image is a reference the application resolves), no user, tag,
category or security model, no live preview or private sharing link, no search engine of its own, and no fallback chain
it would derive by itself. The application declares every one.

It suits a Symfony application whose editors write pages, news or other kinds of content, often in several
languages, and whose readers must see only what was published. A site of a few fixed pages in one language, with no
drafts, is better served by plain routes and Twig templates. phil/directory-bundle builds on it and stores each
listing as a content. Publishing at a set time goes through the `phil_content_publication` workflow, which
`phil/scheduled-transitions-bundle` applies when the kernel has it; neither bundle requires the other.

It is a Symfony bundle that plugs into Doctrine ORM, `symfony/workflow`, the router and Twig, and uses phil/markup,
phil/ujo, phil/slug, phil/sitemap, phil/url and phil/publishing. Capabilities (a template, an editorial workflow, SEO,
an excerpt, a link, a slug, a body of text) are interface and trait pairs that a row class composes. The bundle maps
their columns onto every row class that implements them, and a tagged pipeline of data mappers, mergers and
normalizers reads and writes them. The design follows the content storage of [Sulu](https://github.com/sulu/sulu) 3
(`packages/content`) as a pattern only, around plain domain types.

## Status

| Part | State |
|---|---|
| Dimensions, fallback chains, aggregation | Done, tested |
| Capabilities: template, workflow, SEO, excerpt, link, slug, purified body | Done, tested; each with its data mapper (none for the workflow), merger, normalizer and mapping |
| Body lifecycle: a draft in its author's format, published as UJO and its HTML | Done, tested for every format over in-memory ports and in a kernel on SQLite |
| Doctrine storage: base classes, mapping listener, DBAL types, repositories, transaction | Done, tested on SQLite, MySQL 8 and PostgreSQL |
| Publication draft → live on `symfony/workflow` | Done, tested over in-memory ports with several locales, and in a kernel on SQLite, MySQL 8 and PostgreSQL |
| Versions: freezing on publication, fork to the next version, latest version | Done, tested |
| Route table, slug history and 301 redirects, resolver | Done, tested on SQLite, MySQL 8 and PostgreSQL |
| Controller and Twig rendering (template by key, content, SEO tags, URLs) | Done, tested in a kernel |
| Console commands: create, edit, publish, unpublish, list | Done, tested in a kernel |
| PHP interface for migrations (`ContentManager`), deletion of a root | Done, tested |
| Pages of the sitemap, Atom feeds, signed preview links of drafts | Done, tested over in-memory ports and in a kernel |
| `PagePublished` and `PageRetired` of `phil/publishing` on publish, unpublish and delete | Done, tested over in-memory ports and in a kernel |
| A publication that loses content refused, from PHP, the console, the workflow and the administration | Done, tested |
| Single kinds (home page, contact details) and the dated archive (« now » page) | Done, tested over in-memory ports and in a kernel |
| Working copies: the private autosave of each author, named drafts | Done, tested in a kernel on SQLite |
| Administration pages through the bundle's services, linked from EasyAdmin | Done, tested in a kernel |
| Search feed of `phil/search-bundle` (UJO passages by language) | Done, tested in a kernel on its `dbal` adapter |
| Scheduled publication on `phil/scheduled-transitions-bundle` | Done, tested over its `Scheduler` |

## Documentation

| Topic | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the framework bundles that switch on the Doctrine storage and the rendering layer, registering the bundle, and the schema to migrate. |
| [Configuration](doc/configuration.md) | Every key of `phil_content` — `locales`, `roots`, `entity_manager`, `routes`, `templates`, `default_template`, `preview`, `feed_limit`, `raw_html_role`, `singles`, `archives`, `search_index`, `admin` — with types, defaults, constraints, an annotated example, and the services each one wires. |
| [Roots, dimensions and fallback chains](doc/dimensions.md) | `DimensionKey` and `Stage`, with `Locale`, `Version`, `FallbackChain` and `DeclaredLocales` of `phil/locale-dimensions`: the model, its invariants, and how one dimension is read. |
| [Doctrine storage](doc/storage.md) | The two entities an application writes, the base classes, the mapping listener, the DBAL types, the repositories, references and identifiers, and the transaction's behaviour on failure. |
| [Capabilities](doc/capabilities.md) | The seven capabilities, their values, columns and validation rules, the body's compile-and-purify path, its conversion into UJO on publication, raw HTML for a trusted role, and the default markup adapters. |
| [The pipeline](doc/pipeline.md) | Data mappers, mergers and normalizers: the submitted data keys ("Submitted data"), the merge rules, and the normalized form templates receive. |
| [Publication and versions](doc/publication.md) | The workflow's places and transitions, guarding them, publishing and the refusal of a publication that loses content, the page events, unpublishing, scheduling, the dated archive, removing a draft, freezing and forking versions, and editing. |
| [Routing](doc/routing.md) | The route table and its invariants, mounting `ContentController`, routes of a fixed address, what the controller answers, and URL generation. |
| [Sitemap, feeds and previews](doc/sitemap-feeds-previews.md) | The pages the contents give the sitemap, with `hreflang` alternates, the Atom feed of a kind, and signed links to a draft. |
| [Rendering](doc/rendering.md) | Template resolution, the variables a template receives, the Twig functions `content`, `content_single`, `content_archive`, `content_url`, `content_link_url`, `content_seo` and `content_schema`, and the archive page. |
| [Administration and working copies](doc/administration.md) | Enabling the administration, its pages and buttons, the EasyAdmin menu item, the autosave of each author, named drafts, and the Stimulus controller. |
| [Search](doc/search.md) | The documents the published contents give `phil/search-bundle`: their text by language, filters and identifiers. |
| [Console commands](doc/console.md) | `phil:content:create`, `edit`, `publish`, `unpublish` and `list`: arguments, the `--file`, `--field`, `--json` and `--accept-losses` options, output and exit codes. |
| [Writing content from PHP](doc/content-manager.md) | Every method of `ContentManager`, its errors and transactions, and writing content from a Doctrine migration. |
| [Extension points](doc/extension-points.md) | The persistence, markup and pipeline ports with their contracts and example adapters, an application capability, workflow events, and every service id, tag and name to rely on. |
| [Testing](doc/testing.md) | The unit, functional and integration suites, their environment variables, how to run them, and how an application tests code that uses the bundle. |
| [API reference](doc/reference.md) | Every class and interface by layer, with its role and state. |

## Requirements

- PHP 8.5 or later.
- Symfony 8.2 components (`dependency-injection`, `config`, `workflow`, `routing`, `http-kernel`, `console`,
  …), `doctrine/orm` 3.7, `doctrine/dbal` 4.2, `twig/twig` 3.21, `phil/markup`, `phil/locale-dimensions` and
  `phil/ddd-bundle`.
- For the Doctrine storage and the console commands: DoctrineBundle in the kernel. For the controller and the Twig
  functions: DoctrineBundle and TwigBundle, with FrameworkBundle.

The full list, with the reason for each, is in [doc/installation.md](doc/installation.md).

## Quick start

Install the bundle and the framework bundles it wires (until the packages are on Packagist, declare a repository
that holds `phil/content-bundle` and `phil/ddd-bundle`; see [doc/installation.md](doc/installation.md)):

```bash
composer require phil/content-bundle:^0.1@dev phil/ddd-bundle:^0.1@dev doctrine/doctrine-bundle symfony/twig-bundle
```

Register `Phil\ContentBundle\PhilContentBundle` in `config/bundles.php`, then write a root and its row class:

```php
#[ORM\Entity]
class News extends AbstractContentRoot
{
    #[ORM\Id, ORM\Column(name: 'id', type: NewsIdType::NAME)]
    private NewsId $id;

    public function __construct()
    {
        $this->id = NewsId::generate();
    }

    public static function dimensionContentClass(): string
    {
        return NewsDimension::class;
    }
}

#[ORM\Entity]
class NewsDimension extends AbstractDimensionContent implements TemplateCapable, WorkflowCapable, ExcerptCapable,
    RoutableCapable, BodyCapable
{
    use BodyTrait;
    use ExcerptTrait;
    use RoutableTrait;
    use TemplateTrait;
    use WorkflowTrait;
}
```

The smallest configuration that serves pages:

```yaml
# config/packages/phil_content.yaml
phil_content:
    locales:
        fr: []
    roots:
        - App\Content\News
    routes:
        news: news_show
    default_template: content/news.html.twig

# config/routes/content.yaml
news_show:
    path: /{_locale}/news/{slug}
    controller: Phil\ContentBundle\Infrastructure\Http\ContentController
```

Migrate the schema (`bin/console doctrine:migrations:diff`, then `migrate`), and write and publish content:

```bash
bin/console phil:content:create news fr --field=slug=opening --field=excerptTitle=Opening \
    --field=bodySource="The site **opens** today." --publish
```

`/fr/news/opening` now renders `content/news.html.twig`, which receives the normalized content:

```twig
<h1>{{ content.excerptTitle }}</h1>
{{ content.bodyHtml|raw }}  {# the only value printed unescaped: it is purified on every read #}
```

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).

Sulu is read as a pattern, for its model and its tests.
