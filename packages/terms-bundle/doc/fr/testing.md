<!-- phil-dev source-sha256=f6451227ff06e95ba6f698532e8f1d362c3de823632bf2c7b95a4ce14993f1ed -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| Le domaine | Chaque identifiant et ses bornes, les versions et leurs règles, les acceptations, les politiques et leur renouvellement |
| Le catalogue et le vérificateur | Les versions en vigueur, exigées et dernières importantes à un moment donné ; une lecture par requête ; le renouvellement |
| `Terms` | La numérotation, les premières versions importantes, les moments passés, les formats, les événements et les avis ; l'acceptation de versions à venir et remplacées |
| `Outstanding` et les rappels | Les lots de 500, la dernière version importante, les membres qui ne seront pas en règle |
| Les stockages | SQLite en mémoire : les colonnes et les index, l'ordre, le refus d'un numéro déjà pris, les pages de membres, l'effacement, les lignes devenues illisibles, les moments écrits et relus en UTC sur un serveur d'un autre fuseau horaire |
| La porte | Les pages, les API, les routes et chemins exemptés, les visiteurs et les sous-requêtes, le problème en deux langues |
| Les pages | La page d'acceptation et son formulaire, les cibles, les pages publiques, les changements, les en-têtes de cache |
| L'administration | La liste et ses moments dans le fuseau horaire de l'administration, le formulaire, chaque refus, le rôle et le jeton |
| Les kits | Chaque page publique et chaque page de l'administration rendues avec le kit PHIL UX, dans un sous-répertoire et à la racine, et avec le kit Shadcn du Symfony UX Toolkit : les mêmes titres, libellés et cellules d'en-tête, aucune propriété d'un kit écrite comme attribut par l'autre, aucun attribut en double ; aucun gabarit n'écrit un mot de sa main |
| Le formulaire, la commande, les adaptateurs | La case à cocher d'inscription et ses vérifications ; `phil:terms:outstanding`, `phil:terms:check-legal-links` ; notifications, vie privée, réseau, sécurité, planificateur, EasyAdmin |
| Le bundle | Le câblage et chaque borne de la configuration ; un noyau sur SQLite |

Infection maintient le bundle à 100 % de mutants couverts tués ; les mutants équivalents sont nommés dans
`phil-dev.php` avec leurs raisons.

```sh
vendor/bin/phpunit packages/terms-bundle/tests
```

Les tests unitaires rendent les gabarits avec le Twig de `Tests\Unit\Fixture\TemplatesKernel`, un noyau avec les recettes
du kit PHIL UX, démarré une fois par locale ; `KitRecipes` copie les recettes de l'un ou l'autre kit comme le ferait
`ux:install`. Le kit Shadcn est lu dans `symfony/ux-toolkit`, une exigence de développement ; ses tests sont ignorés sans
lui.

## Banc d'essai

`tests/Benchmark/AcceptanceGateBench.php` exécute la porte pour un membre en règle avec trois politiques requises de
cinq versions chacune, sur SQLite en mémoire avec 10 000 acceptations, et pour un visiteur. Sur un ordinateur portable
récent :

| Sujet | Temps |
|---|---|
| Un membre en règle : les versions lues, puis une question par politique | environ 350 µs |
| Un visiteur | environ 13 µs |

```sh
composer bench -- packages/terms-bundle/tests/Benchmark
```

## Tester une application

Les stockages sont des ports : les tests d'une application peuvent donner à `Terms` et à `AcceptanceChecker` des
stockages en mémoire et une `MockClock`, publier des versions, et avancer l'horloge pour tester le renouvellement et
l'entrée en vigueur des versions.
