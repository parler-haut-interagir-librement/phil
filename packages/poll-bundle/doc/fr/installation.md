<!-- phil-dev source-sha256=5e4a51893fdd869f67ea5bba7f0c3fc3b7b443e941d59da4b927f2ab3a7bb0c8 -->
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

Elles ont les noms, les props et les blocs du kit Shadcn du Toolkit : un site sur Tailwind installe donc les six mêmes
recettes depuis le kit Shadcn. On les trouve sous le sous-répertoire des composants de la configuration `ux_toolkit` ;
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
