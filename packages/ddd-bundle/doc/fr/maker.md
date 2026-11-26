<!-- phil-dev source-sha256=eb0909c9a0a20371c8bef6f22017602b5c2c2830179d316ef5514167b527e57d -->
# Commandes Maker

Avec `symfony/maker-bundle` installé, le bundle ajoute cinq commandes qui écrivent le squelette d'un contexte délimité et
de ses classes. Sans MakerBundle, aucune n'est enregistrée et rien d'autre ne change.

```bash
composer require --dev symfony/maker-bundle
```

| Commande | Arguments | Écrit |
|---|---|---|
| `make:ddd:context` | `<Context>` | `src/<Context>/README.md`, et `Domain/`, `Application/`, `Infrastructure/`, chacun conservé par un `.gitignore` vide |
| `make:ddd:identifier` | `<Context> <Name>` | `Domain/<Name>Id.php`, un `Identifier` ; `Infrastructure/Persistence/Doctrine/Type/<Name>IdType.php`, son `IdentifierType` |
| `make:ddd:command` | `<Context> <Name>` | `Application/<Name>Command.php`, une `Command` ; `Application/<Name>Handler.php`, son `CommandHandler` |
| `make:ddd:query` | `<Context> <Name>` | `Application/<Name>Query.php`, une `Query` ; `Application/<Name>Handler.php`, son `QueryHandler` |
| `make:ddd:event` | `<Context> <Name>` | `Domain/Event/<Name>.php`, un `DomainEvent` |

Les chemins sont relatifs à `src/<Context>/`. Un argument manquant est demandé. Après l'écriture, chaque commande dit ce
qu'il reste à faire.

## La disposition

```text
src/
└── Catalog/
    ├── README.md
    ├── Domain/
    │   ├── ProductId.php
    │   └── Event/
    │       └── ProductAdded.php
    ├── Application/
    │   ├── AddProductCommand.php
    │   ├── AddProductHandler.php
    │   ├── FindProductQuery.php
    │   └── FindProductHandler.php
    └── Infrastructure/
        └── Persistence/Doctrine/Type/
            └── ProductIdType.php
```

L'espace de noms de `src/` est l'espace de noms racine de MakerBundle (`maker.root_namespace`, `App` par défaut), et
chaque dossier en dessous est un segment d'espace de noms : `App\Catalog\Domain\ProductId`. Une application dont `src/`
correspond à un autre espace de noms dans `composer.json` définit le même espace de noms dans la configuration de
MakerBundle.

Cette disposition convient à une configuration de services qui charge `src/` et exclut chaque `src/*/Domain/` ainsi que
les commandes et les requêtes de chaque `src/*/Application/`, puisque ce sont de simples objets et messages, non des
services :

```php
$services->load('App\\', __DIR__.'/../src')
    ->exclude([
        __DIR__.'/../src/*/Domain/',
        __DIR__.'/../src/*/Application/**/{*Query,*Command}.php',
        __DIR__.'/../src/Kernel.php',
    ]);
```

Les *handlers* sont alors des services, et le bundle étiquette chacun sur son bus : voir
[Bus de commandes et de requêtes](command-and-query-buses.md).

## Les noms

- Un contexte et un nom sont des mots ASCII en PascalCase : une majuscule, puis des lettres et des chiffres. `Catalog`,
  `AddProduct` et `Order2Line` sont acceptés ; `catalog`, `Add product`, `Catalog\Admin` et `../Product` sont refusés.
- Un nom de classe n'est jamais un mot que PHP réserve : `make:ddd:event Catalog List` est refusé. Un contexte peut en
  être un, puisque PHP l'accepte comme segment d'espace de noms.
- Le suffixe qu'ajoute une commande n'est pas répété : `make:ddd:command Catalog AddProductCommand` écrit
  `AddProductCommand` et `AddProductHandler`, comme `AddProduct` ; `make:ddd:identifier Catalog ProductId` écrit
  `ProductId`, comme `Product`. Un nom qui est le suffixe seul le garde : `Command` donne `CommandCommand`.
