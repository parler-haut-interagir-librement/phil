<!-- phil-dev source-sha256=3d97f2135f12f6ef9c87901ee06dea467e174fb8bea3ce44c23883966669a2b8 -->
# Installation

## La feuille de style

Chaque recette tire son apparence de phil-ux. Installez-la une fois, comme l'explique son [guide d'installation](https://github.com/parler-haut-interagir-librement/ux-css/blob/main/doc/installation.md)
— `composer require phil/ux-css` avec AssetMapper, `npm install @phil/ux-css`, ou une copie de
`dist/phil-ux.css` — et liez-la depuis le gabarit de base.

## Avec `ux:install`

Le Toolkit télécharge un kit depuis un dépôt GitHub public, en archive zip, sans authentification :

```sh
php bin/console ux:install coming-soon --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install logo --kit=github.com/parler-haut-interagir-librement/ux-kit:v0.1.0
```

Une recette apporte les recettes dont elle dépend : `coming-soon` installe aussi `Logo`, `AnimatedHeadline` et
`Illustration`. Les composants vont dans `templates/components/` et les contrôleurs dans `assets/controllers/`, ou dans
les dossiers et le sous-dossier que fixe la configuration `ux_toolkit` : avec un sous-dossier `ui`, les copies deviennent
`<twig:ui:Logo>` et `ui--animated-headline`, réécrits par le Toolkit dans chaque fichier copié. La commande affiche les
paquets dont les recettes ont besoin ; elle n'en installe aucun.

`ux:install` lit un kit sur GitHub ou parmi les kits du Toolkit, jamais depuis un chemin local : `--kit=../ux-kit` est
refusé comme nom de kit invalide.

## Tant que le dépôt n'est pas publié

Copiez les fichiers des recettes dont la page a besoin, depuis ce dossier, en gardant leurs noms :

| Recette | Copier | Vers |
|---|---|---|
| Logo | `logo/templates/components/Logo.html.twig` | `templates/components/Logo.html.twig` |
| AnimatedHeadline | `animated-headline/templates/components/AnimatedHeadline.html.twig` | `templates/components/AnimatedHeadline.html.twig` |
| AnimatedHeadline | `animated-headline/assets/controllers/animated_headline_controller.js` | `assets/controllers/animated_headline_controller.js` |
| Illustration | `illustration/templates/components/Illustration.html.twig` | `templates/components/Illustration.html.twig` |
| AutosaveStatus | `autosave-status/templates/components/AutosaveStatus.html.twig` | `templates/components/AutosaveStatus.html.twig` |
| ReactionButton | `reaction-button/templates/components/ReactionButton.html.twig` | `templates/components/ReactionButton.html.twig` |
| ReactionPicker | `reaction-picker/templates/components/ReactionPicker.html.twig` | `templates/components/ReactionPicker.html.twig` |
| ComingSoon | `coming-soon/templates/components/ComingSoon.html.twig` | `templates/components/ComingSoon.html.twig` |
| Badge | `badge/templates/components/` | `templates/components/` |
| Button | `button/templates/components/` | `templates/components/` |
| ButtonGroup | `button-group/templates/components/` | `templates/components/` |
| Card | `card/templates/components/` | `templates/components/` |
| Empty | `empty/templates/components/` | `templates/components/` |
| Popover | `popover/templates/components/` | `templates/components/` |
| Popover | `popover/assets/controllers/` | `assets/controllers/` |
| QrCode | `qr-code/templates/components/` | `templates/components/` |
| Tabs | `tabs/templates/components/` | `templates/components/` |
| Tabs | `tabs/assets/controllers/` | `assets/controllers/` |
| Accordion | `accordion/templates/components/` | `templates/components/` |
| Accordion | `accordion/assets/controllers/` | `assets/controllers/` |
| Dialog | `dialog/templates/components/` | `templates/components/` |
| Dialog | `dialog/assets/controllers/` | `assets/controllers/` |
| Sheet | `sheet/templates/components/` | `templates/components/` |
| Switch | `switch/templates/components/` | `templates/components/` |
| Tooltip | `tooltip/templates/components/` | `templates/components/` |
| Tooltip | `tooltip/assets/controllers/` | `assets/controllers/` |
| ColorPicker | `color-picker/templates/components/` | `templates/components/` |
| ColorPicker | `color-picker/assets/controllers/` | `assets/controllers/` |
| DatePicker | `date-picker/templates/components/` | `templates/components/` |
| DatePicker | `date-picker/assets/controllers/` | `assets/controllers/` |
| CopyButton | `copy-button/templates/components/` | `templates/components/` |
| CopyButton | `copy-button/assets/controllers/` | `assets/controllers/` |
| AnimatedButton | `animated-button/templates/components/` | `templates/components/` |
| Stepper | `stepper/templates/components/` | `templates/components/` |
| ActivityCalendar | `activity-calendar/templates/components/` | `templates/components/` |
| Tree | `tree/templates/components/` | `templates/components/` |
| Tree | `tree/assets/controllers/` | `assets/controllers/` |
| Rating | `rating/templates/components/` | `templates/components/` |
| NumberInput | `number-input/templates/components/` | `templates/components/` |
| NumberInput | `number-input/assets/controllers/` | `assets/controllers/` |
| FileInput | `file-input/templates/components/` | `templates/components/` |
| FileInput | `file-input/assets/controllers/` | `assets/controllers/` |
| Tag | `tag/templates/components/` | `templates/components/` |
| Tag | `tag/assets/controllers/` | `assets/controllers/` |
| RelativeTime | `relative-time/templates/components/` | `templates/components/` |
| RelativeTime | `relative-time/assets/controllers/` | `assets/controllers/` |
| ProgressRing | `progress-ring/templates/components/` | `templates/components/` |
| Sparkline | `sparkline/templates/components/` | `templates/components/` |
| BeforeAfter | `before-after/templates/components/` | `templates/components/` |
| BeforeAfter | `before-after/assets/controllers/` | `assets/controllers/` |
| Pagination | `pagination/templates/components/` | `templates/components/` |
| Carousel | `carousel/templates/components/` | `templates/components/` |
| Carousel | `carousel/assets/controllers/` | `assets/controllers/` |
| Spinner | `spinner/templates/components/` | `templates/components/` |
| Skeleton | `skeleton/templates/components/` | `templates/components/` |
| Alert | `alert/templates/components/` | `templates/components/` |
| Avatar | `avatar/templates/components/` | `templates/components/` |
| Input | `input/templates/components/` | `templates/components/` |
| Label | `label/templates/components/` | `templates/components/` |
| Select | `select/templates/components/` | `templates/components/` |
| Select | `select/assets/controllers/` | `assets/controllers/` |
| Separator | `separator/templates/components/` | `templates/components/` |
| Textarea | `textarea/templates/components/` | `templates/components/` |
| Checkbox | `checkbox/templates/components/` | `templates/components/` |
| Breadcrumb | `breadcrumb/templates/components/` | `templates/components/` |
| DropdownMenu | `dropdown-menu/templates/components/` | `templates/components/` |
| DropdownMenu | `dropdown-menu/assets/controllers/` | `assets/controllers/` |
| Table | `table/templates/components/` | `templates/components/` |
| AlertDialog | `alert-dialog/templates/components/` | `templates/components/` |
| AspectRatio | `aspect-ratio/templates/components/` | `templates/components/` |
| Collapsible | `collapsible/templates/components/` | `templates/components/` |
| Field | `field/templates/components/` | `templates/components/` |
| Item | `item/templates/components/` | `templates/components/` |
| Kbd | `kbd/templates/components/` | `templates/components/` |
| NativeSelect | `native-select/templates/components/` | `templates/components/` |
| Progress | `progress/templates/components/` | `templates/components/` |
| RadioGroup | `radio-group/templates/components/` | `templates/components/` |
| ScrollArea | `scroll-area/templates/components/` | `templates/components/` |
| Toggle | `toggle/templates/components/` | `templates/components/` |
| ToggleGroup | `toggle-group/templates/components/` | `templates/components/` |
| LoginForm | `login-form/templates/components/` | `templates/components/` |
| SignupForm | `signup-form/templates/components/` | `templates/components/` |
| Chart | `chart/templates/components/` | `templates/components/` |
| ChartJs | `chart-js/templates/components/` | `templates/components/` |

Puis :

```sh
composer require symfony/ux-twig-component symfony/stimulus-bundle
```

Le StimulusBundle enregistre chaque contrôleur de `assets/controllers/` sous son nom de fichier : le contrôleur copié
est `animated-headline`, comme le gabarit le nomme. `AlertDialog` tourne sur le contrôleur de `Dialog`, copié avec
lui ; `ChartJs` demande `composer require symfony/ux-chartjs`.
