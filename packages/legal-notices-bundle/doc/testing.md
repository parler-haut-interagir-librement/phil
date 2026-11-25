# Testing

| Area | What is covered |
|---|---|
| `PhilLegalNoticesBundle`, `LegalNoticesServices`, `KitSubdirectoryPass`, `IntlExtensionPass` | The defaults, every option, the sources of the application, files and parameters, every refused configuration; the subdirectory of the recipes from the configuration, the Toolkit or neither; the extension of twig/intl-extra registered, then removed when another extension of its class is registered, by its id, its class or a parameter, before the pass of TwigBundle that adds the extensions |
| `ConfigDocumentSource` | The versions of the configuration, the shapes of a date, refused versions |
| `MarkupBody`, `ScheduleRetentionDurations`, `ConfiguredConsentCategories` | Markdown and HTML purified, an unknown format, the rules of a schedule, the categories |
| `DocumentController` | The version in force, a version, the history, the sections of the privacy policy, what is not found |
| `FooterLinks`, `LegalNoticesExtension`, `KitComponents`, the commands | The links, the Twig functions as Twig declares them from their attributes, the names of the recipes, the check failing and passing, the announcements and their span |
| `LegalUrlProvider` | The pages of the documents in force, by section |
| The templates | No text outside Twig; every translation key they name in English and French, with the same keys in both catalogues |
| The Flex recipe | The manifest, the routes, the configuration loaded by the bundle: two drafts with bodies in English and French that name only fields of the identity and every field their document requires, refused once published without the identity, built once it is filled in |
| A kernel | TwigComponentBundle with the recipes of the PHIL UX kit, or of the Shadcn kit of the Symfony UX Toolkit, copied where `ux:install` puts them, in a subdirectory of the components or at their root: the badges and the table of retention periods drawn by the recipes, every page in each locale rendered with each kit; the pages in English and French under a prefix per locale, a body read from a Markdown file, the retention periods of a schedule of phil/privacy-bundle, the consent categories and their cookies as a list in each language, with and without TwigExtraBundle and its intl part, the history, the 404s, the check |

```sh
vendor/bin/phpunit packages/legal-notices-bundle/tests
```
