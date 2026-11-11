<!-- phil-dev source-sha256=156620183e4a5e8ea5b89a1846dee817e043cae5549b0088beb9597d5020bbb6 -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Les classes en lecture seule, les constantes typées, `array_all()` |
| `ext-mbstring` | Les positions des changements comptées en points de code |
| `phil/ujo` | Les documents comparés bloc par bloc, et leur rendu |

## Installation

```sh
composer require phil/text-diff
```

C'est une bibliothèque, pas un bundle : il n'y a rien à enregistrer. Construisez un `TextDiff` là où les différences
sont affichées, une seule fois, et gardez-le ; il ne conserve aucun état entre deux comparaisons.

```php
use Phil\TextDiff\TextDiff;

$differ = new TextDiff();          // at most 1000 edits searched for
$strict = new TextDiff(maxEdits: 200);
```

Dans une application Symfony, `phil/text-diff-bundle` l'enregistre comme service, avec les filtres Twig `text_diff` et
`text_diff_document` et un contrôleur Stimulus qui parcourt les changements. Sans le bundle, déclarez-le comme service
si plusieurs classes l'utilisent :

```yaml
services:
    Phil\TextDiff\TextDiff: ~
```
