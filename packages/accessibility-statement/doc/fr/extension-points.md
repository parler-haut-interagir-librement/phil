<!-- phil-dev source-sha256=b5a3049527565dfbd30d4386eecb328109a0da9653ecb99e82ce6e23100aea63 -->
# Points d'extension

Ce qu'une application implémente, remplace ou utilise.

## Le dernier audit

`Contract\AuditSource::latest(): ?AuditResult` donne l'audit que lit le générateur. Implémentez-le au-dessus de ce qui
exécute les vérifications : un répertoire de rapports lus avec `ReportReader`, une table de rapports, un robot
d'exploration qui produit le rendu des pages et appelle `AccessibilityChecker`. Un `AuditResult` contient le moment de
l'audit, ses pages — chacune étant un `AuditedPage` avec le nom que lui donne l'audit et son `Report` —, sa
`EvaluationMethod` et l'adresse de son propre rapport.

## Conserver les déclarations

La bibliothèque ne conserve rien. `Statement::toArray()` et `StatementReader` font passer une déclaration vers et depuis
tout stockage qui garde une table ou un document JSON ; le numéro de version en est la clé naturelle. Conservez chaque
version : une déclaration ne change jamais une fois publiée.

## Rendu

La bibliothèque ne produit aucun rendu. Une page affiche les parties d'un `Statement` : `target->describe()` et
`target->standard->url()` pour la revendication, `nonConformities` regroupées par `reason`, le titre de chaque critère
tiré de `SuccessCriterion::title()` (en anglais, celui du W3C), les textes dans la langue du lecteur avec
`LocalizedText::in()`, `feedback->phoneUri()` pour un lien `tel:`. Chaque texte est brut : échappez-le.

## L'état

Une application qui juge la conformité selon sa propre règle écrit l'état elle-même ; `Statement` vérifie seulement
qu'il s'accorde avec les non-conformités.
