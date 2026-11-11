<!-- phil-dev source-sha256=34837bddf43aec6580690b07412c124c87cb9e7619fef069bb5a321b5305201b -->
# Installation

## Prérequis

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que le bundle déclare et sur lequel il est testé |
| `phil/ujo` | ^0.1 | Le modèle, les vérifications et le rendu |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | La classe du bundle et sa configuration |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | `UjoType` |
| `symfony/validator` | ^8.2 | `ValidUjo` |
| `twig/twig` | ^3.21, facultatif | Les filtres et les fonctions `ujo_html` et `ujo_text` |

## Installer

```sh
composer require phil/ujo-bundle
```

Sans Symfony Flex, activez le bundle dans `config/bundles.php` :

```php
return [
    // …
    Phil\UjoBundle\PhilUjoBundle::class => ['all' => true],
];
```

Le bundle fonctionne sans configuration ; voir [configuration.md](configuration.md) pour ses options. Les traductions
de ses messages, en anglais et en français, sont dans son répertoire `translations/`, que le framework trouve de
lui-même.
