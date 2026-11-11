# phil/text-diff-bundle

**What changed between two versions, shown in a template and walked through change by change.**

phil/text-diff-bundle brings `phil/text-diff` into a Symfony application. It registers the differ with the bound of
edits the configuration sets, and two Twig filters: `text_diff` compares two texts by words, lines, paragraphs or HTML
and writes the difference with `<ins>` and `<del>`; `text_diff_document` compares two UJO documents block by block and
renders each block with the application's UJO renderer when `phil/ujo-bundle` is installed. The `comparison` Stimulus
controller switches a difference between the inline view, the old text alone and the new text alone, steps from one
change to the next — a replacement is one change —, and announces where it stands in a live region. A stylesheet
colours the marks, underlines insertions and strikes deletions so that colour is never the only cue.

It is for any Symfony application that keeps versions of what people write: the revision history of a page or an
article, the review of a proposed edit, the check of a document before it is published again.

It is written from scratch, on the components Symfony already ships: Config and DependencyInjection for the
configuration, Twig's attribute extensions for the filters, AssetMapper and StimulusBundle for the controller, and
HtmlSanitizer, when configured, for the HTML a template does not mark safe.

## Status

| Part | State |
|---|---|
| The `TextDiff` service, its bound of edits and its default tokenizer from configuration | Implemented and tested |
| `text_diff`: plain text escaped, HTML trusted only when marked safe, sanitized or escaped otherwise | Implemented and tested |
| `text_diff_document`: UJO documents block by block, rendered by the application's renderer | Implemented and tested |
| The `comparison` controller: inline, old and new views; next and previous change; a live counter | Implemented and tested |
| The stylesheet: light and dark, print, views and current change, mapped for AssetMapper | Implemented and tested |
| A side-by-side view in two columns | Not started: the old and new views show one text at a time |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the controller and the stylesheet. |
| [Configuration](doc/configuration.md) | Every key of `phil_text_diff`. |
| [Twig](doc/twig.md) | `text_diff` and `text_diff_document`, and what each trusts. |
| [JavaScript](doc/javascript.md) | The `comparison` controller: targets, values, actions, accessibility. |
| [Styling](doc/styling.md) | The stylesheet, its custom properties and the views. |
| [Extension points](doc/extension-points.md) | The UJO services, the HTML sanitizer, the differ, the controller's labels. |
| [Exceptions](doc/exceptions.md) | What the bundle throws, and when. |
| [Testing](doc/testing.md) | The bundle's tests, and how to test a page that shows differences. |
| [Reference](doc/reference.md) | Every class, service, constant and asset of the bundle. |

## Quick start

```sh
composer require phil/text-diff-bundle
```

```twig
<link rel="stylesheet" href="{{ asset('phil-text-diff/text-diff.css') }}">

<section data-controller="phil--text-diff-bundle--comparison">
    <div role="group" aria-label="View">
        <button type="button" data-action="phil--text-diff-bundle--comparison#show" data-phil--text-diff-bundle--comparison-mode-param="inline" data-phil--text-diff-bundle--comparison-target="mode">Both</button>
        <button type="button" data-action="phil--text-diff-bundle--comparison#show" data-phil--text-diff-bundle--comparison-mode-param="old" data-phil--text-diff-bundle--comparison-target="mode">Before</button>
        <button type="button" data-action="phil--text-diff-bundle--comparison#show" data-phil--text-diff-bundle--comparison-mode-param="new" data-phil--text-diff-bundle--comparison-target="mode">After</button>
    </div>
    <button type="button" data-action="phil--text-diff-bundle--comparison#previous">Previous change</button>
    <button type="button" data-action="phil--text-diff-bundle--comparison#next">Next change</button>
    <p role="status" data-phil--text-diff-bundle--comparison-target="counter"></p>

    <div data-phil--text-diff-bundle--comparison-target="output">
        {{ previous.body|text_diff_document(current.body) }}
    </div>
</section>
```

## Requirements

PHP 8.5 or later, `phil/text-diff`, `phil/ujo`, and the Symfony 8.2 components Config and DependencyInjection.
`twig/twig` for the filters, `symfony/stimulus-bundle` and `symfony/asset-mapper` for the controller and the
stylesheet, `phil/ujo-bundle` for the application's block types, `symfony/html-sanitizer` for HTML a template does not
mark safe — each only when used.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
