# phil/translation-bundle

**A translation screen for Symfony: segments that survive edits, a memory that remembers, a glossary that checks.**

phil/translation-bundle brings `phil/translation-memory` into a Symfony application. A document written in blocks — a
document in UJO (Universal JSON Output), the block document format of `phil/ujo` — is cut into segments kept in
Doctrine, one row per segment and target language, with its state from `untranslated` to `reviewed`. When the author
edits the source, the segments are brought up to date: the unchanged ones keep their translation, those that moved take
it with them, those that changed become `stale`. Translators work on a screen that shows each segment with what the
translation memory proposes, the glossary's terms and issues, the quality checks, what changed under a stale target,
and its comments; they can export the document as XLIFF 2.1 for a translation tool, and import XLIFF 2.x or 1.2 back.
A regional variant such as `fr-CA` is read through its parent: only the segments that differ in Canada are translated
again. Machine translation proposes targets behind a port, off by default and marked as such, and translators are
thanked with a public credit.

It is for Symfony applications that publish what people write in more than one language: articles, pages, rules,
books. The memory and the glossaries are shared by every document of the application, so a sentence translated once is
proposed everywhere and a term is translated the same way throughout. It translates UJO documents only: the message
catalogues of an application's interface stay with Symfony's translation component and its tools.

It is a bundle built on the library; it requires Doctrine ORM, Twig and Symfony's HTTP and routing
components. The application keeps the documents: it names them to the bundle through a `SourceDocuments` service, says
who may translate and review through a `TranslationPolicy`, and publishes the translated document the bundle puts
together. Until both services are configured, the screen answers 404.

The design follows the layers of the monorepo: `Domain` holds the rows — `SegmentRecord`, `MemoryRecord`, `TermRecord`,
`CommentRecord` — and the ports of their stores; `Application` holds `Translations`, `Glossaries`, `Comments`,
`Progress` and the application's ports — `SourceDocuments`, `TranslationPolicy`, `Translators`, `MachineTranslator`;
`Infrastructure` holds the Doctrine and in-memory stores, the controllers and their routes, the Twig function of the
credits, the EasyAdmin links, and the service wiring.

## Status

| Part | State |
|---|---|
| Segments in Doctrine, prepared from a UJO document and brought up to date when the source changes | Implemented and tested |
| Exact matches of the memory proposed to new segments | Implemented and tested |
| Translate, propose, review, confirm and clear a segment; usable targets learnt by the memory | Implemented and tested |
| Translation memory and glossaries in Doctrine, shared by every document | Implemented and tested |
| XLIFF 2.1 export, XLIFF 2.x and 1.2 import with a report | Implemented and tested |
| The translated document put together from usable segments | Implemented and tested |
| Translation screen in Twig: segments, matches, terms, issues, changes, forms, export and import | Implemented and tested |
| Regional variants read through their parent, `fr-CA` and `en-CA` by default; translated and raw readings | Implemented and tested |
| Quality checks on the screen, dismissed per segment by a flag kept with its row | Implemented and tested |
| One target per segment and comments on segments, signed by the current user | Implemented and tested |
| Who wrote and reviewed each target; a public credit of the translators, in Twig | Implemented and tested |
| Glossary pages through `Glossaries`, inflected forms, TBX export and import, EasyAdmin links | Implemented and tested |
| Machine translation behind a port with named adapters, off by default, marked as machine output | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering the bundle, the tables and the routes. |
| [Configuration](doc/configuration.md) | Every option of `phil_translation`. |
| [Translating documents](doc/translations.md) | `Translations`: preparing, the actions on a segment, progress, XLIFF, the translated document. |
| [The translation screen](doc/screen.md) | The routes, what the page shows, the forms, the application's services, the layout. |
| [Memory and glossaries](doc/memory-and-glossaries.md) | The shared memory, `Glossaries`, and how both reach the screen. |
| [Regional variants](doc/variants.md) | The chain from `fr-CA` to `fr` to the source, the translated and raw readings. |
| [Comments and credits](doc/collaboration.md) | One target per segment, comments, who did what, the public credit, `Translators`. |
| [Glossary pages](doc/glossary-pages.md) | Editing glossaries on screen, TBX, EasyAdmin links, inflected forms. |
| [Machine translation](doc/machine-translation.md) | The port, the adapters, proposals marked as machine output. |
| [Storage](doc/storage.md) | The three tables, their keys, and the stores in memory. |
| [Exceptions](doc/exceptions.md) | What the bundle throws, and what the screen answers. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | The bundle's tests, and how to test an application that uses it. |
| [Reference](doc/reference.md) | Every type of the bundle, with its role. |

## Quick start

```sh
composer require phil/translation-bundle
```

```yaml
# config/packages/phil_translation.yaml
phil_translation:
    granularity: sentence
    sources: App\Translation\ArticleSources      # implements SourceDocuments
    policy: App\Translation\EditorsPolicy        # implements TranslationPolicy

# config/routes/phil_translation.yaml
phil_translation:
    resource: '@PhilTranslationBundle/config/routes.php'
    prefix: /admin/translate
```

The screen of an article is then `/admin/translate/article:42/fr`, and its French version is put together by:

```php
$french = $translations->translated('article:42', $article->content(), LanguageTag::of('fr'));
```

## Requirements

PHP 8.5 or later, Symfony 8.2, Doctrine ORM 3.7, Twig 3.21, `phil/translation-memory` and `phil/ujo`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
