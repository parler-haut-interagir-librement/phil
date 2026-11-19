<!-- phil-dev source-sha256=6ea23b52d384a62bf998cf7d3ca3dd94ea1d5330c75895e6c218170ff8faae01 -->
# phil/library-bundle

**Une ludothèque ou une bibliothèque pour Symfony : catalogue, recherche, prêts, retours et rappels, ses exemplaires étant des articles de stockroom.**

phil/library-bundle fait fonctionner une ludothèque, une bibliothèque de livres ou de tout autre objet prêté. Son
catalogue contient des titres décrits par leurs fiches — ISBN, auteurs, éditeur, année et, pour un jeu, les joueurs, la
durée d'une partie et l'âge minimal qui lui convient —, recherchés par une liste filtrée avec un simple formulaire,
paginée par des liens, sans JavaScript. Chaque titre a des exemplaires, connus par leurs codes à barres, chacun avec son
état et une note — les pièces manquantes d'une boîte.

Les exemplaires sont prêtés au comptoir ou réservés à l'avance, sur `phil/reservation` : chaque exemplaire est une
ressource d'une unité et chaque prêt une réservation de celle-ci, de sorte qu'un exemplaire sorti ou réservé sur une
période n'est pas prêté de nouveau sur cette période. Un prêt est dû à la fin de son dernier jour sur l'horloge de la
bibliothèque, renouvelé un nombre fixé de fois quand personne n'a réservé l'exemplaire après lui, retourné avec l'état
où se trouve l'exemplaire, ou enregistré comme perdu. Un membre qui a un exemplaire en retard, ou qui détient autant de
prêts que permis, n'emprunte plus. Les rappels avant l'échéance et les avis de prêt en retard partent sous forme de
messages Messenger qui ne portent que des identifiants ; aucune amende n'est comptée.

Un membre est l'identifiant stable de l'application — un numéro de carte, un numéro de compte —, jamais une adresse
courriel. Un membre voit ses prêts sur une page à lui ; le personnel a le comptoir, l'administration du catalogue et la
liste des prêts en retard, joignables depuis une interface d'administration EasyAdmin si l'application en a une. Les
pages sont dessinées par les recettes du kit PHIL UX. L'historique des prêts d'un membre fait partie des demandes
d'accès et d'effacement de `phil/privacy-bundle`, et peut être oublié après un nombre de jours. Un exemplaire peut être
un article de `phil/stockroom` : ses prêts font alors sortir l'article vers le prêt et le font revenir — avec
`phil/stockroom-bundle` dans la même base de données, dans la transaction du prêt.

## État

| Partie | État |
|---|---|
| `Title`, `Copy`, `Loan` dans Doctrine, moments en UTC, `Record` et son ISBN | Implémenté et testé, sur SQLite, MySQL 8.4 et PostgreSQL 17 |
| `Catalogue` : titres, fiches, exemplaires, états, liens avec stockroom | Implémenté et testé |
| `Loans` : prêter, réserver, enregistrer la sortie, renouveler, retourner, annuler, perdre, les limites du membre, l'historique oublié | Implémenté et testé |
| Deux prêts d'un même exemplaire faits en même temps | Testé sur MySQL et PostgreSQL |
| `DbalTitleSearch`, la recherche du catalogue avec DBAL | Implémenté et testé |
| `Reminders`, l'expéditeur Messenger, les commandes | Implémenté et testé |
| Les pages : catalogue, titre, prêts du membre, comptoir, administration, retards ; les recettes du kit | Implémenté et testé |
| `LoanHistoryPrivacy`, le module de `phil/privacy-bundle` | Implémenté et testé |
| `StockroomLink`, le lien entre un exemplaire et un article de `phil/stockroom`, dans les transactions de `phil/stockroom-bundle` | Implémenté et testé |
| `LibraryAdmin`, les liens d'une interface d'administration EasyAdmin | Implémenté et testé |
| La recette Flex | Écrite dans `recipe/` |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement du bundle, le schéma, les routes, les recettes du kit. |
| [Configuration](doc/fr/configuration.md) | Chaque option de `phil_library`, avec un exemple. |
| [Catalogue et recherche](doc/fr/catalogue.md) | Les titres, les fiches, les ISBN, les exemplaires, la recherche. |
| [Prêts](doc/fr/loans.md) | Prêter, réserver, renouveler, retourner, perdre, les limites du membre, le verrou, le lien avec stockroom. |
| [Rappels et vie privée](doc/fr/reminders.md) | Les rappels et les avis, les commandes, l'historique dans les demandes de vie privée. |
| [Pages](doc/fr/pages.md) | Les routes, les gabarits, les recettes du kit, les rôles, les liens EasyAdmin. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les événements et les options qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, sa suite d'intégration sur MySQL et PostgreSQL, et son seuil de mutation. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/library-bundle
```

```yaml
# config/packages/phil_library.yaml
phil_library:
    timezone: America/Toronto
    loans: { days: 21, max_renewals: 2, max_open: 10 }

# config/routes/phil_library.yaml
phil_library:
    resource: '@PhilLibraryBundle/config/routes.php'
    prefix: /library
```

```php
use Phil\LibraryBundle\Application\Exception\LoanRefused;
use Phil\LibraryBundle\Application\Loans;

try {
    $loan = $loans->lend('G-0042', $member);   // due at the end of the 21st day
} catch (LoanRefused $refused) {
    $refused->reason;                          // taken, overdue, limit…
}

$loans->giveBack('G-0042', CopyCondition::Incomplete, 'Two tiles missing.');
```

## Prérequis

PHP 8.5 ou une version plus récente, Symfony 8.2 ou une version plus récente, Doctrine ORM 3.7 et DBAL 4.5 ou une
version plus récente, Twig et Symfony UX Twig Components. `symfony/console`, `symfony/messenger`,
`phil/privacy-bundle`, `phil/stockroom`, `phil/stockroom-bundle` et EasyAdmin sont facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
