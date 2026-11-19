# Testing

The bundle's Unit suite runs without a kernel: the catalog, the texts, the declarations and their mapping, the
compliance check, the export guard and its event, the page, the Twig functions on a real Twig environment with the
bundle's translations, the UJO document read back by `phil/ujo`, the JSON-LD, the voter, the Doctrine repository on
an SQLite database in memory, the container configuration, the strict printing, the attribution form and the license
finder — its decision tree for every combination, its form flow step by step. The Functional suite boots an
application with FrameworkBundle and its forms, DoctrineBundle, TwigBundle and SecurityBundle, and another that prints
with `phil/paged-media-bundle` under the strict printing. Every mutant of the production code is killed.

```sh
composer test
composer infection:changed
```

## Testing an application

An in-memory `LicenseDeclarationRepository` — an array of declarations by subject — stands for the table in unit
tests; `ExportGuard` and `LicenseCompliance` are plain objects built with a `LicenseCatalog`. In a kernel test, create
the table with Doctrine's `SchemaTool`, then check the export guard and the page through the container.
