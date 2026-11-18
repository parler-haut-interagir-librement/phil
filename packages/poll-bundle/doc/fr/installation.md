<!-- phil-dev source-sha256=266f3dc558875b62b664ee3cbb016ff5f13d9a16d7610a297f645c54b11f4570 -->
# Installation

## Prérequis

| Paquet | Version | Raison |
|---|---|---|
| PHP | 8.5 ou plus récent, avec `mbstring` et `sodium` | Les libellés sont comptés en caractères ; les reçus sont encodés sans fuite de temps d'exécution. |
| `phil/poll` | `^0.1` | Les règles, les bulletins et les méthodes de dépouillement. |
| `phil/resource` | `^0.1` | Les votants, les créateurs et les sujets nommés par des références (`user:7`, `idea:42`). |
| `doctrine/orm`, `doctrine/dbal` | `^3.7`, `^4.5` | Les tables des sondages, des bulletins et des participations. |
| `symfony/config`, `symfony/dependency-injection`, `symfony/uid` | `^8.2` | Le bundle, sa configuration et les identifiants aléatoires. |
| `symfony/cache-contracts`, `symfony/service-contracts` | `^3.6` | Le cache des dépouillements, quand on en nomme un ; les stockages en mémoire vidés entre deux requêtes. |
| `psr/clock`, `psr/event-dispatcher` | `^1.0` | L'heure d'un bulletin et des dates ; les événements. |

DoctrineBundle garde les tables dans la base de données de l'application ; sans lui, le stockage `memory` sert aux
tests. TwigBundle, avec le traducteur, dessine l'[interface de vote](voting-interface.md), avec Symfony UX Twig Component 3.7
ou plus récent et les recettes du kit ci-dessous.

## Activer le bundle

```sh
composer require phil/poll-bundle
```

```php
// config/bundles.php
return [
    // …
    Phil\PollBundle\PhilPollBundle::class => ['all' => true],
];
```

Le bundle a besoin d'un service `clock`, qu'enregistre FrameworkBundle.

## Les recettes du kit

Les gabarits dessinent leurs parties avec des recettes du kit PHIL UX, installées dans l'application par le Symfony UX
Toolkit ; rien n'est rendu depuis `vendor/` :

```sh
for recipe in field radio-group checkbox native-select button progress; do
    php bin/console ux:install $recipe --kit=github.com/parler-haut-interagir-librement/ux-kit
done
```

Un site sur Tailwind installe plutôt les six mêmes recettes depuis le kit Shadcn du Toolkit, avec `label`, qu'appelle
son `Field:Label`, et Symfony UX Icons, que dessinent sa case à cocher et sa liste ; les gabarits ne donnent aux
recettes que ce que les deux kits lisent de la même façon. On les trouve sous le sous-répertoire des composants de la configuration `ux_toolkit` ;
dans un environnement où le Toolkit n'est pas activé, comme l'est souvent la production, répétez-le dans
`kit_subdirectory`.

## Les tables

Le bundle ajoute son mappage à DoctrineBundle : `phil_poll`, `phil_poll_vote` et `phil_poll_participation`. La
table `phil_poll` contient les sièges de chaque sondage et la révision de ses bulletins.
Générez la migration comme pour les entités de l'application :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

### Collation des clés

**Les clés se comparent octet par octet**, comme PHP les compare. Un votant dépose un bulletin par sondage et est
retrouvé par sa référence : un bulletin de `user:Ann` n'est jamais un bulletin de `user:ann`, de sorte qu'aucun n'est
refusé comme ayant déjà voté ni ne voit le bulletin de l'autre ; et les sondages du sujet `post:Abc` ne sont jamais
listés pour `post:abc`. `BinaryKeysListener` donne à ces champs la collation binaire quand l'ORM charge leurs
métadonnées sur MySQL ou MariaDB ; le mappage lui-même reste le même sur toutes les plateformes.

| Table | Colonnes en `utf8mb4_bin` sur MySQL et MariaDB |
|---|---|
| `phil_poll` | `id`, `subject` |
| `phil_poll_vote` | `id`, `poll`, `voter`, `receipt` |
| `phil_poll_participation` | `poll`, `voter` |

Sur MySQL et MariaDB, ces colonnes prennent le jeu de caractères `utf8mb4` et la collation `utf8mb4_bin`, selon la
plateforme de la connexion : la collation par défaut de MySQL 8, `utf8mb4_0900_ai_ci`, ignore la casse et les accents.
Les collations par défaut de PostgreSQL sont déterministes, égales seulement quand les octets le sont, et SQLite compare
les octets : leurs colonnes gardent la valeur par défaut.

#### Mise à niveau depuis 0.3

Les tables d'une installation 0.3 sur MySQL ou MariaDB gardaient la collation de la connexion.
`doctrine:migrations:diff` écrit la migration qui amène les colonnes ci-dessus à `utf8mb4_bin` ; exécutez-la avec
`doctrine:migrations:migrate`. Le changement ne fait que distinguer davantage les valeurs : aucune ligne existante ne
brise une clé. Les lignes qu'une collation insensible à la casse avait déjà fusionnées restent fusionnées ; les valeurs
écrites à partir de la migration sont gardées distinctes. Sur PostgreSQL et SQLite, le diff est vide.

## Un premier sondage

```php
use Phil\PollBundle\Application\PollDraft;
use Phil\PollBundle\Application\Polls;
use Phil\PollBundle\Domain\ValueObject\PollOption;
use Phil\Resource\ResourceReference;

$poll = $polls->create(
    new PollDraft('Saturday or Sunday?', [PollOption::of('sat', 'Saturday'), PollOption::of('sun', 'Sunday')]),
    ResourceReference::of('user', '7'),
);

$polls->cast($poll->id, ResourceReference::of('user', '8'), ['sun']);

$polls->results($poll->id)->winners();   // ['sun']
```

C'est l'application qui décide qui peut créer, voter et gérer : voir [Points d'extension](extension-points.md).
