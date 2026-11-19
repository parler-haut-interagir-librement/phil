# Extension points

What an application implements, replaces or listens to.

## The licenses

`phil_licensing.licenses` adds licenses to the catalog, with their requirements ([Configuration](configuration.md));
`license.<code>` of the domain `phil_licensing` translates their names.

## The store

`Domain\Contract\LicenseDeclarationRepository` is aliased to `DoctrineLicenseDeclarations`. An application that keeps
the declarations elsewhere names its service in `phil_licensing.repository`: `declarationOf()`, `add()` and
`remove()`.

## The subjects

`Domain\Contract\Licensable` lets an object of the application stand for its subject wherever the bundle takes one:
the export guard, the voter, the renderer and the Twig functions.

## The export

`Application\Event\ExportChecking` is dispatched on every check of the export guard; its listeners add violations
([Compliance](compliance.md)). `ExportGuard` itself is a public service an application calls from a controller, a
message handler or a command; `Infrastructure\PagedMedia\GuardPrintedWork` calls it before a print of
`phil/paged-media-bundle` that names its work. `phil_licensing.printing.unlicensed_subjects` names the subject types
printed without a license, and `phil_licensing.printing.strict` refuses a print that names no work
([Printing](printing.md)).

## The page

`phil_licensing.template` names another template, rendered with `page`, an `AttributionPage`, and `locale`; the
bundle's template is replaced at `templates/bundles/PhilLicensingBundle/attribution.html.twig`. The translation domain
`phil_licensing` takes other languages and other sentences. `AttributionPage` is the model of a page built otherwise.

## The forms

`AttributionType` and `LicenseFinderType` are form types of the application's form factory, extended by type
extensions and rendered by its form themes: the block prefixes are `phil_licensing_attribution` and
`phil_licensing_finder`. `LicenseFinderType` takes any data storage of the form flow in its option `data_storage`.

## The license finder

`Domain\Finder\LicenseFinder` is a public service an application calls with its own form, a command or an API;
`LicenseFinderData::choices()` turns the answers of the bundle's form into its `LicenseChoices`. The share-alike
licenses of the application are offered as adapted material. The templates `finder/form.html.twig` and
`finder/recommendation.html.twig` are replaced under `templates/bundles/PhilLicensingBundle/finder/`, and the clauses
of custom terms are sentences of the domain `phil_licensing`, `terms.<clause>`.
