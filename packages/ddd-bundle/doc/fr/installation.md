<!-- phil-dev source-sha256=4f2b158c5759aea4b6aa18cd9ace462247e7ac3419270d7c2077d72757bb97f1 -->
# Installation

## Prérequis

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que le bundle déclare et sur lequel il est testé |
| `symfony/dependency-injection` | ^8.2 | La classe du bundle est un `AbstractBundle` ; ni `symfony/http-kernel` ni `symfony/framework-bundle` n'est requis |
| `symfony/config` | ^8.2 | L'arbre de configuration du bundle |
| `symfony/messenger` | ^8.2 | Les deux adaptateurs de bus |
| `symfony/uid` | ^8.2 | Les identifiants UUIDv7 |
| `doctrine/dbal` | ^4.2, facultatif | Seulement pour le type de colonne des identifiants ; les versions antérieures à 4.2 sont déclarées en `conflict` |

`Domain\` ne dépend que de `symfony/uid`. Une application qui restreint ce que sa propre couche `Domain` peut importer
peut y permettre `Phil\DddBundle\Domain\` sans rien ouvrir d'autre : aucun conteneur, aucune configuration, aucune
entrée-sortie.

## Installer

Le bundle n'est pas encore publié sur Packagist. Installez-le depuis le dépôt qui le contient, par un dépôt Composer de
type `path` ou `vcs` :

```json
{
    "repositories": [
        { "type": "path", "url": "../path/to/packages/ddd-bundle" }
    ],
    "require": {
        "phil/ddd-bundle": "^0.1@dev"
    }
}
```

Une fois publié :

```bash
composer require phil/ddd-bundle
```

Pour stocker les identifiants par Doctrine, exigez aussi DBAL (4.2 ou plus récent) :

```bash
composer require doctrine/dbal
```

## Enregistrer le bundle

```php
// config/bundles.php
return [
    // …
    Phil\DddBundle\PhilDddBundle::class => ['all' => true],
];
```

## Déclarer les bus Messenger

**Le bundle ne déclare aucun bus Messenger.** Il se branche sur deux bus que déclare l'application, parce que les
*middlewares* de chaque bus sont une décision de l'application : une transaction autour des commandes, aucune autour
des requêtes. Par défaut, le bundle attend les identifiants de service `command.bus` et `query.bus` :

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        default_bus: command.bus
        buses:
            command.bus:
                middleware:
                    - validation
                    - doctrine_transaction
            query.bus: ~
```

- `doctrine_transaction` exige DoctrineBundle ; sans Doctrine, choisissez le *middleware* qui donne aux commandes leur
  transaction dans votre couche de persistance.
- Le bus de requêtes doit rester synchrone : `QueryBus::ask()` lit le résultat du seul *handler* qui s'est exécuté, et
  une requête routée vers un transport ne s'est pas encore exécutée quand `ask()` retourne (voir
  [command-and-query-buses.md](command-and-query-buses.md)).
- D'autres identifiants de bus conviennent : nommez-les dans `phil_ddd` (voir [configuration.md](configuration.md)).

## Rendre les handlers autoconfigurés

Le bundle étiquette les *handlers* par l'autoconfiguration. Les services de l'application doivent donc être
autoconfigurés, ce qui est la valeur par défaut d'une application Symfony :

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true
```

Un *handler* n'a alors qu'à implémenter `CommandHandler` ou `QueryHandler` ; ni attribut `#[AsMessageHandler]` ni
option `bus` n'est nécessaire. Voir [command-and-query-buses.md](command-and-query-buses.md).

## Enregistrer les types de colonne des identifiants

Facultatif, et seulement avec DBAL. Chaque classe d'identifiant reçoit sa propre sous-classe de `IdentifierType`,
enregistrée sous un nom de type que choisit l'application. Voir [doctrine-identifier-type.md](doctrine-identifier-type.md).

## Vérifier l'installation

```bash
bin/console debug:container 'Phil\DddBundle\Application\Bus\CommandBus'
bin/console debug:messenger
```

La première commande montre l'alias vers `MessengerCommandBus` ; la seconde liste chaque *handler* sous le bus que son
marqueur a choisi.
