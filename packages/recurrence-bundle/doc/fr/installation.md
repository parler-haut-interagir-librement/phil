<!-- phil-dev source-sha256=bb551d7588b6c9df313e019933332cf1d766be972f3baef5dec7b77137cded70 -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `ext-intl` | toute version | Jours, mois et dates écrits par ICU ; messages ICU |
| `phil/recurrence` | ^0.1@dev | Les règles |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | Le formulaire |
| `symfony/dependency-injection` | ^8.2 | La classe du bundle |
| `symfony/translation-contracts` | ^3.6 | Les règles en mots, par le traducteur de l'application |
| `doctrine/dbal`, `twig/twig` | facultatifs | Le type de colonne, les fonctions Twig |

```sh
composer require phil/recurrence-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\RecurrenceBundle\PhilRecurrenceBundle::class => ['all' => true],
];
```

Le bundle n'a aucune clé de configuration. Avec Symfony UX, le contrôleur Stimulus se trouve dans `assets/`, déclaré
dans son `package.json`.
