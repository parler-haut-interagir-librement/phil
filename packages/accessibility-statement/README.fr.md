<!-- phil-dev source-sha256=ef9ceef5f863b1a562321ad32be32a39769c91d0d0fb8dc26b7b57f6446fb2c1 -->
# phil/accessibility-statement

**Dites à quel point un site est accessible, ce qui écarte encore des lecteurs et comment vous joindre, une version
datée à la fois.**

phil/accessibility-statement modélise une déclaration d'accessibilité : la version de WCAG et le niveau par rapport
auxquels elle mesure le contenu, par exemple WCAG 2.2 AA ; l'état de conformité revendiqué — total, partiel ou non
conforme — ; les critères de succès non respectés, chacun avec sa raison, ce qu'il signifie pour un lecteur, la solution
de rechange offerte entre-temps et la date à laquelle il sera respecté ; les solutions de rechange qui ne dépendent pas
de l'accessibilité du contenu ; le contact pour la rétroaction ; la procédure de recours ; et la dernière évaluation.
Une déclaration est publiée comme une version qui ne change jamais, et elle se vérifie elle-même : un état que les
non-conformités contredisent, un critère hors de la cible ou une évaluation datée après la publication est refusé.

Elle s'adresse à toute organisation qui publie une déclaration d'accessibilité et veut qu'elle reste vraie. Une
déclaration s'écrit à la main ou se lit dans une configuration ou une table. Sa version suivante se génère à partir des
rapports que `phil/accessibility-check` produit pour les pages du site — les critères qui échouent, à quelle fréquence
et quand l'audit a eu lieu —, tout en conservant ce que des personnes ont écrit de chaque critère.

C'est une simple bibliothèque PHP au-dessus de `phil/accessibility-check` et de l'extension `mbstring`.
`phil/accessibility-statement-bundle` fournit aux applications Symfony la page, ses versions, son texte en anglais et en
français, et la commande qui la régénère.

## État

| Partie | État |
|---|---|
| `Statement`, sa cible, son état, ses non-conformités, solutions de rechange, rétroaction, recours et évaluation, vérifiés | Implémenté et testé |
| WCAG 2.0, 2.1 et 2.2 aux niveaux A, AA et AAA, avec les critères que chacun mesure | Implémenté et testé |
| L'état que justifient les non-conformités | Implémenté et testé |
| `StatementReader` et `toArray()` : données simples et JSON, chaque clé vérifiée | Implémenté et testé |
| `StatementGenerator` : la version suivante à partir d'un audit | Implémenté et testé |
| `ReportReader` : relecture des rapports JSON de `phil/accessibility-check` | Implémenté et testé |
| Cibles de *fuzzing* des deux lecteurs | Implémenté et testé |
| Les titres français des critères de succès, tirés de la traduction autorisée du W3C (voir `data/NOTICE.md`) | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis et installation. |
| [Déclarations](doc/fr/statements.md) | Les parties d'une déclaration, la cible, les non-conformités, ce qui est refusé, l'état déduit, les textes et les adresses. |
| [Données simples](doc/fr/data.md) | `toArray()`, `StatementReader`, les clés, les moments et les textes, ce qui est refusé. |
| [Générer à partir d'un audit](doc/fr/generating.md) | `StatementGenerator`, le contenu de la version suivante, `ReportReader`, `AuditSource`. |
| [Points d'extension](doc/fr/extension-points.md) | Le dernier audit, la conservation des déclarations, le rendu, l'état. |
| [Exceptions](doc/fr/exceptions.md) | `InvalidStatement` et ses raisons, `InvalidReport`. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, le seuil de mutation et le *fuzzing*, et comment tester avec elle. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/accessibility-statement
```

```php
use Phil\AccessibilityCheck\AccessibilityChecker;
use Phil\AccessibilityStatement\Audit\AuditedPage;
use Phil\AccessibilityStatement\Audit\AuditResult;
use Phil\AccessibilityStatement\StatementGenerator;
use Phil\AccessibilityStatement\StatementReader;

$current = StatementReader::fromArray([
    'version' => 1,
    'published_at' => '2026-11-01',
    'entity' => 'Example Org',
    'feedback' => ['email' => 'accessibility@example.com', 'response_days' => 10],
]);

$audit = new AuditResult(new DateTimeImmutable(), [
    new AuditedPage('/', new AccessibilityChecker()->checkHtml('<img src="/team.jpg"><p style="color: #aaa">Closed on Mondays.</p>')),
]);

$next = new StatementGenerator()->generate($current, $audit, new DateTimeImmutable());

$next->version;                         // 2
$next->status->value;                   // "partial"
$next->nonConformities[0]->criterion;   // SuccessCriterion::NonTextContent, found once
echo json_encode($next->toArray());     // the data to keep
```

## Prérequis

PHP 8.5 ou plus récent, avec l'extension `mbstring` ; `phil/accessibility-check` et `phil/url`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
