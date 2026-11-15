# Public pages

The routes of `config/routes/pages.php`, under the application's prefix:

| Route | Path | Page |
|---|---|---|
| `phil_terms_policy` | `/{policy}` | The version in force — or, before any takes effect, the latest |
| `phil_terms_version` | `/{policy}/v{number}` | One version, at an address that never changes |
| `phil_terms_changes` | `/{policy}/v{number}/changes/{from}` | The changes from an older version to a newer one |
| `phil_terms_accept` | `/accept` | The acceptance page of a signed-in member |

Each page of a policy lists its versions with the moment each takes effect, which ones are material, and a link to the
changes from the version before. A version not yet in force says when it takes effect; a version replaced says so. An
undeclared policy, an unknown version, or changes that do not go from an older version to a newer one are a `404`.

## Changes

With `phil/text-diff`, the changes are those of the two versions' HTML, in the request's locale: what was added is in
`<ins>`, what was removed in `<del>`, and the paragraphs keep their structure. Without it, the changes are a `404`.

## Cache and session

The page of the version in force is sent with `Cache-Control: public, max-age=300`, a versioned page and its changes
with `public, max-age=86400`. These pages open no session and carry no token; the acceptance page carries the token of
its form and is never kept by a cache.

## The recipes of the kit

The templates draw their parts with recipes of the kit, installed in the application by the Symfony UX Toolkit from
the PHIL UX kit or from the Shadcn kit ([Installation](installation.md#install-the-recipes-of-the-kit)):

| Page | Recipes |
|---|---|
| A policy | `Badge`: the standing of the version — in force, not yet, replaced — and the mark of a material version |
| The changes | `Button`, as links to the two versions |
| The acceptance page | `Alert` when a box is left unticked; `Field`, `Checkbox` and `Field:Label` for each version; `Button` |

They are called by the names `phil_terms_kit()` gives — `ui:Badge` when the recipes are in the subdirectory `ui` —
and pass only the props both kits share: the headings, the lists and the links of the versions are the templates' own.
The tests render every page with each kit.

## Layout and words

The pages extend `pages.layout`, a template that defines the `title` and `content` blocks. No template writes a word
of its own: every word is a message of the `phil_terms` domain. The title of a policy is the
message `policy.<key>` of the `phil_terms` domain: the bundle translates `terms`, `privacy` and `code_of_conduct` in
English and French, the application the others. Every template can be overridden in
`templates/bundles/PhilTermsBundle/`.

The legal links of `phil/legal-notices-bundle` belong to the layout of the application, which includes
`@PhilLegalNotices/_links.html.twig`; `phil:terms:check-legal-links` confirms that they are there
([Console](console.md)).
