# Testing

## The bundle's tests

`tests/Unit` checks the configuration and the services it gives, the mapping of the controller and the stylesheet,
and both filters: plain text escaped, HTML trusted only as `Markup`, sanitized or escaped otherwise, documents read and
rendered by the application's UJO services. `tests/Functional` boots a small application — FrameworkBundle with
AssetMapper and an HTML sanitizer, TwigBundle, `phil/ujo-bundle` and this bundle —, renders a template with both
filters, and finds the controller and the stylesheet through AssetMapper. `tests/js` checks the controller and the
decisions it makes without the DOM.

From the monorepo root:

```sh
vendor/bin/phpunit packages/text-diff-bundle/tests
(cd packages/text-diff-bundle && node --import ./tests/js/register.mjs --test tests/js)
```

The bundle is held to 100 % covered mutation score by Infection, like the library.

## Testing an application

The output of the filters is deterministic: a functional test asserts on the rendered HTML, or counts the `ins` and
`del` of a page with a crawler. A test of the controller's behaviour runs in a browser — Panther or Playwright — and
checks `data-text-diff-mode`, `aria-pressed`, the counter's text and the element marked `text-diff-current`.
