# Maker commands

With `symfony/maker-bundle` installed, the bundle adds five commands that write the skeleton of a bounded context and
of its classes. Without MakerBundle, none of them is registered and nothing else changes.

```bash
composer require --dev symfony/maker-bundle
```

| Command | Arguments | Writes |
|---|---|---|
| `make:ddd:context` | `<Context>` | `src/<Context>/README.md`, and `Domain/`, `Application/`, `Infrastructure/`, each kept by an empty `.gitignore` |
| `make:ddd:identifier` | `<Context> <Name>` | `Domain/<Name>Id.php`, an `Identifier`; `Infrastructure/Persistence/Doctrine/Type/<Name>IdType.php`, its `IdentifierType` |
| `make:ddd:command` | `<Context> <Name>` | `Application/<Name>Command.php`, a `Command`; `Application/<Name>Handler.php`, its `CommandHandler` |
| `make:ddd:query` | `<Context> <Name>` | `Application/<Name>Query.php`, a `Query`; `Application/<Name>Handler.php`, its `QueryHandler` |
| `make:ddd:event` | `<Context> <Name>` | `Domain/Event/<Name>.php`, a `DomainEvent` |

The paths are relative to `src/<Context>/`. A missing argument is asked for. After writing, each command says what is
left to do.

## The layout

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

The namespace of `src/` is the root namespace of MakerBundle (`maker.root_namespace`, `App` by default), and each folder
below it is a namespace segment: `App\Catalog\Domain\ProductId`. An application whose `src/` maps to another namespace
in `composer.json` sets the same namespace in MakerBundle's configuration.

This layout fits a service configuration that loads `src/` and leaves out every `src/*/Domain/` and the commands and
queries of every `src/*/Application/`, since they are plain objects and messages, not services:

```php
$services->load('App\\', __DIR__.'/../src')
    ->exclude([
        __DIR__.'/../src/*/Domain/',
        __DIR__.'/../src/*/Application/**/{*Query,*Command}.php',
        __DIR__.'/../src/Kernel.php',
    ]);
```

The handlers are then services, and the bundle tags each one on its bus: see
[Command and query buses](command-and-query-buses.md).

## Names

- A context and a name are PascalCase ASCII words: a capital letter, then letters and digits. `Catalog`, `AddProduct`
  and `Order2Line` are accepted; `catalog`, `Add product`, `Catalog\Admin` and `../Product` are refused.
- A class name is never a word PHP reserves: `make:ddd:event Catalog List` is refused. A context may be one, since PHP
  accepts it as a namespace segment.
- The suffix a command adds is not repeated: `make:ddd:command Catalog AddProductCommand` writes `AddProductCommand`
  and `AddProductHandler`, as `AddProduct` does; `make:ddd:identifier Catalog ProductId` writes `ProductId`, as
  `Product` does. A name that is the suffix alone keeps it: `Command` gives `CommandCommand`.
- An existing file is never overwritten: the command stops before writing anything, and says which file is in the way.

## What each class holds

- **The identifier** is an empty `final readonly` subclass of `Identifier`: see [Identifiers](identifiers.md). Its
  type is a `final` subclass of `IdentifierType` that names it, and the command shows the line that registers the type
  with DoctrineBundle, under a name made of the identifier's class in snake case (`product_id`):

  ```php
  // config/packages/doctrine.php, under doctrine.dbal.types
  'product_id' => \App\Catalog\Infrastructure\Persistence\Doctrine\Type\ProductIdType::class,
  ```

  A column then declares `#[ORM\Column(type: 'product_id')]`. See [Doctrine identifier type](doctrine-identifier-type.md).
- **The command** is an empty `final readonly` class: its properties, identifiers and scalars, are yours to add. Its
  handler has an empty `__invoke()` that returns `void`.
- **The query** declares `@implements Query<null>`, and its handler returns `null`: replace `null` by the type of the
  answer in both, as the comment of the query says.
- **The event** takes the time it occurred in its constructor and returns it from `occurredAt()`: see
  [Domain events](domain-events.md).

Every PHP file written passes PHPStan at its maximum level, with the strict rules, and the PHIL code style of
PHP-CS-Fixer, as it is.

## Example session

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

The command handler written by `make:ddd:command`:

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

## The templates

The files are rendered from the templates in the bundle's `templates/maker/` folder, by replacing their `{{…}}`
placeholders; nothing in a template is executed. `Application\Maker\DddSkeleton` renders them and returns the files
without writing them, and the commands of `Infrastructure\Maker` write what it returns through MakerBundle's generator.
