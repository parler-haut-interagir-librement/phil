<!-- phil-dev source-sha256=8b8f8467dc629a237d10c2affccaf74a1e4590d6d451d08b91e190f55ac0b01f -->
# Installation

## Prérequis

| Paquet | Version | Sert à |
|---|---|---|
| PHP | 8.5 ou plus récent | |
| `phil/slug` | ^0.1 | Les slugs et les générateurs |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | La configuration et les services |
| `twig/twig` | ^3.21, facultatif | Le filtre `phil_slug`, enregistré seulement quand Twig est installé |
| `doctrine/dbal` | ^4.5, facultatif | L'[historique des anciens slugs](history.md), enregistré seulement quand il est activé |
| `doctrine/orm` | ^3.7, facultatif | La table de l'historique ajoutée au schéma que l'ORM génère |
| `psr/clock` | ^1.0, facultatif | L'horloge de l'historique ; `symfony/clock` fournit le service `clock` |

## Enregistrer

```php
// config/bundles.php
return [
    // ...
    Phil\SlugBundle\PhilSlugBundle::class => ['all' => true],
];
```

Sans aucune configuration, le bundle enregistre un seul générateur nommé `default` : le générateur translittérant de
`phil/slug`, sans locale particulière, d'au plus 255 caractères, qui ne retire aucun mot.
