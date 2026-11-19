# Reference

| Type | Role |
|---|---|
| `PhilLicensingBundle` | The bundle, its configuration and its services. |
| `Domain\Catalog\LicenseCatalog` | The built-in licenses and those of the application. |
| `Domain\Catalog\Requirement` | What a license requires. |
| `Domain\Catalog\ResourceKind` | What an attributed resource is. |
| `Domain\Compliance\ViolationCode` | Why a work cannot be published as declared. |
| `Domain\Contract\Licensable` | An object of the application that stands for its subject. |
| `Domain\Contract\LicenseDeclarationRepository` | The store of the declarations. |
| `Domain\Exception\LicensingException` | Every exception of the bundle. |
| `Domain\Finder\CustomTerms` | The clauses of a custom license. |
| `Domain\Finder\LicenseChoices` | The answers given to the license finder. |
| `Domain\Finder\LicenseFinder` | The decision tree of the license finder. |
| `Domain\Finder\Modifications` | What others may do with adaptations. |
| `Domain\Finder\Question` | A question of the license finder. |
| `Domain\Finder\Recommendation` | The license the finder recommends. |
| `Domain\Finder\TermsClause` | A clause of custom terms. |
| `Domain\Exception\InvalidLicensing` | A value refused. |
| `Domain\Exception\UnknownLicense` | A code the catalog does not hold. |
| `Domain\Model\LicenseDeclaration` | The license of a subject, its declarations and attributions; an entity. |
| `Domain\Text` | The checks of the texts. |
| `Domain\ValueObject\Attribution` | The credit of one resource. |
| `Domain\ValueObject\ComplianceReport` | The violations found. |
| `Domain\ValueObject\License` | A license of the catalog. |
| `Domain\ValueObject\SubjectReference` | A subject by type and identifier. |
| `Domain\ValueObject\Violation` | One violation. |
| `Application\AttributedResource` | A resource of the page with its license. |
| `Application\AttributionPage` | What the license and attribution page shows. |
| `Application\Event\ExportChecking` | Dispatched on each check of the export guard. |
| `Application\Exception\ExportBlocked` | An export refused. |
| `Application\Exception\PrintSubjectRequired` | A print that names no work, refused by the strict printing. |
| `Application\ExportGuard` | The check before an export. |
| `Application\LicenseCompliance` | The compliance check. |
| `Infrastructure\Doctrine\DoctrineLicenseDeclarations` | The declarations through Doctrine. |
| `Infrastructure\Security\ExportVoter` | `PHIL_LICENSING_EXPORT`. |
| `Infrastructure\Form\AttributionTransformer` | Between an `Attribution` and its fields. |
| `Infrastructure\Form\AttributionType` | The credit of one resource as a form. |
| `Infrastructure\Form\Finder\LicenseFinderData` | The answers of the license finder's form. |
| `Infrastructure\Form\Finder\LicenseFinderStepType` | One question of the license finder. |
| `Infrastructure\Form\Finder\LicenseFinderType` | The license finder as a form in steps. |
| `Infrastructure\PagedMedia\GuardPrintedWork` | The export guard before a print of `phil/paged-media-bundle`. |
| `Infrastructure\PagedMedia\RequirePrintSubject` | The strict printing. |
| `Infrastructure\StructuredData\LicenseStructuredData` | The licensing as schema.org properties. |
| `Infrastructure\Translation\CustomTermsWriter` | The text of custom terms, translated. |
| `Infrastructure\Translation\LicenseNames` | The names of the licenses and the messages, translated. |
| `Infrastructure\Twig\AttributionRenderer` | The page as an HTML fragment. |
| `Infrastructure\Twig\FinderExtension` | The Twig functions of the license finder. |
| `Infrastructure\Twig\LicensingExtension` | The Twig functions. |
| `Infrastructure\Ujo\AttributionDocument` | The page as a UJO (Universal JSON Output) document. |
