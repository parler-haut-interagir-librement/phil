# The page

`config/routes.php` declares two localized routes:

| Route | English | French |
|---|---|---|
| `phil_accessibility_statement` | `/accessibility` | `/accessibilite` |
| `phil_accessibility_statement_version` | `/accessibility/v{version}` | `/accessibilite/v{version}` |

The first shows the version in force, the second each version at an address that never changes. A route answers
`GET` and `HEAD`; a version that does not exist, or a site without any, answers 404. A locale prefix, a host or other
paths are the application's: import the file with a `prefix`, or declare its own routes to
`StatementController::current()` and `version()`.

## What the page shows

1. The commitment and what the statement measures, in the wording chosen.
2. The conformance status, with the target and a link to the recommendation of the W3C.
3. The content that is not accessible, grouped by reason — criteria not met, disproportionate burden, content outside
   the scope —, each a card in a list item, headed by the number, title and level of its criterion, with its
   description, the occurrences an audit found, the alternative and the date it will be met.
4. The alternatives.
5. The feedback channels: a button to the form of `feedback_form` — the page of a form of `phil/forms-bundle` — or
   else to the page of `feedback_route`, then the feedback page, the email, the telephone as a `tel:` link, the postal
   address, and the days within which an answer comes.
6. The enforcement procedure and the authority, linked to its page.
7. How the statement was prepared: its version and publication, the last evaluation, its method, the pages evaluated
   and its report.
8. The versions, when there are several, in a table: the one shown marked `aria-current="page"` and the newest marked
   in force by a badge.

Each section is a `<section>` labelled by its heading, the list of versions a `<nav>`. The page carries no script and
no style of its own: the recipes of the kit carry their classes, and the stylesheet of the kit, loaded by the layout,
gives them their look.

## The recipes of the kit

The templates draw their parts with recipes of the PHIL UX kit — or of the Shadcn kit —, installed in the application
by the Symfony UX Toolkit; nothing is rendered from `vendor/`:

```sh
php bin/console ux:install card --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install badge --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install table --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
# for the administration page
php bin/console ux:install alert --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install field --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install textarea --kit=github.com/parler-haut-interagir-librement/ux-kit
```

| Template | Recipes |
|---|---|
| `statement.html.twig`, `_versions.html.twig` | `Card` with `Card:Header` and `Card:Content`; `Table` with its parts; `Badge`; `Button` |
| `admin.html.twig` | `Alert` with `Alert:Title` and `Alert:Description`; `Field` with `Field:Label` and `Field:Description`; `Textarea`; `Button`; the table above |

These recipes have the names, props and blocks of the Shadcn kit of the Toolkit, so a site on Tailwind installs them
from the Shadcn kit instead (`--kit=shadcn`), with `label` and `separator`, which its recipes call. The templates pass
only the props both kits share: the list item around each card, the heading of a criterion and the header cells' `scope`
are the templates' own, as the Shadcn card is a `div` and its title is not a heading; an invalid field says so by the
class `isDanger` and `data-invalid`, which each kit styles. The tests render the page and the administration page with
each kit, and check that no template writes a word of its own.

The components are found under the components subdirectory of the `ux_toolkit` configuration, as
`phil_accessibility_kit('Card:Content')` names them; in an environment where the Toolkit is not
enabled, as production often is, repeat it in the option `kit_subdirectory`. A template of the application's own calls
a recipe the same way: `{% component (phil_accessibility_kit('Badge')) with {variant: 'secondary'} %}`.

## Languages

The page is in English and French, from the translation domain `phil_accessibility_statement`; the titles of the
success criteria are the W3C's in English, and the bundle gives them in French. The texts of the
statement are shown in the request's language, else its primary language, else `default_locale`, else the first given.
Another language is added with a file `translations/phil_accessibility_statement.<locale>.php` (or `.yaml`, `.xlf`) of
the application, with the keys of the English file.

## Wording

| `wording` | For |
|---|---|
| `wcag` | Any site: the commitment and the measure against the Web Content Accessibility Guidelines. |
| `en-301-549` | A site under the European accessibility requirements: the harmonised standard EN 301 549, which applies WCAG to web content. |
| `aca` | A site under the Accessible Canada Act: the standard CAN/ASC-EN 301 549, and how feedback is acknowledged. |

Each wording is a template, `@PhilAccessibilityStatement/wording/<wording>.html.twig`, of sentences from the
translation domain. The wording does not replace legal advice: an organisation reads the obligations that apply to it
and writes its enforcement procedure in the statement.

## Overriding the templates

The layout is replaced with the `layout` option. The page itself, `statement.html.twig`, the table of versions,
`_versions.html.twig`, and the wordings are replaced the Symfony way, by a template of the same name under
`templates/bundles/PhilAccessibilityStatementBundle/`. The page receives `statement`, `groups` (the non-conformities by
reason), `versions`, `wording`, `feedback_route`, `feedback_form` and `layout`. Inside the block of a recipe, the
variables of the loop around it are not seen: set what the block needs before the `{% component %}` tag.

## The footer link

```twig
<footer>
    {{ phil_accessibility_statement_link() }}
    {{ phil_accessibility_statement_link('Accessibility statement', {class: 'footer-link'}) }}
    <a href="{{ phil_accessibility_statement_path() }}">…</a>
</footer>
```

`phil_accessibility_statement_link()` writes the link to the page in the request's language, its text translated
(`Accessibility`, `Accessibilité`) unless one is given, escaped, with the attributes given whose names are plain
(`class`, `data-…`, `aria-…`) — never `href`, never a name with a space or a capital, never a value that is not a
scalar. `phil_accessibility_statement_path()` gives the address alone, and the filter `|phil_accessibility_text` the
text of a `LocalizedText` in the request's language.

With `phil/legal-notices-bundle` installed, the statement also comes among the links of
`phil_legal_notices_links()`, once a version is published and the route imported: a footer that shows those links needs
no link of its own.
