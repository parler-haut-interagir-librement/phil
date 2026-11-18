# Configuration

```yaml
# config/packages/phil_slides.yaml
phil_slides:
    split: auto
    heading_level: 2
    notes_class: notes
    fragment_class: fragment
    aspect_ratio: '16:9'
    max_slides: 500
    max_source_bytes: 1048576
    markup_level: all
    presenter_role: ROLE_SLIDES_PRESENTER
    layout: '@PhilSlides/layout.html.twig'
    kit_subdirectory: ~
    pdf:
        notes_page_size: letter
```

| Option | Default | What it does |
|---|---|---|
| `split` | `auto` | Where a document is cut: `separators`, `headings`, `both`, or `auto` — at its separators when it holds one at the top level, at its headings otherwise. The directive `headingDivider` of a source cuts at both. |
| `heading_level` | `2` | The deepest heading that starts a slide, 1 to 6. |
| `notes_class` | `notes` | The class of the `container` blocks that hold the speaker's notes. |
| `fragment_class` | `fragment` | The class of the `container` blocks that appear one at a time. |
| `aspect_ratio` | `16:9` | The shape of a slide when the source names none: `16:9` or `4:3`. |
| `max_slides` | `500` | The most slides a deck holds; a document that makes more is refused with `DeckRefused`. |
| `max_source_bytes` | `1048576` | The largest source `DeckReader::fromMarkup()` reads, in bytes. |
| `markup_level` | `all` | The level `phil/markup` reads a source at; see below. |
| `presenter_role` | `ROLE_SLIDES_PRESENTER` | The role the presenter routes ask for; everyone else is refused (403). See [Presenter view](presenter.md#security). |
| `layout` | `@PhilSlides/layout.html.twig` | The Twig template the presenter route extends, with the blocks `title` and `main`. |
| `kit_subdirectory` | `null` | Where the recipe `Button` is, under the components directory: `ui` names it `ui:Button`. When `null`, the subdirectory of the `ux_toolkit` configuration; to set in an environment without the Toolkit. |
| `pdf.notes_page_size` | `letter` | The upright page a slide is printed on with its notes: `letter` or `a4`. |

A class is 1 to 64 letters, digits, `_` or `-`; a value out of range stops the container build.

## The Markdown level

`all` is the default, and the level to keep for decks written by the team of the site. At `all`, raw HTML in a source
becomes `raw` blocks, and the renderer of `phil/ujo-bundle` refuses them unless `phil_ujo.allow_raw` is `true`: that
refusal is the protection, the same as for every other document of the application, so a deck holds no HTML the
application did not allow. HTML comments are the speaker's notes, or the directives of a slide, from the `phil/markup`
version whose Markdown reader keeps them as `note` tunes (see [Authoring](authoring.md#notes-in-html-comments)); before
it, they are left out.

`forum` and `basic` read no raw HTML: they show it, and the comments, as text. Choose one of them only for sources
written by people the application does not trust.

The rendering of each slide — the block types allowed, raw HTML, the limits of a document — is the configuration of
`phil_ujo`, the same as for every other document of the application.