- Un fichier existant n'est jamais écrasé : la commande s'arrête avant d'écrire quoi que ce soit, et dit quel fichier
  est en cause.

## Ce que contient chaque classe

- **L'identifiant** est une sous-classe `final readonly` vide d'`Identifier` : voir [Identifiants](identifiers.md). Son
  type est une sous-classe `final` d'`IdentifierType` qui le nomme, et la commande affiche la ligne qui enregistre le
  type auprès de DoctrineBundle, sous un nom formé de la classe de l'identifiant en snake case (`product_id`) :

  ```php
  // config/packages/doctrine.php, under doctrine.dbal.types
  'product_id' => \App\Catalog\Infrastructure\Persistence\Doctrine\Type\ProductIdType::class,
  ```

  Une colonne déclare alors `#[ORM\Column(type: 'product_id')]`. Voir
  [Type d'identifiant Doctrine](doctrine-identifier-type.md).
- **La commande** est une classe `final readonly` vide : ses propriétés, identifiants et scalaires, sont à vous
  d'ajouter. Son *handler* a un `__invoke()` vide qui retourne `void`.
- **La requête** déclare `@implements Query<null>`, et son *handler* retourne `null` : remplacez `null` par le type de
  la réponse dans les deux, comme le dit le commentaire de la requête.
- **L'événement** reçoit dans son constructeur l'instant où il s'est produit et le retourne par `occurredAt()` : voir
  [Événements du domaine](domain-events.md).

Chaque fichier PHP écrit passe PHPStan à son niveau maximal, avec les règles strictes, et le style de code PHIL de
PHP-CS-Fixer, tel quel.

## Exemple de session

```console
$ bin/console make:ddd:context Catalog

 created: src/Catalog/README.md
 created: src/Catalog/Domain/.gitignore
 created: src/Catalog/Application/.gitignore
 created: src/Catalog/Infrastructure/.gitignore

  Success!

 Next: describe the context in src/Catalog/README.md, then, when it has Doctrine entities, add its
 mapping on src/Catalog/Infrastructure/Persistence/Doctrine/Entity in config/packages/doctrine.php,
 and add "Catalog" to the contexts of the Deptrac rules.

$ bin/console make:ddd:identifier Catalog Product

 created: src/Catalog/Domain/ProductId.php
 created: src/Catalog/Infrastructure/Persistence/Doctrine/Type/ProductIdType.php

  Success!

 Next: register the type in config/packages/doctrine.php, under doctrine.dbal.types:

     'product_id' => \App\Catalog\Infrastructure\Persistence\Doctrine\Type\ProductIdType::class,

 then map a column with #[ORM\Column(type: 'product_id')].

$ bin/console make:ddd:command Catalog AddProduct

 created: src/Catalog/Application/AddProductCommand.php
 created: src/Catalog/Application/AddProductHandler.php

  Success!

$ bin/console make:ddd:query Catalog FindProduct

 created: src/Catalog/Application/FindProductQuery.php
 created: src/Catalog/Application/FindProductHandler.php

  Success!

$ bin/console make:ddd:event Catalog ProductAdded

 created: src/Catalog/Domain/Event/ProductAdded.php

  Success!
```

Le *handler* de commande écrit par `make:ddd:command` :

```php
<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use Phil\DddBundle\Application\Bus\CommandHandler;

/**
 * Handles AddProductCommand, in the transaction of the command bus.
 */
final readonly class AddProductHandler implements CommandHandler
{
    public function __invoke(AddProductCommand $command): void
    {
    }
}
```

## Les gabarits

Les fichiers sont produits à partir des gabarits du dossier `templates/maker/` du bundle, en remplaçant leurs paramètres
`{{…}}` ; rien dans un gabarit n'est exécuté. `Application\Maker\DddSkeleton` les produit et retourne les fichiers sans
les écrire, et les commandes de `Infrastructure\Maker` écrivent ce qu'il retourne par le générateur de MakerBundle.
