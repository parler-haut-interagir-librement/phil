<!-- phil-dev source-sha256=78994577b82e680ed541b76d6ba0b5b7db42aefe10e52f66ab1ed6543ad39905 -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Comme `phil/text-diff` |
| `phil/text-diff` | La comparaison des textes et des documents |
| `phil/ujo` | Les documents que lit `text_diff_document` |
| `symfony/config`, `symfony/dependency-injection` 8.2 | La configuration et les services |
| `twig/twig` 3.21, facultatif | Les filtres `text_diff` et `text_diff_document` |
| `phil/ujo-bundle`, facultatif | Les documents lus et rendus avec les types de blocs de l'application |
| `symfony/html-sanitizer` 8.2, facultatif | Le HTML qu'un gabarit ne marque pas comme sûr, assaini plutôt qu'échappé |
| `symfony/stimulus-bundle`, facultatif | Le contrôleur `comparison` |
| `symfony/asset-mapper` 8.2, facultatif | Le contrôleur et la feuille de style servis sans étape de construction |

## Installation

```sh
composer require phil/text-diff-bundle
```

Symfony Flex active le bundle ; sans Flex, ajoutez-le à `config/bundles.php` :

```php
return [
    // ...
    Phil\TextDiffBundle\PhilTextDiffBundle::class => ['all' => true],
];
```

Sans configuration, le comparateur recherche jusqu'à mille modifications et `text_diff` compare par mots.

## Le contrôleur

Avec AssetMapper, le bundle associe son dossier `assets/src` à `@phil/text-diff-bundle/`. StimulusBundle enregistre le
contrôleur à partir du fichier `assets/controllers.json` de l'application :

```json
{
    "controllers": {
        "@phil/text-diff-bundle": {
            "comparison": { "enabled": true, "fetch": "lazy" }
        }
    },
    "entrypoints": []
}
```

Le contrôleur s'appelle alors `phil--text-diff-bundle--comparison`, chargé la première fois qu'une page l'utilise. Avec
une autre chaîne de construction, enregistrez `vendor/phil/text-diff-bundle/assets/src/comparison_controller.js` sous
ce nom.

## La feuille de style

AssetMapper associe aussi `assets/styles` à `phil-text-diff/` ; une mise en page y fait un lien :

```twig
<link rel="stylesheet" href="{{ asset('phil-text-diff/text-diff.css') }}">
```

Avec une autre chaîne de construction, importez `vendor/phil/text-diff-bundle/assets/styles/text-diff.css`. Voir
[Mise en forme](styling.md).
