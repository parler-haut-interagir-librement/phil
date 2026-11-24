# phil/faq-bundle

**Frequently asked questions by section, searchable without JavaScript, each with a stable anchor, an anonymous
"Was this answer useful?" vote, and the `FAQPage` JSON-LD of the page.**

phil/faq-bundle shows the questions of a site and the answers to them. Questions live in **sections**, each in its
order; a question has a **translation per language** — its wording, its answer written in Markdown, HTML or plain text
and kept as purified HTML, whether it is published in that language, and when it last changed, in UTC. A locale falls
back to its language: `fr_CA` shows the `fr` questions it lacks.

Each question is a `details` element whose `id` is its **anchor**, made once from its first wording and kept when the
wording changes, so a shared link keeps opening the same question; each has a link to share it and a button that copies
it. The page **searches** with a plain GET form that the server filters, accents and case aside, and that the Stimulus
controller filters as one types. A search that finds nothing shows the link to the **contact form** and is **counted**,
normalized, per language and per day: a total, never who searched.

The **vote** is yes or no, counted per question. A page carries a signed vote token that votes once for each of its
questions, which the server remembers by a hash until the token expires; the Stimulus controller remembers in the
browser's own storage which questions it voted for. The votes are held back per question by `symfony/rate-limiter`
and checked by Symfony's stateless CSRF protection. Nothing personal is read or kept — no address, no identifier, no
cookie —, so the bundle needs no module of a privacy bundle.

The `FAQPage` JSON-LD, written with `phil/structured-data`, describes the questions the page shows; how a search
engine displays it is that engine's choice. The same page exports as Markdown for an `llms.txt` file. An optional
**administration**, guarded by a role and reachable from an EasyAdmin dashboard, writes the sections and questions in
each language, orders them, publishes them, and shows the totals of the votes and of the searches without answer;
`phil:faq:export` and `phil:faq:import` move them through YAML.

It is a Symfony bundle on the Doctrine ORM, Twig, Symfony UX Twig Component, `phil/markup`, `phil/slug` and
`phil/structured-data`. Its tables, prefixed `phil_faq_`, are tested on SQLite, MySQL 8.4 and PostgreSQL 17. Its
services keep no state between requests and serve a long-running worker.

## Status

| Part | State |
|---|---|
| `Section`, `Question` and their translations, the anchor kept when the wording changes, the order by "before" | Implemented and tested, on SQLite, MySQL 8.4 and PostgreSQL 17 |
| `FaqEditor`: sections, questions, translations, order, publication; answers read by `phil/markup` and purified | Implemented and tested |
| `FaqPages`: the page in a language with its fallback, the search folded as the server and the browser fold it | Implemented and tested |
| `HelpfulVotes`, `VoteTokens`: one vote per page token and per question, the rate limit, nothing of the reader kept | Implemented and tested |
| `UnansweredSearches`: the searches without answer, normalized, counted per language and day | Implemented and tested |
| `FaqStructuredData`, `FaqMarkdown`: the `FAQPage` JSON-LD, checked by the `Validator` of `phil/structured-data`; the Markdown of an `llms.txt` | Implemented and tested |
| The Twig component `PhilFaq`, the page and its routes, with the recipes of the PHIL UX kit | Implemented and tested |
| The Stimulus controller: the search as one types, the question of the address opened, the votes without leaving the page | Implemented and tested under Node, with AssetMapperBundle and StimulusBundle |
| The administration, its EasyAdmin menu; `phil:faq:export` and `phil:faq:import` | Implemented and tested |
| An assistant that answers with a language model | Not part of the bundle |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the bundle, the tables, the routes, the recipes of the kit, the controller. |
| [Configuration](doc/configuration.md) | Every option, the rate limits, the services registered. |
| [Templates](doc/templates.md) | The component, the page, the search, the votes, the JSON-LD, the Markdown export, the accessibility. |
| [Extension points](doc/extension-points.md) | What an application configures, replaces or overrides. |
| [Testing](doc/testing.md) | The tests, the servers they read, the checks in a browser. |
| [Reference](doc/reference.md) | Every class and file of the bundle. |

## Quick start

```sh
composer require phil/faq-bundle
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install input --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install copy-button --kit=github.com/parler-haut-interagir-librement/ux-kit
```

```yaml
# config/routes/phil_faq.yaml
phil_faq:
    resource: '@PhilFaqBundle/config/routes.php'
    prefix: /faq
```

```yaml
# config/packages/phil_faq.yaml
phil_faq:
    contact_url: /contact
```

```php
$section = $editor->addSection(Locale::fromString('en'), 'Orders');
$editor->addQuestion($section->id, Locale::fromString('en'), 'How do I pay?', 'By **card** or transfer.');
```

The questions are then at `/faq/`, or wherever a template writes `<twig:PhilFaq />`.

## Requirements

PHP 8.5 or later with `ext-intl`, `ext-mbstring` and `ext-sodium`, Symfony 8.2 or later with its CSRF protection,
Symfony UX Twig Component 3.7 or later, Doctrine DBAL 4.5 and ORM 3.7. Symfony Security, the console, the YAML
component, AssetMapper, StimulusBundle and EasyAdmin are optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
