# Testing

## The bundle's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
node --test tests/js
```

`tests/Unit` checks the configuration and its services, the compiler pass, the form extension, the preview's
answers and checks, and the background pass. `tests/Functional` boots a small application — forms, router,
Messenger, AssetMapper, an HTTP client standing for the Mermaid service — and submits documents, formulas with macros,
colours, numbers and chemistry among them, renders them, previews blocks with the stateless CSRF token, serves the
controller through AssetMapper, and runs the background pass, the style of a diagram kept and rewritten. `tests/js`
runs the controller's pure modules, and the Mermaid service's `lib.mjs` — its limiter, deadline, body limit,
configuration of Mermaid and Chromium —, under Node's test runner. In the monorepo, Infection holds the PHP at 100 % of covered mutants.

The server of the Mermaid service is checked against Mermaid through `phil/svg-blocks`' integration test, at
`PHIL_MERMAID_URL`, once the image is built and running.

## Testing an application

Configure no `mermaid.url` in the test environment, or point the `http_client` at a mock response factory; give
`mermaid.documents` an in-memory implementation; run Messenger's transport synchronously.
