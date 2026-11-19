# phil/licensing-bundle

**The license a work is published under, what that license asks, and the page that gives it: declared, checked, and
shown in HTML, UJO (Universal JSON Output) or JSON-LD.**

phil/licensing-bundle keeps the license of a work or a digital product — a book, a set of rules, a product of a shop —
and the credit of every resource the work uses: the images, the maps, the fonts, the text sources such as a system
reference document. Its catalog knows the ORC License, the six Creative Commons 4.0 licenses, CC0 1.0, the Open Game
License 1.0a and custom terms, each with what it requires: the credit of the creators, a
notice naming the license, its full text, the copyright notices, share-alike, the Reserved Material, the Product
Identity, the designation of the open content. An application adds its own licenses.

It is for any Symfony application that publishes works built on licensed material and must say so: attach a
`LicenseDeclaration` to the subject by its type and identifier, list the attributions, and the bundle generates the
license and attribution page, checks the declaration against the licenses, and refuses an export that has no license
or misses what a license requires — with the strict printing, a print that names no work too. An attribution form asks
for the changes a license wants indicated, and a license finder, in steps and without JavaScript, chooses the license
from what the author allows or writes custom terms.

The obligations of each license are summarized from its official text, never quoted; the bundle ships no license
text. When a license asks for its text to go with the work, the declaration holds it, as the application provides it.

## Status

| Part | State |
|---|---|
| The catalog: ORC, CC BY, BY-SA, BY-NC, BY-NC-SA, BY-ND and BY-NC-ND 4.0, CC0 1.0, OGL 1.0a, custom terms, licenses of the application | Implemented and tested |
| `LicenseDeclaration`, attached to any subject, with its attributions, in the table `phil_licensing_declaration` | Implemented and tested |
| `LicenseCompliance`, the violations of a declaration | Implemented and tested |
| `ExportGuard`, its `ExportChecking` event and the voter `PHIL_LICENSING_EXPORT` | Implemented and tested |
| The export guard before each print of `phil/paged-media-bundle` that names its work, unlicensed subjects | Implemented and tested |
| The strict printing, off by default: a print that names no work refused | Implemented and tested |
| `AttributionType`, the changes required by the license, "No modifications" | Implemented and tested |
| `LicenseFinder`, the decision tree, and its custom terms | Implemented and tested |
| `LicenseFinderType`, the license finder in steps on the form flow of Symfony | Implemented and tested |
| Community content programs: a license offered to creators, revenue sharing | Not started ([Scope](doc/scope.md)) |
| The license and attribution page as an HTML fragment, in English and French | Implemented and tested |
| The page as a UJO (Universal JSON Output) document, with `phil/ujo` | Implemented and tested |
| The licensing of a work as JSON-LD, with `phil/structured-data` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering, the table, the optional packages. |
| [Configuration](doc/configuration.md) | Every key, the licenses of the application, the services. |
| [Licenses](doc/licenses.md) | The catalog, what each license requires and where it comes from. |
| [Declarations](doc/declarations.md) | Declaring a license, the attributions, the limits, Doctrine. |
| [The attribution page](doc/attribution-page.md) | The HTML fragment, the Twig functions, the UJO document, the JSON-LD. |
| [Compliance](doc/compliance.md) | The checks, the violations, the export guard, its event and its voter. |
| [Printing](doc/printing.md) | The guard of the prints, the unlicensed subjects, the strict printing. |
| [The attribution form](doc/attribution-form.md) | The credit of a resource as a form, the changes and "No modifications". |
| [The license finder](doc/license-finder.md) | The decision tree, the custom terms, the form in steps, the templates. |
| [Scope](doc/scope.md) | What the bundle does, and what stays out of it. |
| [Extension points](doc/extension-points.md) | What an application implements, replaces or listens to. |
| [Testing](doc/testing.md) | How the bundle is tested, and how to test an application with it. |
| [Reference](doc/reference.md) | Every type of the bundle. |

## Quick start

```sh
composer require phil/licensing-bundle
```

```php
use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Catalog\ResourceKind;
use Phil\LicensingBundle\Domain\Model\LicenseDeclaration;
use Phil\LicensingBundle\Domain\ValueObject\Attribution;
use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;

$declaration = LicenseDeclaration::declare(SubjectReference::of('book', $book->getId()), $catalog->get(LicenseCatalog::ORC), $now);
$declaration->describe('© 2026 Example Press', 'The names, maps and art of the setting.', null, null, $now);
$declaration->attribute([
    Attribution::of(ResourceKind::Text, 'Example Reference Document', LicenseCatalog::ORC, 'Example Games'),
    Attribution::of(ResourceKind::Image, 'Cover', LicenseCatalog::CC_BY, 'Jane Doe', 'https://example.com/cover', '© 2024 Jane Doe', 'Cropped'),
], $now);
$declarations->add($declaration);   // LicenseDeclarationRepository; the entity manager flushes it

$exportGuard->assertExportable(SubjectReference::of('book', $book->getId()));   // ExportBlocked otherwise
```

```twig
{{ phil_licensing_attribution(book) }}  {# a Licensable, a SubjectReference or a declaration #}
```

## Requirements

PHP 8.5 or later with `ext-mbstring`, `phil/url`, Symfony 8.2 or later, Doctrine ORM 3.7 and Twig 3.21;
DoctrineBundle for the table, TwigBundle for the page, `symfony/translation` for English and French, `phil/ujo` for
the UJO document, `phil/structured-data` for the JSON-LD, SecurityBundle for the voter, `phil/paged-media-bundle` for
the guard of the prints, `symfony/form` and `symfony/validator` for the forms.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
