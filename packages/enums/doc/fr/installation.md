<!-- phil-dev source-sha256=3e13812700599c86b9cc7a07635455ebf8af1df1571b61af6159117ac0317a77 -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Les traits utilisent `array_any()` et `array_first()`, et la bibliothèque est testée sur PHP 8.5. |
| `ext-mbstring` | Les libellés par défaut de `stringOptions()` changent la casse de noms qui peuvent contenir des lettres hors ASCII. |

La bibliothèque n'a aucune dépendance à un paquet. Ses extensions PHPStan utilisent les propres classes de PHPStan, que
PHPStan fournit lorsqu'il les charge.

## Installer

```sh
composer require phil/enums
```

Rien à enregistrer : la bibliothèque est un ensemble de traits, d'attributs et un petit lecteur de métadonnées. Utilisez
un trait sur une enum, et ses méthodes sont là.

## Tous les traits d'un coup

`AsEnumerableEnumTrait` apporte tous les traits sauf `AsStringSelectableEnumTrait` :

```php
use Phil\Enums\Traits\AsEnumerableEnumTrait;

enum Status: string
{
    use AsEnumerableEnumTrait;

    case DRAFT = 'draft';
    case PUBLISHED = 'published';
}

Status::values();               // ['draft', 'published']
Status::names();                // ['DRAFT', 'PUBLISHED']
Status::options();              // ['DRAFT' => 'draft', 'PUBLISHED' => 'published']
Status::DRAFT();                // 'draft'
Status::DRAFT->is(Status::DRAFT); // true; strict, so is('draft') is false
Status::fromName('PUBLISHED');  // Status::PUBLISHED
Status::isBacked();             // true
```

Préférez les traits individuels dont une enum a besoin : chacun ajoute des méthodes publiques à l'API de l'enum, et une
enum qui ne fait qu'énumérer ses valeurs dans un message d'erreur a besoin de `AsValuableEnumTrait` et de rien d'autre.

## PHPStan

Incluez l'extension afin que PHPStan connaisse les appels statiques de cas et les accesseurs de métadonnées — voir
[PHPStan](phpstan.md).
