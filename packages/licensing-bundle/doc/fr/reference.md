<!-- phil-dev source-sha256=a7feedffbb9640a2a9b1684c2d773757b261c2e10246f81e132e17706e2d1510 -->
# Référence

| Type | Rôle |
|---|---|
| `PhilLicensingBundle` | Le bundle, sa configuration et ses services. |
| `Domain\Catalog\LicenseCatalog` | Les licences intégrées et celles de l'application. |
| `Domain\Catalog\Requirement` | Ce qu'une licence exige. |
| `Domain\Catalog\ResourceKind` | La nature d'une ressource attribuée. |
| `Domain\Compliance\ViolationCode` | La raison pour laquelle une œuvre ne peut pas être publiée telle que déclarée. |
| `Domain\Contract\Licensable` | Un objet de l'application qui tient lieu de son sujet. |
| `Domain\Contract\LicenseDeclarationRepository` | Le stockage des déclarations. |
| `Domain\Exception\LicensingException` | Toutes les exceptions du bundle. |
| `Domain\Finder\CustomTerms` | Les clauses d'une licence personnalisée. |
| `Domain\Finder\LicenseChoices` | Les réponses données à l'assistant de licence. |
| `Domain\Finder\LicenseFinder` | L'arbre de décision de l'assistant de licence. |
| `Domain\Finder\Modifications` | Ce que d'autres peuvent faire des adaptations. |
| `Domain\Finder\Question` | Une question de l'assistant de licence. |
| `Domain\Finder\Recommendation` | La licence que recommande l'assistant. |
| `Domain\Finder\TermsClause` | Une clause de conditions personnalisées. |
| `Domain\Exception\InvalidLicensing` | Une valeur refusée. |
| `Domain\Exception\UnknownLicense` | Un code absent du catalogue. |
| `Domain\Model\LicenseDeclaration` | La licence d'un sujet, ses déclarations et ses attributions ; une entité. |
| `Domain\Text` | Les vérifications des textes. |
| `Domain\ValueObject\Attribution` | Le crédit d'une ressource. |
| `Domain\ValueObject\ComplianceReport` | Les violations trouvées. |
| `Domain\ValueObject\License` | Une licence du catalogue. |
| `Domain\ValueObject\SubjectReference` | Un sujet par type et identifiant. |
| `Domain\ValueObject\Violation` | Une violation. |
| `Application\AttributedResource` | Une ressource de la page avec sa licence. |
| `Application\AttributionPage` | Ce que montre la page de licence et d'attribution. |
| `Application\Event\ExportChecking` | Réparti à chaque vérification de la garde d'exportation. |
| `Application\Exception\ExportBlocked` | Une exportation refusée. |
| `Application\Exception\PrintSubjectRequired` | Une impression qui ne nomme aucune œuvre, refusée par l'impression stricte. |
| `Application\ExportGuard` | La vérification avant une exportation. |
| `Application\LicenseCompliance` | La vérification de conformité. |
| `Infrastructure\Doctrine\DoctrineLicenseDeclarations` | Les déclarations par Doctrine. |
| `Infrastructure\Security\ExportVoter` | `PHIL_LICENSING_EXPORT`. |
| `Infrastructure\Form\AttributionTransformer` | Entre une `Attribution` et ses champs. |
| `Infrastructure\Form\AttributionType` | Le crédit d'une ressource sous forme de formulaire. |
| `Infrastructure\Form\Finder\LicenseFinderData` | Les réponses du formulaire de l'assistant de licence. |
| `Infrastructure\Form\Finder\LicenseFinderStepType` | Une question de l'assistant de licence. |
| `Infrastructure\Form\Finder\LicenseFinderType` | L'assistant de licence sous forme de formulaire par étapes. |
| `Infrastructure\PagedMedia\GuardPrintedWork` | La garde d'exportation avant une impression de `phil/paged-media-bundle`. |
| `Infrastructure\PagedMedia\RequirePrintSubject` | L'impression stricte. |
| `Infrastructure\StructuredData\LicenseStructuredData` | La licence sous forme de propriétés schema.org. |
| `Infrastructure\Translation\CustomTermsWriter` | Le texte des conditions personnalisées, traduit. |
| `Infrastructure\Translation\LicenseNames` | Les noms des licences et les messages, traduits. |
| `Infrastructure\Twig\AttributionRenderer` | La page sous forme de fragment HTML. |
| `Infrastructure\Twig\FinderExtension` | Les fonctions Twig de l'assistant de licence. |
| `Infrastructure\Twig\LicensingExtension` | Les fonctions Twig. |
| `Infrastructure\Ujo\AttributionDocument` | La page sous forme de document UJO (Universal JSON Output). |
