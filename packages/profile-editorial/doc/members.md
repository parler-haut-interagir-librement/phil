# Members

Each member is an independent package, published on Packagist and released on its own. The profile requires it at its
latest published minor version.

## `phil/markup-bundle` — text as safe HTML

- **Constraint:** `^0.1`. **Brings:** `phil/markup` 0.2, `phil/ujo`, `phil/svg-sanitizer`, `phil/css-sanitizer`.
- **Role:** text written in Markdown, BBCode, wiki syntaxes, UJO (Universal JSON Output) or HTML is shown as HTML
  purified at the level the application chooses: basic, forum or all.
- **Adds:** the configuration `phil_markup`, the Twig filters `markup_html`, `markup_purify` and `markup_ujo`, the form
  field `MarkupFormatType`, the conversion of a source to UJO.
- **Why in this profile:** every other member works on text authors wrote, and that text must be shown without
  letting a script through.
- **Needs:** `ext-dom`, `ext-libxml`, `ext-ctype`, `ext-filter` and `ext-mbstring`; Twig for the filters.

## `phil/citations` — sources and bibliographies

- **Constraint:** `^0.1`.
- **Role:** references kept as CSL-JSON, imported from BibTeX and RIS, cited and listed in author-date, notes or
  numeric styles, in English or French.
- **Adds:** `CitationProcessor`, `Cite`, `ReferenceList` and the importers; a library, with no bundle.
- **Why in this profile:** editorial content that makes claims names its sources, and a bibliography is part of the
  text.
- **Needs:** `ext-intl`, `ext-ctype` and `ext-mbstring`.

## `phil/footnotes-sidenotes` — notes beside the text

- **Constraint:** `^0.1`. **Brings:** `phil/ujo`.
- **Role:** the footnotes and margin notes of a UJO document, numbered and checked, shown as accessible endnotes, as
  sidenotes on wide screens, or at the foot of printed pages.
- **Adds:** `NoteResolver`, its options and its HTML; a library, with no bundle.
- **Why in this profile:** notes carry the asides and the citations of long-form writing.
- **Needs:** `ext-mbstring`. `phil/paged-media` and `phil/typography` are used when they are installed.

## `phil/content-warnings-bundle` — warnings readers control

- **Constraint:** `^0.1`. **Brings:** `phil/content-warnings`.
- **Role:** a catalogue of warning themes from configuration, in six languages; warnings on any entity; content folded
  or hidden as each reader chooses.
- **Adds:** the column type `phil_content_warnings`, the fields `ContentWarningsType` and `WarningPreferencesType`, the
  Twig functions and the template `@PhilContentWarnings/fold.html.twig`, the Stimulus controller `reveal`.
- **Why in this profile:** honest publishing says what a piece contains before a reader meets it.
- **Needs:** `ext-intl`; Twig, the forms and Doctrine DBAL for the parts that use them.

## `phil/structured-data-bundle` — what search engines read

- **Constraint:** `^0.1`. **Brings:** `phil/structured-data`, `phil/indexing-consent`, `phil/url`.
- **Role:** one schema.org graph per page, fed by the configuration, the packages and the templates, merged by `@id`,
  checked and printed safely as JSON-LD; a page withdrawn from search engines prints none.
- **Adds:** the configuration `phil_structured_data`, the Twig functions `structured_data()`, `structured_data_add()`
  and `structured_data_about()`, the command `phil:structured-data:check`.
- **Why in this profile:** articles, books and events are found through the data that describes them.
- **Needs:** `ext-dom`, `ext-json` and `ext-libxml`; Twig for the functions.

## Packages close to this profile

These packages are not members: they answer other needs, and an application adds them when it has those.

| Package | Why it is not a member |
|---|---|
| `phil/typography`, `phil/typography-bundle` | Suggested by `phil/markup-bundle` and `phil/footnotes-sidenotes` for the typographic rules of a language; an application that typesets adds them. |
| `phil/paged-media` | Printed and PDF output; used by `phil/footnotes-sidenotes` when installed. |
| `phil/social-cards-bundle` | Suggested by `phil/structured-data-bundle` for share previews; a choice about social networks. |
| `phil/ujo-bundle`, `phil/block-editor-bundle` | Editing UJO documents in the browser; the profile is about publishing them. |
