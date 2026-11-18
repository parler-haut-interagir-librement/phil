# phil/slides

**Slides in Symfony: a UJO document, or a Markdown source, cut into slides, with speaker notes the audience never
sees, a presenter view in a second window behind an optional protected route, fragments, full screen and PDF export.**

phil/slides turns a UJO (Universal JSON Output) document into a deck: one slide per section, or per separator block —
the `---` of Markdown. A Markdown source is read by `phil/markup`, whose front matter gives the deck its global
directives: title, language, classes, pagination, header, footer, shape and the heading level that cuts. Each slide is
rendered by the `Ujo` service of `phil/ujo-bundle`, so it is checked and purified as every other document of the
application. The speaker's notes — a `::: notes` container, or the `note` tune of any block, which `phil/markup` gives
the block after an HTML comment — are taken out of what the audience sees: they are in neither the audience page nor
the public PDF. A comment made of Marp directives, `<!-- _class: lead -->`, shapes its slide instead.

Two templates draw the deck. The audience page reads as a plain sequence of slides without script; its Stimulus
controller shows one slide at a time, reveals the fragments one by one, answers the arrows, Page Up, Page Down, Home,
End and Escape, goes full screen, keeps the address on the current slide and announces each new slide in a live region.
The presenter view, opened in a second window, shows the current slide with its notes, the next slide and a timer;
both windows stay at the same position through a `BroadcastChannel`, with no server. An optional routes file serves
the presenter view and the PDF with the notes to one configurable role only, `ROLE_SLIDES_PRESENTER` by default;
everyone else is refused before any deck is looked for. Printing goes through `phil/paged-media`: one page per slide,
the notes left out unless asked for, and a PDF from the print engine of `phil/paged-media-bundle` when it is
installed.

It is for Symfony applications that show talks, courses or reports as slides from the documents they already keep.
The controllers are served by AssetMapper; the buttons and links are the recipe `Button` of the PHIL UX kit or of the
Shadcn kit, installed by the Symfony UX Toolkit; the look rests on the `--phil-*` tokens of the PHIL UX stylesheet, and
the labels are in English and French.

## Status

| Part | State |
|---|---|
| `Slicer`: a document cut at its separators or headings, notes taken out, global directives read | Implemented and tested |
| `CommentDirectives`: notes and Marp directives from HTML comments, kept by `phil/markup` as `note` tunes | Implemented and tested |
| `DeckReader`: decks from a document, its JSON, a reading of `phil/markup` or a source | Implemented and tested |
| `SlideRenderer`: each slide and its notes rendered by the `Ujo` service of `phil/ujo-bundle` | Implemented and tested |
| The audience and presenter templates, the Twig functions, their buttons from the PHIL UX or Shadcn kit | Implemented and tested |
| The presenter routes: presenter view and PDF with notes, for the role `presenter_role` only | Implemented and tested |
| The Stimulus controllers `deck` and `presenter`, and the stylesheet | Implemented and tested |
| `DeckPrint` and `DeckPdf`: one page per slide, notes on request | Implemented and tested |
| The configuration `phil_slides`, the Flex recipe `recipe/0.1` | Implemented and tested |
| Labels in English and French | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, enabling the bundle, the Flex recipe, the controllers and the stylesheet. |
| [Configuration](doc/configuration.md) | Every option of `phil_slides`. |
| [Authoring](doc/authoring.md) | Where slides are cut, notes, fragments, the directives of a source. |
| [Showing a deck](doc/showing.md) | The audience page, the keyboard, full screen, accessibility, the look. |
| [Presenter view](doc/presenter.md) | The presenter routes and their security, the second window, the notes, the next slide, the timer, the synchronisation. |
| [PDF export](doc/pdf.md) | `DeckPrint` and `DeckPdf`, the pages, the notes. |
| [Extension points](doc/extension-points.md) | What an application configures, overrides or replaces. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

```sh
composer require phil/slides
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
```

```twig
{# the audience, at a route of the application #}
{{ include('@PhilSlides/deck.html.twig', {deck: slides_markup(talk.source, 'markdown', talk.slug), presenter_url: path('phil_slides_presenter', {deck: talk.slug})}) }}
```

```yaml
# config/routes/phil_slides.yaml: the speaker's view and the PDF with notes, for ROLE_SLIDES_PRESENTER only
phil_slides_presenter:
    resource: '@PhilSlidesBundle/config/routes.php'
    prefix: /presenter
```

The presenter routes find each deck through a service of the application that implements `PresentedDecks`; see
[Presenter view](doc/presenter.md).

```markdown
---
title: A talk
paginate: true
---

# Welcome

::: notes
Thank the organisers.
:::

---

## The point

::: fragment
Revealed on the next key press.
:::
```

## Requirements

PHP 8.5 or later with `ext-mbstring`; `phil/ujo` and `phil/ujo-bundle` 0.1, `phil/markup` 0.2, `phil/paged-media`
0.1; `symfony/config`, `symfony/dependency-injection`, `symfony/http-foundation`, `symfony/http-kernel` and
`symfony/routing` 8.2 or later; `symfony/ux-twig-component` 3.7 or later; `twig/twig` 3.21 or later; the recipe
`Button` of `phil/ux-kit` or of the Shadcn kit. TwigBundle, AssetMapper, StimulusBundle, SecurityBundle, the
translator and `phil/paged-media-bundle` are optional.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
