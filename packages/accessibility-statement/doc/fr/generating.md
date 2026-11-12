<!-- phil-dev source-sha256=523bb24529a8c77554132141bb2bfdb4c4310d6c1385cf6dd93d4d2bd2c1b838 -->
# Générer à partir d'un audit

`StatementGenerator` écrit la version suivante d'une déclaration à partir du dernier audit du contenu, c'est-à-dire des
rapports que `phil/accessibility-check` a produits pour ses pages.

```php
use Phil\AccessibilityCheck\AccessibilityChecker;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityStatement\Audit\AuditedPage;
use Phil\AccessibilityStatement\Audit\AuditResult;
use Phil\AccessibilityStatement\EvaluationMethod;
use Phil\AccessibilityStatement\StatementGenerator;

$checker = new AccessibilityChecker();
$audit = new AuditResult(new DateTimeImmutable(), [
    new AuditedPage('/', $checker->checkHtml($home)),
    new AuditedPage('/events', $checker->checkHtml($events)),
], EvaluationMethod::Automated, reportUrl: '/accessibility/audit.html');

$next = new StatementGenerator(Severity::Error)->generate($current, $audit, new DateTimeImmutable());
```

## Le contenu de la version suivante

- Tout ce qu'un audit ne connaît pas vient de la version actuelle : la cible, le contact pour la rétroaction, les
  solutions de rechange, la portée, la procédure de recours, l'organisation.
- Les non-conformités écrites à la main (`fromAudit` faux) sont conservées telles quelles, en premier.
- Les non-conformités de l'audit précédent sont remplacées par celles de cet audit : une pour chaque critère de succès
  de la cible qu'un constat de la sévérité minimale ou plus grave fait échouer — `Error` par défaut —, dans l'ordre des
  critères, avec le nombre de constats sur l'ensemble des pages comme `occurrences`, et `fromAudit` vrai.
- Un critère que l'audit précédent avait déjà trouvé garde ce qui en avait été écrit : sa raison, sa description, sa
  solution de rechange et sa date de correction. Un critère que l'audit ne trouve plus est levé.
- Un critère que nomme une non-conformité écrite à la main lui est laissé. Un critère hors de la cible — un constat sur
  un critère de niveau AAA sous une cible AA — n'est pas une non-conformité de la déclaration.
- L'état est celui que justifient les non-conformités (`ConformanceStatus::derive()`).
- L'évaluation est celle de l'audit : son moment, sa méthode, le nombre de ses pages et son rapport.
- La version vaut un de plus que la version actuelle, sauf si `generate()` reçoit un autre numéro.

Une vérification automatisée ne voit pas tous les obstacles : une déclaration générée à partir d'une telle vérification
indique `EvaluationMethod::Automated`, et les non-conformités trouvées par des personnes y restent, écrites à la main.

## Relire des rapports

Un audit exécuté ailleurs — par `phil:accessibility:check --format=json`, ou par une application qui conserve
`json_encode($report)` — se relit avec `Audit\ReportReader` :

```php
use Phil\AccessibilityStatement\Audit\ReportReader;

$report = new ReportReader()->fromJson(file_get_contents('var/accessibility/home.json'));
```

Il lit le résultat de chaque règle et chaque constat : sa règle, son critère, sa sévérité, sa clé de message et ses
paramètres, et son emplacement. Le texte d'un message est réécrit à partir du catalogue de `phil/accessibility-check`,
dans la locale du rapport. Un rapport de plus de 5 Mio (ou de la taille donnée au constructeur), plus profond qu'un
rapport ne l'est, ou qui n'en a pas la forme, est refusé avec une `InvalidReport`. Au plus 50 000 règles et 50 000
constats sont lus.

## L'origine de l'audit

`Contract\AuditSource` est le port qu'une application implémente pour donner le dernier audit : un répertoire de
rapports, une table, un service qui vérifie les pages du site. `latest()` retourne un `AuditResult`, ou `null` s'il n'y
en a pas encore. `phil/accessibility-statement-bundle` en fournit un qui lit un répertoire de rapports JSON.
