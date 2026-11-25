# Pages

## The routes

| Route | Path | Page |
|---|---|---|
| `phil_legal_notices_legal_notice` | `/legal` | The legal notice in force |
| `phil_legal_notices_legal_notice_history` | `/legal/history` | Its versions |
| `phil_legal_notices_legal_notice_version` | `/legal/v{number}` | One version |
| `phil_legal_notices_privacy_policy` | `/privacy` | The privacy policy in force |
| `phil_legal_notices_privacy_policy_history` | `/privacy/history` | Its versions |
| `phil_legal_notices_privacy_policy_version` | `/privacy/v{number}` | One version |

`DocumentController::route(DocumentType $type, string $page = '')` gives these names. A document without a version in
force, a draft, a version that does not exist and a history without public versions are answered 404.

## What a page shows

The page of a version shows its title, its number and where it stands — in force since a date, taking effect on a
date, replaced, withdrawn —, a link to the history, the summary of its changes, a link to the next version announced,
then its body. The privacy policy also lists the retention periods, as a table, and the consent categories with their
cookies, unless `pages.retention_section` or `pages.consent_section` is off — for a body that places them itself with
`${retention.<id>}` and `${consent.<key>}`.

The history lists the published and withdrawn versions, the newest first, with their effective dates and summaries,
the version in force marked as the current page.

The body is shown in the locale of the request, else in its language, else in `default_locale`; every text of the
pages, its punctuation included, is in the `phil_legal_notices` domain, in English and French, and a template writes no
word of its own: a site changes a text, or adds a language, with a translation file of that domain. Each response
carries a `Content-Language`; the page in force and the history are cached for five minutes, a version for an hour.

## The recipes of the kit

The templates draw their parts with recipes of the PHIL UX kit, installed in the application by the Symfony UX
Toolkit; nothing is rendered from `vendor/`:

```sh
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install table --kit=github.com/parler-haut-interagir-librement/ux-kit
```

| Template | Recipes |
|---|---|
| `document.html.twig` | `Badge` for where the version stands — `default` in force, `outline` taking effect, `secondary` replaced, `destructive` withdrawn —; `Table` with `Table:Header`, `Table:Body`, `Table:Row`, `Table:Head` and `Table:Cell` for the retention periods |
| `history.html.twig` | `Badge` for the version in force and the withdrawn ones |

These recipes have the names, props and blocks of the Shadcn kit of the Toolkit, so a site on Tailwind installs them
from the Shadcn kit instead, with Twig's HTML extension and the Tailwind filters its recipes call; the pages are
tested with both kits. The components are found under the components subdirectory of the `ux_toolkit`
configuration, as `phil_legal_notices_kit('Table:Row')` names them; in an environment where the Toolkit is not enabled,
as production often is, repeat it in the option `pages.kit_subdirectory`. A template of the application's own calls a
recipe the same way: `{% component (phil_legal_notices_kit('Badge')) with {variant: 'secondary'} %}`.

The cookies of a consent category are written as a list in the language of the page — `_pk_id.*, _pk_ses.*, and
_pk_ref.*`, `_pk_id.*, _pk_ses.* et _pk_ref.*` — by the `format_list` filter of twig/intl-extra. The bundle registers
that extension, and `IntlExtensionPass` removes it when the application registers its own: TwigExtraBundle with its
intl part enabled, or a Twig extension of the class `Twig\Extra\Intl\IntlExtension`. Twig always holds one.

## Templates

| Template | Context |
|---|---|
| `@PhilLegalNotices/document.html.twig` | `type`, `version`, `html`, `summary`, `retention`, `consent`, `now`, `in_force`, `upcoming`, `layout` |
| `@PhilLegalNotices/history.html.twig` | `type`, `versions`, `now`, `in_force`, `upcoming`, `layout` |
| `@PhilLegalNotices/_links.html.twig` | none: it calls `phil_legal_notices_links()` |
| `@PhilLegalNotices/layout.html.twig` | the blocks `title` and `content`, and the footer links |

The layout of the bundle is a bare HTML page; the application's layout loads the stylesheet of phil/ux-css, which gives
the recipes their look and dresses the rest of the page — headings, lists, links — without a class.

`pages.layout` names the layout the pages extend: the application's own, which defines `title` and `content`.

## Footer links

```twig
{{ include('@PhilLegalNotices/_links.html.twig') }}
```

`phil_legal_notices_links()` returns, for each document with a version in force, then for each policy of
`footer.terms_policies`, an `url`, a `label` translation key and its `domain`. The terms pages of phil/terms-bundle
link back to the documents when their layout includes the same template.
