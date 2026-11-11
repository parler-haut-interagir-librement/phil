<!-- phil-dev source-sha256=8a7419e4d4e70c920627fb579f1df6ffc7435bd4d163f7db505893e8b1429d9e -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent ;
- Symfony 8.2 ou plus récent : les composants de console, de répartition d'événements et d'injection de dépendances,
  avec l'application de console de FrameworkBundle ;
- en option, Monolog, avec MonologBundle, pour `--debug-phil`.

## Installer

```sh
composer require phil/console-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\ConsoleBundle\PhilConsoleBundle::class => ['all' => true],
];
```

Le bundle fonctionne sans configuration : les gardes s'appliquent dès qu'une commande porte un attribut, et les options
sont ajoutées à chaque commande.

## Mode *worker*

Les services ne conservent rien entre deux commandes. Ce dont une exécution a besoin jusqu'à sa fin — son départ, sa
mémoire, les filtres de flux de ses horodatages, les gestionnaires de débogage — est conservé par son entrée et
abandonné à la fin. L'écouteur et l'interrupteur de débogage portent l'étiquette `kernel.reset` : une exécution
interrompue avant sa fin ne laisse rien derrière elle une fois les services réinitialisés.
