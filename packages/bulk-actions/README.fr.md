<!-- phil-dev source-sha256=ec7ddd6b0f4cb7fce3ab67de5f37625399eb985d7c2c8e5c04cf6e8dc73ee51b -->
# phil/bulk-actions

**Modifiez des milliers d'enregistrements en toute sécurité depuis une liste d'administration : sélection côté serveur,
modification champ par champ, aperçu, voters et versions par élément, exécution par lots, nouvelles tentatives.**

phil/bulk-actions est une bibliothèque PHP. Elle donne à la page de liste d'une administration les quatre étapes d'une
action groupée — sélectionner, prévisualiser, confirmer, exécuter — et vérifie tout de nouveau à chacune d'elles.

**Sélection côté serveur.** Une page envoie une liste bornée d'identifiants, ou « tous les éléments de ce filtre »
moins les lignes que la personne a décochées. `BulkSelector` la range sur le serveur et rend un **jeton à usage unique
qui expire** ; la page porte le jeton, jamais 50 000 identifiants. Un filtre est un nom que le dépôt de la cible
déclare, avec ses paramètres, et le dépôt le relit quand l'exécution a lieu.

**La classe vient de la configuration.** Une requête nomme une cible, comme `products` ; la `Target` que l'application
déclare dit de quelle classe sont ses éléments, quel dépôt les lit et quelles actions s'y appliquent. Chaque élément lu
est vérifié par rapport à cette classe.

**Une case par champ.** `ChangeSet::fromSubmission()` lit un formulaire de modification groupée où chaque champ a une
case : un champ décoché reste inchangé, tandis qu'un champ coché et vidé met `null`, `false`, `""` ou `0`.

**Aperçu, puis confirmation.** `BulkPreviewer` donne le nombre d'éléments, un échantillon avec l'ancienne et la nouvelle
valeur de chaque champ, les éléments de l'échantillon que le voter refuse, et si l'action est destructrice — sans
utiliser le jeton. `BulkConfirmer` utilise le jeton une seule fois, redemande les règles d'accès et range un `BulkRun`.

**Exécution par lots.** Un *worker* traite des messages `ExecuteBulkRun`, qui portent l'identifiant de l'exécution et
rien d'autre. `BulkExecutor` redemande le droit de lancer l'action, puis vérifie la classe de chaque élément, son
**voter** et sa **version** quand la personne en a vu une, et applique le lot dans **une seule transaction**. Quand le
lot échoue, chaque élément est appliqué de nouveau dans sa propre transaction, de sorte qu'un mauvais élément
n'entraîne jamais les autres avec lui. Les effets hors de la base de données passent par une boîte d'envoi (*outbox*)
sous la clé d'idempotence de chaque élément.

**Un rapport par élément.** Chaque élément se termine réussi, refusé par son voter, en conflit avec une version plus
récente, refusé par l'action avec sa raison, en échec ou manquant. `BulkRetrier` relance les éléments en échec, et les
conflits sur demande ; rien d'autre n'est touché deux fois.

La bibliothèque ne lit et n'écrit que par des **ports** — les éléments, les règles d'accès, les magasins, les
transactions, le répartiteur et la piste d'audit — et ne garde aucun état entre les appels : ses services sont sûrs
dans un *worker* de longue durée.

## État

| Partie | État |
|---|---|
| `Selection`, `FilterCriteria`, `SelectionToken`, `BulkSelector` : sélection côté serveur sous un jeton à usage unique | Implémenté et testé |
| `ChangeSet` : une case par champ | Implémenté et testé |
| `BulkPreviewer`, `Preview` : nombre, échantillon, anciennes et nouvelles valeurs, avertissement de destruction | Implémenté et testé |
| `BulkConfirmer` : jeton utilisé une fois, accès redemandé, exécution rangée et envoyée | Implémenté et testé |
| `BulkExecutor`, `ExecuteBulkRunHandler` : lots, transactions, voters, versions, reprise élément par élément | Implémenté et testé |
| `BulkRun`, `Report`, `BulkRetrier` : résultat par élément, nouvelle tentative des échecs | Implémenté et testé |
| `ExternalSelection` : sélections issues des lignes d'une table de données ou d'une action groupée d'une administration | Implémenté et testé |
| `InMemorySelectionStore`, `InMemoryRunStore` | Implémenté et testé |
| Adaptateurs vers une base de données, un bus, les règles d'accès et les pages | Écrits par l'application |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, et la place de chaque service. |
| [Configuration](doc/fr/configuration.md) | Les cibles, les actions et les limites. |
| [Sélection](doc/fr/selection.md) | Les identifiants, les filtres, les lignes décochées, les jetons et les cases par champ. |
| [Aperçu et confirmation](doc/fr/confirmation.md) | L'aperçu, le dialogue natif, la confirmation et les requêtes qu'envoie une page. |
| [Exécution avec Messenger](doc/fr/messenger.md) | Les lots, les transactions, la reprise élément par élément, les effets par une boîte d'envoi, les rapports et les nouvelles tentatives. |
| [Sécurité](doc/fr/security.md) | Ce qui est vérifié à chaque étape, et pourquoi une action groupée ne contourne aucune règle d'accès. |
| [Passerelles](doc/fr/bridges.md) | Les tables de données, les actions groupées d'une administration, la corbeille et la piste d'audit. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports qu'une application implémente, avec des adaptateurs. |
| [Tests](doc/fr/testing.md) | Les tests, le flux fonctionnel et le banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/bulk-actions
```

```php
use Phil\BulkActions\Change\ChangeSet;
use Phil\BulkActions\Selection\FilterCriteria;
use Phil\BulkActions\Selection\Selection;
use Phil\BulkActions\Selection\SelectionToken;

// The list page: every draft product but one; the page keeps only the token.
$token = $selector->select($user, 'products', 'edit', Selection::filter(FilterCriteria::of('status', ['status' => 'draft']), ['p42']), new DateTimeImmutable());

// The form: the price box ticked, the field emptied, the status left alone.
$changes = ChangeSet::fromSubmission($request->request->all('values'), $request->request->all('apply'));
$preview = $previewer->preview($user, SelectionToken::fromString($tokenFromThePage), $changes, new DateTimeImmutable());

// The confirmation, a POST: the run is filed and its first chunk dispatched to a worker.
$run = $confirmer->confirm($user, SelectionToken::fromString($tokenFromThePage), $changes, new DateTimeImmutable());
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-sodium`. Aucun autre paquet : Symfony Messenger, une boîte d'envoi et une
piste d'audit s'atteignent par des ports.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
