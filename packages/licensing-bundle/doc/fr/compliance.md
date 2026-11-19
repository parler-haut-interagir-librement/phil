<!-- phil-dev source-sha256=6dea5b67c45fc93046cb87198f26cd137f23f5c008f6e877969bc5201eae07e2 -->
# Conformité

`LicenseCompliance::check($declaration)` retourne un `ComplianceReport` : aucune violation, ou chaque `Violation` dans
l'ordre de découverte, avec son `ViolationCode`, la licence concernée et la ressource par sa position et son titre.

| Code | Quand |
|---|---|
| `no_declaration` | Le sujet n'a pas de déclaration (la garde d'exportation seulement). |
| `unknown_license` | L'œuvre ou une ressource nomme un code absent du catalogue. |
| `missing_license_text` | La licence demande son texte intégral, ou n'a ni adresse ni référence, et il n'y a aucun texte de licence. |
| `missing_notice` | La licence demande l'avis de droit d'auteur de l'œuvre. |
| `missing_reserved_material` | La licence demande le Reserved Material. |
| `missing_product_identity` | La licence demande la Product Identity. |
| `missing_designated_content` | La licence demande que le contenu ouvert soit identifié. |
| `missing_credit` | La licence d'une ressource demande un crédit, et l'attribution n'a ni auteur ni mention. |
| `missing_resource_notice` | La licence d'une ressource demande de conserver sa mention, et l'attribution n'en a aucune. |
| `missing_changes` | La licence d'une ressource demande d'indiquer les modifications, et l'attribution n'en dit rien ; une ressource inchangée le précise. |
| `share_alike_conflict` | Une ressource est sous une licence de partage à l'identique autre que celle de l'œuvre. |

`LicenseNames::violation($violation, $locale)` et `phil_licensing_violation()` donnent le message, `violation.<code>` du
domaine `phil_licensing`, la licence étant nommée dans la langue du lecteur. Ce que la vérification ne peut pas voir,
c'est-à-dire que les mentions et les modifications indiquées sont vraies, reste la responsabilité de l'éditeur.

## La garde d'exportation

Une application appelle `ExportGuard` avant d'exporter une œuvre : un PDF, un EPUB, un fichier d'impression.

```php
$exportGuard->assertExportable($book);   // throws ExportBlocked, with ->subject and ->report

$report = $exportGuard->check($book);    // or reads the report
```

Un sujet sans déclaration est refusé avec `no_declaration`. Après la vérification, la garde répartit `ExportChecking`
par le répartiteur PSR-14 : un écouteur ajoute les violations des règles propres à l'application, et celles-ci bloquent
l'exportation comme les autres.

```php
#[AsEventListener]
final class RequireAttributionPage
{
    public function __invoke(ExportChecking $event): void
    {
        if (!$this->layout->includesAttributionPage($event->subject)) {
            $event->addViolation(new Violation(ViolationCode::MissingCredit, resourceTitle: 'Attribution page'));
        }
    }
}
```

## L'impression

Lorsque `phil/paged-media-bundle` est installé, la garde s'exécute avant chaque impression qui nomme son œuvre, et
l'impression stricte refuse une impression qui n'en nomme aucune : voir [Impression](printing.md).

## Le voter

Avec SecurityBundle, `is_granted('PHIL_LICENSING_EXPORT', subject)` est refusé tant que la garde trouve une violation,
le code de chaque violation étant une raison du vote. Il ne dit rien de qui peut exporter : les voters de l'application
en décident, et la stratégie de décision d'accès les combine.
