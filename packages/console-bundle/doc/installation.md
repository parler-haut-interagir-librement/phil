# Installation

## Requirements

- PHP 8.5 or later;
- Symfony 8.2 or later: the console, the event dispatcher and the dependency injection components, with
  FrameworkBundle's console application;
- optionally Monolog, with MonologBundle, for `--debug-phil`.

## Installing

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

The bundle works without configuration: the guards apply as soon as a command carries an attribute, and the options
are added to every command.

## Worker mode

The services keep nothing between two commands. What one run needs until its end — its start, its memory, the stream
filters of its timestamps, the debug handlers — is kept by its input and dropped when it ends. The listener and the
debug switch are tagged `kernel.reset`: a run interrupted before its end leaves nothing behind once the services are
reset.
