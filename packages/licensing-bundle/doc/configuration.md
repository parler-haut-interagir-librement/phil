# Configuration

```yaml
# config/packages/phil_licensing.yaml
phil_licensing:
    # Service id of a LicenseDeclarationRepository; the Doctrine one when DoctrineBundle is registered.
    repository: ~
    # The Twig template of the license and attribution page, a fragment.
    template: '@PhilLicensing/attribution.html.twig'
    # The guard of the prints of phil/paged-media-bundle.
    printing:
        # Refuses a print that names no work with a PrintSubject, whose licensing cannot be checked.
        strict: false
        # The subject types printed without a license: invoices, tickets, receipts.
        unlicensed_subjects: []
    # The licenses of the application, after the built-in ones, by code.
    licenses:
        house:
            name: House license
            url: https://example.com/license
            reference: ~
            spdx: LicenseRef-House
            requirements: [attribution, license_notice]
```

| Key | Default | What it does |
|---|---|---|
| `repository` | `null` | The store of the declarations. Without it and without DoctrineBundle, the export guard, the page and the voter are not registered. |
| `template` | `@PhilLicensing/attribution.html.twig` | The template `AttributionRenderer` renders, with `page` and `locale`. |
| `printing.strict` | `false` | Refuses a print of `phil/paged-media-bundle` that names no work ([Printing](printing.md)). |
| `printing.unlicensed_subjects` | `[]` | The subject types printed unchecked, without a license. |
| `licenses.<code>.name` | required | The name shown when `license.<code>` is not translated. |
| `licenses.<code>.url` | `null` | An absolute http or https address of the license text. |
| `licenses.<code>.reference` | `null` | Where the text is registered or published, when no address gives it. |
| `licenses.<code>.spdx` | `null` | The SPDX identifier. |
| `licenses.<code>.requirements` | `[]` | Values of `Requirement`: `attribution`, `license_notice`, `full_text`, `copyright_notice`, `share_alike`, `indicate_changes`, `reserved_material`, `product_identity`, `designated_content`, `non_commercial`, `no_derivatives`. |

A code is 1 to 64 lowercase letters, digits, dots and hyphens, and may not be a built-in code. A license the catalog
refuses fails the container build.

## The services

| Service | Public | Registered |
|---|---|---|
| `LicenseCatalog`, `LicenseCompliance`, `LicenseNames`, `LicenseFinder`, `CustomTermsWriter` | yes | always |
| `AttributionType`, `LicenseFinderStepType`, `LicenseFinderType` | no | with `symfony/form` and `symfony/validator` |
| `FinderExtension` | no | with TwigBundle |
| `RequirePrintSubject` | no | with `phil/paged-media-bundle` and `printing.strict` |
| `AttributionDocument` | yes | with `phil/ujo` |
| `LicenseStructuredData` | yes | with `phil/structured-data` |
| `LicenseDeclarationRepository` (alias) | yes | with DoctrineBundle or `repository` |
| `ExportGuard` | yes | with a repository |
| `AttributionRenderer` | yes | with a repository and TwigBundle |
| `LicensingExtension` | no | with a repository and TwigBundle |
| `GuardPrintedWork` | no | with a repository and `phil/paged-media-bundle` |
| `ExportVoter` | no | with a repository and SecurityBundle |

Every service is stateless: a worker serves request after request with the same instances.
