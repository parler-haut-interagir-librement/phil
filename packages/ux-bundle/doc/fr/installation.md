<!-- phil-dev source-sha256=97fb0a2d74c2ae86486498acfdee37c98783fb887e6667b181f45d14e034845c -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `symfony/dependency-injection`, `symfony/config`, `symfony/http-kernel` | ^8.2 | La classe du bundle et sa configuration |
| `symfony/twig-bundle`, `twig/twig` | ^8.2, ^3.30 | Le thème de formulaire et son espace de noms |
| `phil/ux-css` | ^0.5 | La feuille de style que le bundle associe |
| `symfony/form` | ^8.2 | Les formulaires dont le thème produit le rendu ; Symfony n'enregistre les thèmes de formulaire qu'avec lui |
| `symfony/asset-mapper` | facultatif | La feuille de style servie sous `phil-ux/phil-ux.css` |

```sh
composer require phil/ux-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\UxBundle\PhilUxBundle::class => ['all' => true],
];
```

## Configuration

```yaml
# config/packages/phil_ux.yaml
phil_ux:
    form_theme: true    # false: the forms keep the themes of twig.form_themes only
```

`form_theme` est lue pendant que les bundles ajoutent leur configuration en tête, avant la résolution des variables
d'environnement : écrivez-la comme un booléen simple, non comme une variable d'environnement.

## La feuille de style

Avec AssetMapper, le bundle associe le répertoire `dist/` de phil/ux-css sous `phil-ux/`. Liez-la dans le gabarit de
base :

```twig
<link rel="stylesheet" href="{{ asset('phil-ux/phil-ux.css') }}">
```

ou listez-la dans la table d'importation et importez-la depuis le script d'entrée :

```php
// importmap.php
return [
    // ...
    'phil-ux/phil-ux.css' => ['path' => 'phil-ux/phil-ux.css', 'type' => 'css'],
];
```

```js
// assets/app.js
import 'phil-ux/phil-ux.css';
```

La recette Flex de phil/ux-css écrit la même association dans `config/packages/phil_ux_css.yaml` ; avec le bundle
installé, ce fichier n'est pas nécessaire, et le garder ne change rien. Chargez la feuille de style avant les styles
propres à l'application : ses règles sont dans des couches de cascade, si bien que les styles de l'application, écrits
hors de toute couche, l'emportent de toute façon. Les couches, les jetons et les thèmes sont décrits dans la
documentation de phil/ux-css.

Sans AssetMapper, l'application importe `vendor/phil/ux-css/dist/phil-ux.css` par sa propre chaîne.

## Les composants

Le bundle n'installe aucun composant. Les composants Twig de PHIL UX sont des recettes de phil/ux-kit, que le Symfony UX
Toolkit copie dans l'application :

```sh
php bin/console ux:install field --kit=github.com/parler-haut-interagir-librement/ux-kit
```

## L'administration

Les sites construits sur les bundles PHIL utilisent EasyAdmin comme administration. Les pages d'EasyAdmin produisent le
rendu de leurs formulaires avec le thème propre à EasyAdmin, listé avec `only`, si bien que le thème de formulaire PHIL
UX ne les atteint pas. `phil/ux-easyadmin-bundle` habille l'administration des couleurs de PHIL UX.
