<!-- phil-dev source-sha256=d8cfdf61014df35e613936646f0e4ac51af2456e90e70471719365026004db83 -->
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

| Clé | Valeur par défaut | Rôle |
|---|---|---|
| `repository` | `null` | Le stockage des déclarations. Sans lui et sans DoctrineBundle, la garde d'exportation, la page et le voter ne sont pas enregistrés. |
| `template` | `@PhilLicensing/attribution.html.twig` | Le gabarit dont `AttributionRenderer` produit le rendu, avec `page` et `locale`. |
| `printing.strict` | `false` | Refuse une impression de `phil/paged-media-bundle` qui ne nomme aucune œuvre ([Impression](printing.md)). |
| `printing.unlicensed_subjects` | `[]` | Les types de sujets imprimés sans vérification, sans licence. |
| `licenses.<code>.name` | requis | Le nom affiché quand `license.<code>` n'est pas traduit. |
| `licenses.<code>.url` | `null` | Une adresse absolue http ou https du texte de la licence. |
| `licenses.<code>.reference` | `null` | L'endroit où le texte est enregistré ou publié, quand aucune adresse ne le donne. |
| `licenses.<code>.spdx` | `null` | L'identifiant SPDX. |
| `licenses.<code>.requirements` | `[]` | Des valeurs de `Requirement` : `attribution`, `license_notice`, `full_text`, `copyright_notice`, `share_alike`, `indicate_changes`, `reserved_material`, `product_identity`, `designated_content`, `non_commercial`, `no_derivatives`. |

Un code compte de 1 à 64 lettres minuscules, chiffres, points et traits d'union, et ne peut pas être un code intégré.
Une licence que le catalogue refuse fait échouer la construction du conteneur.

## Les services

| Service | Public | Enregistré |
|---|---|---|
| `LicenseCatalog`, `LicenseCompliance`, `LicenseNames`, `LicenseFinder`, `CustomTermsWriter` | oui | toujours |
| `AttributionType`, `LicenseFinderStepType`, `LicenseFinderType` | non | avec `symfony/form` et `symfony/validator` |
| `FinderExtension` | non | avec TwigBundle |
| `RequirePrintSubject` | non | avec `phil/paged-media-bundle` et `printing.strict` |
| `AttributionDocument` | oui | avec `phil/ujo` |
| `LicenseStructuredData` | oui | avec `phil/structured-data` |
| `LicenseDeclarationRepository` (alias) | oui | avec DoctrineBundle ou `repository` |
| `ExportGuard` | oui | avec un dépôt |
| `AttributionRenderer` | oui | avec un dépôt et TwigBundle |
| `LicensingExtension` | non | avec un dépôt et TwigBundle |
| `GuardPrintedWork` | non | avec un dépôt et `phil/paged-media-bundle` |
| `ExportVoter` | non | avec un dépôt et SecurityBundle |

Chaque service est sans état : un *worker* sert les requêtes les unes après les autres avec les mêmes instances.
