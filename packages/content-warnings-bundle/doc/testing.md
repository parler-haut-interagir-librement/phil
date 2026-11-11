# Testing

| Area | What is covered |
|---|---|
| `CatalogueFactory`, `CatalogueLabels`, `WarningScreen` | The base list, files and definition in order, labels by locale and fallbacks, choices, decisions |
| `SessionWarningPreferences`, `InMemoryWarningPreferences` | Saving and reading, the main request, no session, unreadable preferences, resetting |
| `ContentWarningsColumnType` | JSON, null and empty, every refusal |
| `ContentWarningsType`, `WarningPreferencesType` and their transformers | Choices by category and locale, kept severities, notes and unlisted themes, options |
| `ContentWarningsExtension`, `PhilContentWarningsBundle` | The functions; the services, the configuration, a broken catalogue, the prepended configuration |
| The Stimulus controller | Events, revealing with and without reduced motion, concealing, under Node's test runner |

A functional suite boots a kernel with FrameworkBundle, DoctrineBundle on SQLite, TwigBundle and this bundle:
`ContentWarningsBundleTest` stores warnings, renders the template folded, hidden and shown, and the forms in `fr_CA`.
Installed on its own, where FrameworkBundle is a development requirement, the suite is skipped.

```sh
vendor/bin/phpunit packages/content-warnings-bundle/tests
node --import ./tests/js/register.mjs --test tests/js   # from packages/content-warnings-bundle
```
