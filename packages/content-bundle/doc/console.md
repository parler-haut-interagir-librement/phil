# Console commands

Beside the administration ([administration.md](administration.md)), content is edited with six commands, five of
them registered when the kernel has DoctrineBundle. Each calls `ContentManager` ([content-manager.md](content-manager.md)).

| Command | Arguments | Options | Does |
|---|---|---|---|
| `phil:content:create` | `resource-key`, `locale` | `--file`, `--field`, `--json`, `--publish` | creates a root of the kind and its first draft in the locale, version 1, `unpublished`; with `--publish`, publishes it afterwards — a body that loses content in UJO is then refused, and the draft stays |
| `phil:content:edit` | `reference`, `locale` | `--file`, `--field`, `--json` | edits the draft of the locale, forking a published version first |
| `phil:content:publish` | `reference`, `locale` | `--accept-losses` | publishes the draft of the locale; what the conversion of its body into UJO does not keep is listed first, and the editor asked whether to publish anyway. With `--accept-losses`, it is published without asking; without a terminal to ask and without the option, the publication is refused ([publication.md](publication.md#a-publication-that-loses-content-is-refused)) |
| `phil:content:unpublish` | `reference`, `locale` | — | takes the locale offline |
| `phil:content:preview-link` | `reference`, `locale` | — | prints a signed link to the draft of the locale ([sitemap-feeds-previews.md](sitemap-feeds-previews.md)); needs TwigBundle |
| `phil:content:list` | `resource-key…` (optional, several) | — | one line per locale of the latest draft version of each root: content, locale, draft version, place, slug, live version; every declared kind when none is given |

A `reference` is `resource_key:identifier` (`news:0192f3c4-…`, `static_page:12`).

```bash
bin/console phil:content:create news fr --file=news.yaml --field=slug=opening --publish
bin/console phil:content:edit news:0192f3c4-7b1e-7c4d-9a1f-2b3c4d5e6f70 fr --field=excerptTitle="The site opens"
bin/console phil:content:publish news:0192f3c4-7b1e-7c4d-9a1f-2b3c4d5e6f70 fr
bin/console phil:content:unpublish news:0192f3c4-7b1e-7c4d-9a1f-2b3c4d5e6f70 fr
bin/console phil:content:list news
```

## Submitted values

`create` and `edit` take the keys of the normalized form ([pipeline.md](pipeline.md#submitted-data)) from three
sources, applied in this order, so an option overrides the file:

1. `--file=<path>`: a YAML (`.yaml`, `.yml`) or JSON (`.json`) file holding a map of keys to values;
2. `--field=key=value` (repeatable): a string value; a dotted key sets a key inside a map
   (`--field=templateData.title=Hello`);
3. `--json=key=<json>` (repeatable): a typed value — `--json=seoNoIndex=true`, `--json=slug=null`,
   `--json='seoKeywords=["larp","2026"]'`. A dotted key works the same way.

```yaml
# news.yaml
templateKey: news
excerptTitle: Opening
excerptDescription: The site opens today.
seoKeywords: [opening, news]
bodySource: |
    The site **opens** today.
templateData:
    subtitle: A first article
```

A missing file, an unknown extension, a file that does not parse, a file that is not a map of string keys, an
option without `=`, or a `--json` value that is not JSON is refused with an error message.

## Output and exit codes

- On success, each command prints a success message; `phil:content:create -q` prints the reference alone, for a
  script.
- **A refusal of the domain** — a `DomainError`: a slug taken, a locale not served, a transition not allowed, a
  value refused, a root not found, a malformed reference — is printed as an error message, and the command exits
  with `Command::FAILURE`.
- A mistake of the configuration or code is not a refusal of the domain, and surfaces as an exception: for
  instance a resource key that no declared root has (`InvalidContentMapping`), or a root whose constructor needs
  arguments without a `ContentRootFactory` of the application.

`create --publish` runs the creation and the publication in two transactions: when the publication is refused,
the root and its draft stay created, and the command exits with a failure.

## Classes

`Infrastructure\Console\ContentCommand` is the abstract base of the five commands: it holds the
`ContentManager`, declares the data options, and turns a `DomainError` into an error message.
`Infrastructure\Console\SubmittedFields::read(?string $file, array $fields, array $json)` reads the submitted
values as described above.
