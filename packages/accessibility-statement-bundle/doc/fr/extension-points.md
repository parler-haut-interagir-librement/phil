<!-- phil-dev source-sha256=a57db1e7181692cee55d5099c5cc2e77d75eb12f8f7d54d91e1269dd94e1a2dc -->
# Points d'extension

Ce qu'une application implémente, remplace ou utilise.

## Le dernier audit

Au lieu du répertoire de rapports, `audit.source` peut nommer un service qui implémente
`Phil\AccessibilityStatement\Contract\AuditSource` de `phil/accessibility-statement` : une table où un robot
d'exploration range ses rapports, ou un service qui produit le rendu des pages du site et les vérifie avec
`AccessibilityChecker`. `latest()` retourne un `AuditResult`, ou `null` s'il n'y en a aucun.

## Les versions

`Domain\Contract\StatementRepository` est le stockage des versions, un alias de `ConfiguredStatements` ou de
`DbalStatements`. Une application qui les garde ailleurs décore ou remplace l'alias par le sien. Il offre `latest()`,
`find()`, `publications($limit)` (le moment de chaque version, de la plus récente à la plus ancienne), `writable()` et
`add()`. Cette dernière ne modifie jamais une version déjà publiée et lève `StatementNotStored` quand elle ne peut pas
en garder une.

## La régénération

`Application\StatementRegenerator` (public) donne la version suivante avec `preview()`, ou la publie avec `publish()`.
Il sert à une application qui régénère la déclaration depuis une tâche planifiée, un *handler* de message ou son
interface d'administration plutôt que depuis la console. Il lève `NothingToRegenerate` sans déclaration ou sans audit.

## La publication

`Application\StatementPublisher` (public, avec `admin`) donne le brouillon de la version suivante avec `draft()`, en
JSON, et publie un document avec `publish()`. Il refuse avec `PublicationRefused` un document qui ne se lit pas, qui
n'a pas le numéro suivant, ou que le stockage ne garde pas. Il sert à une interface d'administration propre à
l'application, à la place de la page du bundle ([Administration](administration.md)).

## La page

La mise en page, la page, le tableau des versions, la page d'administration et les libellés sont des gabarits que
l'application remplace ([La page](page.md)) ; le domaine de traduction `phil_accessibility_statement` accepte d'autres
langues et d'autres phrases. Les parties sont des recettes du kit, trouvées sous `kit_subdirectory` ou le
sous-répertoire du Toolkit. Le moyen de rétroaction d'un formulaire est le formulaire que nomme `feedback_form`, ou la
route que nomme `feedback_route`. Avec `phil/legal-notices-bundle`, le lien rejoint les liens juridiques d'un pied de
page par l'étiquette `phil_legal_notices.footer_link` de `StatementFooterLink`, qu'une application retire ou remplace
comme n'importe quel service.
