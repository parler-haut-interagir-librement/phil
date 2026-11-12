# Extension points

What an application implements, configures, replaces or overrides. Segments, the memory and glossaries are
`phil/translation-memory`'s, and documents are UJO.

## Ports written by the application

Each is named by its service id in the configuration; the screen answers 404 until `sources` and `policy` are set. See
[The translation screen](screen.md#the-applications-services).

### `SourceDocuments`

`Phil\TranslationBundle\Application\SourceDocuments` — finds the documents to translate (`sources`).

| Method | Contract |
|---|---|
| `find(string $document): ?SourceDocument` | The document's UJO content, its language and its title, or `null` for a document it does not know |

### `TranslationPolicy`

`Phil\TranslationBundle\Application\TranslationPolicy` — who may translate and review, for the current user (`policy`).

| Method | Contract |
|---|---|
| `canTranslate(string $document, LanguageTag $targetLanguage): bool` | See the page, write and propose targets, confirm and clear them, export and import; see the memory's units learnt from that document |
| `canReview(string $document, LanguageTag $targetLanguage): bool` | Mark targets as reviewed, by the screen or by an imported XLIFF file; remove comments |
| `canEditGlossary(LanguageTag $sourceLanguage, LanguageTag $targetLanguage): bool` | Add, change and remove the terms of that glossary, export and import it |

### `Translators`

`Phil\TranslationBundle\Application\Contract\Translators` — who is at the screen, and how each person is thanked
(`translators`). Optional. See [Comments and credits](collaboration.md#the-translators-service).

| Method | Contract |
|---|---|
| `current(): ?string` | The identifier of the current user, at most 255 bytes, kept with what they write; `null` when unknown |
| `credit(string $translator): ?string` | The name a person is credited under in public; `null` when they asked not to be |

### `MachineTranslator`

`Phil\TranslationBundle\Application\Contract\MachineTranslator` — a machine translation service, behind an adapter
named after it (`machine_translation.translator`). Optional, and off by default. See
[Machine translation](machine-translation.md).

| Method | Contract |
|---|---|
| `name(): string` | The name of the service |
| `translate(list<string> $sources, LanguageTag $sourceLanguage, LanguageTag $targetLanguage): list<string>` | One target per source, in order |

### `Inflector`

`Phil\TranslationMemory\Glossary\Contract\Inflector` — the inflected forms of glossary terms (`inflector`), from
`phil/translation-memory`. Optional; `RegularPlurals` is registered for it.

## Stores

`SegmentRepository`, `TermRepository`, `Contract\CommentRepository` and `Phil\TranslationMemory\Memory\MemoryStore`
are aliases of the stores
`store` chooses — Doctrine, or memory for tests; an application points them at its own services, the memory store
indexed on its languages, fingerprint and length. See [Storage](storage.md).

## Configuration

| Option | What it extends |
|---|---|
| `granularity` | Segments by block field or by sentence |
| `prefill`, `min_score`, `max_suggestions` | What the memory proposes |
| `layout` | The template the screen extends, with blocks `title` and `content`: the application's base template, with its Stimulus application |
| `store`, `entity_manager` | Where segments, terms, comments and the memory are kept |
| `variants` | The regional variants that inherit from their parent |

See [Configuration](configuration.md).

## Services and the screen

| Point | How |
|---|---|
| Translating in code | `Translations`, `Glossaries` and `Comments`, public — see [Translating documents](translations.md) |
| The checks run | A `Checker` given to `Translations` as its last argument, in the application's own service definition |
| The credit on a published page | The Twig function `phil_translation_credits()` and `@PhilTranslation/credits.html.twig` |
| An EasyAdmin back office | `TranslationAdmin::menuItems()` — see [Glossary pages](glossary-pages.md#easyadmin) |
| Memory lookups of the application's own | `Phil\TranslationMemory\Memory\TranslationMemory`, public |
| The look of the screen | The classes under `<main class="phil-translation">`: `progress`, `state state-<state>`, `terms`, `issues`, `suggestions` and the others |
