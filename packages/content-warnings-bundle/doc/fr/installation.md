<!-- phil-dev source-sha256=de624fb30540f846841bd51fcda499286b33893ef620f4243a2be2bb844ff891 -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `ext-intl` | toute version | La locale courante dans laquelle les libellés sont lus |
| `phil/content-warnings` | ^0.1@dev | Le catalogue, les avertissements, les préférences et les décisions |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | La configuration et la classe du bundle |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | Les champs |
| `symfony/service-contracts` | ^3.6 | La réinitialisation du magasin en mémoire entre les requêtes |
| `doctrine/dbal` | facultatif | Le type de colonne `phil_content_warnings` |
| `symfony/http-foundation` | facultatif | `SessionWarningPreferences`, le magasin par défaut |
| `twig/twig` | facultatif | Les fonctions, l'affichage replié et le thème de formulaire |

```sh
composer require phil/content-warnings-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\ContentWarningsBundle\PhilContentWarningsBundle::class => ['all' => true],
];
```

Sans configuration, le catalogue est la liste de base de la bibliothèque, et un contenu visé par un avertissement est
replié. Avec Symfony UX, le contrôleur Stimulus et sa feuille de style se trouvent dans `assets/`, déclarés dans son
`package.json`.
