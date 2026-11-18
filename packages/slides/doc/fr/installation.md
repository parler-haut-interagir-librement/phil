<!-- phil-dev source-sha256=99e1f629d6d6d22bdb1b30f95131699b05531b835256ef0ead51d63e585d2a04 -->
# Installation

## Prérequis

| Paquet | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent, avec `ext-mbstring` | Les classes en lecture seule ; la longueur des textes de directives |
| `phil/ujo` | `^0.1` | Le document, ses blocs, la classe du service `Ujo` |
| `phil/ujo-bundle` | `^0.1` | Le service `Ujo` qui rend chaque diapositive, tel que configuré par `phil_ujo` |
| `phil/markup` | `^0.2` | Markdown et les autres formats lus en UJO, l'en-tête YAML |
| `phil/paged-media` | `^0.1` | La règle `@page` d'une présentation imprimée |
| `symfony/config`, `symfony/dependency-injection` | `^8.2` | Le bundle et sa configuration |
| `symfony/http-foundation`, `symfony/http-kernel`, `symfony/routing` | `^8.2` | Les routes du présentateur |
| `symfony/ux-twig-component` | `^3.7` | La recette `Button` avec laquelle les gabarits dessinent leurs boutons et leurs liens |
| `twig/twig` | `^3.21` | Les gabarits et les fonctions Twig |
| `symfony/twig-bundle` | facultatif | Les gabarits sous `@PhilSlides`, `DeckPrint` |
| `symfony/asset-mapper`, `symfony/stimulus-bundle` | facultatif | Les contrôleurs et la feuille de style, sans étape de construction |
| `symfony/translation` | facultatif | Les libellés en anglais et en français |
| `phil/paged-media-bundle` | facultatif | `DeckPdf`, le PDF d'une présentation par le moteur d'impression configuré |
| `symfony/security-bundle` | facultatif | Le rôle que demandent les routes du présentateur ; sans lui, elles sont refusées à tous |
| `phil/ux-kit`, ou le kit Shadcn de `symfony/ux-toolkit` | l'un des deux | La recette `Button`, installée par le Toolkit de Symfony UX |

## Activer le bundle

```sh
composer require phil/slides
```

Symfony Flex enregistre le bundle et copie sa configuration depuis la recette. Sans Flex :

```php
// config/bundles.php
return [
    // …
    Phil\UjoBundle\PhilUjoBundle::class => ['all' => true],
    Phil\Slides\PhilSlidesBundle::class => ['all' => true],
];
```

`phil/ujo-bundle` doit être activé aussi : sans son service `Ujo`, le conteneur s'arrête avec un message qui le dit.

## La recette

`recipe/0.1` enregistre le bundle et copie deux fichiers :

- `config/packages/phil_slides.yaml` : des documents coupés à leurs séparateurs ou, à défaut, à leurs titres de niveaux
  1 et 2 ; des diapositives en 16:9 ; les routes du présentateur pour `ROLE_SLIDES_PRESENTER`. Chaque autre option garde
  sa valeur par défaut ; voir [Configuration](configuration.md) ;
- `config/routes/phil_slides.yaml` : l'import des routes du présentateur sous `/presenter`, en commentaire.
  Décommentez-le dès qu'un service implémente `PresentedDecks` ; voir
  [Vue du présentateur](presenter.md#les-routes-du-présentateur).

## Le kit

Les gabarits dessinent chaque bouton et chaque lien de leurs commandes avec la recette `Button` du kit que l'application
a installé, par le Toolkit de Symfony UX :

```sh
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
# or, with the Shadcn kit on Tailwind
php bin/console ux:install button --kit=shadcn
```

La recette est appelée par le nom que lui donne le Toolkit : avec un sous-répertoire de composants `ui` dans la
configuration `ux_toolkit`, `ui:Button`. Sans le Toolkit, nommez le sous-répertoire avec
`phil_slides.kit_subdirectory`. Chaque bouton garde son libellé, son action Stimulus, sa cible et son état
`aria-pressed` dans l'un ou l'autre kit ; le kit donne l'apparence.

## Les contrôleurs et la feuille de style

Les contrôleurs `phil--slides--deck` et `phil--slides--presenter` et la feuille de style `styles/slides.css` sont
fournis dans `assets/`, déclarés dans son `package.json`. Avec AssetMapper, le bundle associe ce dossier à
`@phil/slides` ; la recette Flex de Symfony UX ajoute les deux contrôleurs à `assets/controllers.json`, avec la feuille
de style importée, ou l'application les enregistre à la main. Ils ont besoin de `@hotwired/stimulus` 3.

La feuille de style habille les diapositives par-dessus celle de PHIL UX, `phil/ux-css` : elle utilise ses jetons
`--phil-*`, sinon les jetons du kit Shadcn — `--background`, `--foreground`, `--border` —, sinon les couleurs du
système, et son style sans classe pour les liens et les titres. Ses états sont des classes de la forme `isX` et `hasX`
de PHIL UX, et chaque couleur qu'elle mélange l'est en OKLCH.
