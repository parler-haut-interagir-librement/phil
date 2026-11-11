# Testing

| Area | What is covered |
|---|---|
| `AcceptedUrlTypes` | Every form of accepted kinds, merging by enum, refusals |
| `TypedUrlType`, `TypedUrlTransformer` | Detection, the default protocol, accepted kinds only, an empty field |
| `UrlOfType` | Texts, addresses and typed addresses of accepted kinds or not, invalid texts, messages and codes |
| `UrlColumnType`, `UrlExtension`, `PhilUrlBundle` | The column, the Twig helpers, the services and the prepended column type |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on SQLite, TwigBundle and the bundle:
`UrlBundleTest` stores an address, validates in `fr_CA` and renders the Twig helpers. Installed on its own, where
FrameworkBundle is a development requirement, the suite is skipped.

```sh
vendor/bin/phpunit packages/url-bundle/tests
```
