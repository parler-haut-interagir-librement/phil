<!-- phil-dev source-sha256=223c44d67a5b798ab61b6445218eba597c497f4ba09f9c0b2c21c26735274f7b -->
# Installation

## Tailwind et le kit Shadcn

Les recettes sont écrites pour Tailwind CSS 4. Elles lisent les jetons du kit Shadcn (`bg-background`,
`text-foreground`, `text-muted-foreground`, `ring-ring`, `--radius`, `--chart-1` à `--chart-5`) et les utilitaires
`animate-in` de tw-animate-css 1.3.4 ou plus récent, dont `slide-in-from-end`, logique, suit le sens de la page.
Installez Tailwind 4, tw-animate-css et la feuille de style du kit Shadcn comme le dit le guide d'installation du kit
Shadcn, puis faites lire les composants installés par Tailwind :

```css
/* assets/styles/app.css */
@import 'tailwindcss';
@import 'tw-animate-css';
@source '../../templates/components';
```

Les recettes dotées d'un contrôleur Stimulus (`AnimatedHeadline`, `Carousel`, `Celebration`, `Lightbox`, `ColorPicker`,
`CopyButton`, `Tree`, `NumberInput`, `FileInput`, `Tag`, `RelativeTime`, `BeforeAfter`, `ThemeSwitcher`, `StickyHeader` et l'aperçu flou d'`Image`) ont
besoin du StimulusBundle, qui charge les contrôleurs que `ux:install` copie dans `assets/controllers/`.

## Avec `ux:install`

Les recettes s'installent avec Symfony UX Toolkit 3.7.1 ou plus récent :

```sh
php bin/console ux:install coming-soon --kit=github.com/parler-haut-interagir-librement/ux-kit-shadcn
```

`coming-soon` installe aussi `Logo`, `AnimatedHeadline` et `Illustration`, dans les répertoires et le sous-répertoire de
la configuration `ux_toolkit` de l'application. Avec un sous-répertoire, `ui` par exemple, le Toolkit préfixe les
identifiants des contrôleurs du kit partout où un gabarit les nomme, `data-controller="ui--carousel"` et
`data-ui--carousel-target`, y compris le `data-controller` écrit dans une table Twig, que la 3.7.1 est la première
version à réécrire ; les contrôleurs d'un bundle, comme le contrôleur d'enregistrement automatique de
`phil/forms-autosave`, gardent leurs identifiants. `ux:install` lit un kit depuis GitHub ou parmi les kits propres au
Toolkit, jamais depuis un chemin local.

Le kit Shadcn et ce kit-ci peuvent être installés tous deux dans une application : les recettes Shadcn viennent du kit
`shadcn` du Toolkit, les autres de celui-ci. Un nom de recette que les deux kits contiennent, `Carousel`, écrit les
mêmes fichiers : installez-la depuis le kit dont la page veut le comportement, et répondez non quand `ux:install` offre
de l'écraser depuis l'autre. Le champ de date est le `DatePicker` du kit Shadcn ; ce kit-ci n'en a aucun.

## Tant que le dépôt n'est pas publié

Copiez les fichiers des recettes dont la page a besoin, depuis ce répertoire, en gardant leurs noms :

| Recette | Copier | Vers |
|---|---|---|
| Logo | `logo/templates/components/Logo.html.twig` | `templates/components/Logo.html.twig` |
| AnimatedHeadline | `animated-headline/templates/components/AnimatedHeadline.html.twig` | `templates/components/AnimatedHeadline.html.twig` |
| AnimatedHeadline | `animated-headline/assets/controllers/animated_headline_controller.js` | `assets/controllers/animated_headline_controller.js` |
| Illustration | `illustration/templates/components/Illustration.html.twig` | `templates/components/Illustration.html.twig` |
| Image | `image/templates/components/` | `templates/components/` |
| QrCode | `qr-code/templates/components/QrCode.html.twig` | `templates/components/QrCode.html.twig` |
| AutosaveStatus | `autosave-status/templates/components/AutosaveStatus.html.twig` | `templates/components/AutosaveStatus.html.twig` |
| ReactionButton | `reaction-button/templates/components/ReactionButton.html.twig` | `templates/components/ReactionButton.html.twig` |
| ReactionPicker | `reaction-picker/templates/components/ReactionPicker.html.twig` | `templates/components/ReactionPicker.html.twig` |
| ColorPicker | `color-picker/templates/components/ColorPicker.html.twig` | `templates/components/ColorPicker.html.twig` |
| CopyButton | `copy-button/templates/components/CopyButton.html.twig` | `templates/components/CopyButton.html.twig` |
| Celebration | `celebration/templates/components/Celebration.html.twig` | `templates/components/Celebration.html.twig` |
| Celebration | `celebration/assets/controllers/celebration_controller.js` | `assets/controllers/celebration_controller.js` |
| Celebration | `celebration/assets/controllers/celebration_effects.js` | `assets/controllers/celebration_effects.js` |
| Celebration | `celebration/assets/controllers/celebration_worker.js` | `assets/controllers/celebration_worker.js` |
| AnimatedButton | `animated-button/templates/components/AnimatedButton.html.twig` | `templates/components/AnimatedButton.html.twig` |
| Stepper | `stepper/templates/components/Stepper.html.twig` | `templates/components/Stepper.html.twig` |
| ActivityCalendar | `activity-calendar/templates/components/ActivityCalendar.html.twig` | `templates/components/ActivityCalendar.html.twig` |
| Tree | `tree/templates/components/Tree.html.twig` | `templates/components/Tree.html.twig` |
| Rating | `rating/templates/components/Rating.html.twig` | `templates/components/Rating.html.twig` |
| NumberInput | `number-input/templates/components/NumberInput.html.twig` | `templates/components/NumberInput.html.twig` |
| FileInput | `file-input/templates/components/FileInput.html.twig` | `templates/components/FileInput.html.twig` |
| Tag | `tag/templates/components/Tag.html.twig` | `templates/components/Tag.html.twig` |
| RelativeTime | `relative-time/templates/components/RelativeTime.html.twig` | `templates/components/RelativeTime.html.twig` |
| ProgressRing | `progress-ring/templates/components/ProgressRing.html.twig` | `templates/components/ProgressRing.html.twig` |
| Sparkline | `sparkline/templates/components/Sparkline.html.twig` | `templates/components/Sparkline.html.twig` |
| BeforeAfter | `before-after/templates/components/BeforeAfter.html.twig` | `templates/components/BeforeAfter.html.twig` |
| Carousel | `carousel/templates/components/` | `templates/components/` |
| Carousel | `carousel/assets/controllers/carousel_controller.js` | `assets/controllers/carousel_controller.js` |
| ThemeSwitcher | `theme-switcher/templates/components/` | `templates/components/` |
| ThemeSwitcher | `theme-switcher/assets/controllers/theme_switcher_controller.js` | `assets/controllers/theme_switcher_controller.js` |
| StickyHeader | `sticky-header/templates/components/StickyHeader.html.twig` | `templates/components/StickyHeader.html.twig` |
| StickyHeader | `sticky-header/assets/controllers/sticky_header_controller.js` | `assets/controllers/sticky_header_controller.js` |
| Timeline | `timeline/templates/components/` | `templates/components/` |
| FloatingLabel | `floating-label/templates/components/FloatingLabel.html.twig` | `templates/components/FloatingLabel.html.twig` |
| ComingSoon | `coming-soon/templates/components/ComingSoon.html.twig` | `templates/components/ComingSoon.html.twig` |
