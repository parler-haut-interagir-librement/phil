<!-- phil-dev source-sha256=8ca51534627dbf9ecfededf00b49003e70b422190e8a0635b32f437ea3bc5bb6 -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Comme `phil/typography` |
| `phil/typography` | Les typographes, les correcteurs et les motifs de césure |
| `symfony/config`, `symfony/dependency-injection` 8.2 | La configuration et les services |
| `twig/twig` 3.21, facultatif | Les filtres `typography` et `typography_text` |
| `symfony/translation-contracts` 3.6 | La locale courante, lue dans le `LocaleSwitcher` de Symfony |
| `symfony/translation` 8.2, facultatif | Le `LocaleSwitcher`, qui donne aux filtres la locale de la requête |
| `phil/ujo-bundle`, facultatif | La passe typographique sur le HTML des documents UJO (Universal JSON Output) |

## Installer

```sh
composer require phil/typography-bundle
```

Symfony Flex active le bundle ; sans Flex, ajoutez-le à `config/bundles.php` :

```php
return [
    // ...
    Phil\TypographyBundle\PhilTypographyBundle::class => ['all' => true],
];
```

Sans configuration, le typographe par défaut est le préréglage de la locale par défaut du noyau, avec la césure. Les
filtres, et le HTML des documents UJO quand `phil/ujo-bundle` est installé, sont corrigés dans la locale de la
requête.
