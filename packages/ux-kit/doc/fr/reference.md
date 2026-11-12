<!-- phil-dev source-sha256=c57206d1d6f1ab150a8fc8113c4b719e165fad674bf25995fc049d752d56783d -->
# Référence

| Fichier | Ce que c'est |
|---|---|
| `manifest.json` | Le kit : nom, description, licence, page d'accueil, couleur, icône, dépendances |
| `icon.svg` | L'icône du kit |
| `.gitattributes` | Laisse les fichiers propres au paquet hors de l'archive que télécharge `ux:install`, sauf `src/` |
| `src/PhilUxKitBundle.php` | `Phil\UxKit\PhilUxKitBundle` : le bundle, `phil_ux_kit.image.hosts`, `allow_localhost` et `check`, et les fonctions Twig des recettes d'images |
| `src/Application/ImageOrigin.php` | `Phil\UxKit\Application\ImageOrigin` : d'où vient l'image d'une adresse, ou chaque candidate d'un `srcset`, lue par `phil/url` : `local`, `external` ou `refused` |
| `src/Application/ImageSources.php` | `Phil\UxKit\Application\ImageSources` : les props d'une `Picture` d'après les fichiers d'une image parmi les ressources d'AssetMapper |
| `src/Application/ImageTextCheck.php` | `Phil\UxKit\Application\ImageTextCheck` : ce qu'un développeur a pu laisser de travers dans une image, un texte de remplacement qui est le nom de son fichier, un mot générique ou qui commence par dire que c'est une image, une largeur ou une hauteur absente |
| `src/Infrastructure/Twig/ImageExtension.php` | `image_origin()`, `image_sources()` et `image_check()`, qui journalise les problèmes d'`ImageTextCheck` en avertissements quand `phil_ux_kit.image.check` est actif |
| `src/Test/BrowserTestCase.php` | `Phil\UxKit\Test\BrowserTestCase` : un Chrome sans interface sur la page des recettes d'un kit, et les aides d'un test en navigateur |
| `src/Test/RecipesPage.php` | `Phil\UxKit\Test\RecipesPage` : la racine web de chaque recette d'un kit, avec sa feuille de style, ses contrôleurs et Stimulus |
| `logo/manifest.json`, `logo/README.md` | La recette `Logo` |
| `logo/templates/components/Logo.html.twig` | Le composant `Logo` |
| `animated-headline/manifest.json`, `animated-headline/README.md` | La recette `AnimatedHeadline` |
| `animated-headline/templates/components/AnimatedHeadline.html.twig` | Le composant `AnimatedHeadline` |
| `animated-headline/assets/controllers/animated_headline_controller.js` | Le contrôleur Stimulus `animated-headline`, `next()`, `timing()` et `BUDGET` |
| `illustration/manifest.json`, `illustration/README.md` | La recette `Illustration` |
| `illustration/templates/components/Illustration.html.twig` | Le composant `Illustration` |
| `image/manifest.json`, `image/README.md` | La recette `Image` |
| `image/templates/components/` | Les composants `Image` et `Picture`, et `Image:Figure`, la figure de leur description, de leur légende, de leur crédit, de leur licence et de leur création |
| `image/assets/controllers/image_preview_controller.js` | Le contrôleur Stimulus `image-preview`, qui peint l'aperçu flou d'une image par le CSSOM jusqu'à ce qu'elle soit chargée |
| `gallery/manifest.json`, `gallery/README.md` | La recette `Gallery` |
| `gallery/templates/components/Gallery.html.twig` | Le composant `Gallery` |
| `lightbox/manifest.json`, `lightbox/README.md` | La recette `Lightbox` |
| `lightbox/templates/components/Lightbox.html.twig` | Le composant `Lightbox` |
| `lightbox/assets/controllers/lightbox_controller.js` | Le contrôleur Stimulus `lightbox`, qui ouvre et ferme le dialogue là où le navigateur n'exécute ni les invocateurs ni `closedby` |
| `autosave-status/manifest.json`, `autosave-status/README.md` | La recette `AutosaveStatus` |
| `autosave-status/templates/components/AutosaveStatus.html.twig` | Le composant `AutosaveStatus` |
| `reaction-button/manifest.json`, `reaction-button/README.md` | La recette `ReactionButton` |
| `reaction-button/templates/components/ReactionButton.html.twig` | Le composant `ReactionButton` |
| `reaction-picker/manifest.json`, `reaction-picker/README.md` | La recette `ReactionPicker` |
| `reaction-picker/templates/components/ReactionPicker.html.twig` | Le composant `ReactionPicker` |
| `coming-soon/manifest.json`, `coming-soon/README.md` | Le bloc `ComingSoon` |
| `coming-soon/templates/components/ComingSoon.html.twig` | Le composant `ComingSoon` |
| `badge/manifest.json`, `badge/README.md` | La recette `Badge` |
| `badge/templates/components/` | Le composant `Badge` et ses parties |
| `button/manifest.json`, `button/README.md` | La recette `Button` |
| `button/templates/components/` | Le composant `Button` et ses parties |
| `button-group/manifest.json`, `button-group/README.md` | La recette `ButtonGroup` |
| `button-group/templates/components/` | Le composant `ButtonGroup` et ses parties |
| `card/manifest.json`, `card/README.md` | La recette `Card` |
| `card/templates/components/` | Le composant `Card` et ses parties |
| `empty/manifest.json`, `empty/README.md` | La recette `Empty` |
| `empty/templates/components/` | Le composant `Empty` et ses parties |
| `popover/manifest.json`, `popover/README.md` | La recette `Popover` |
| `popover/templates/components/` | Le composant `Popover` et ses parties |
| `popover/assets/controllers/` | Le contrôleur Stimulus de la recette |
| `qr-code/manifest.json`, `qr-code/README.md` | La recette `QrCode` |
| `qr-code/templates/components/` | Le composant `QrCode` et ses parties |
| `tabs/manifest.json`, `tabs/README.md` | La recette `Tabs` |
| `tabs/templates/components/` | Le composant `Tabs` et ses parties |
| `tabs/assets/controllers/` | Le contrôleur Stimulus de la recette |
| `accordion/manifest.json`, `accordion/README.md` | La recette `Accordion` |
| `accordion/templates/components/` | Le composant `Accordion` et ses parties |
| `accordion/assets/controllers/` | Le contrôleur Stimulus de la recette |
| `dialog/manifest.json`, `dialog/README.md` | La recette `Dialog` |
| `dialog/templates/components/` | Le composant `Dialog` et ses parties |
| `dialog/assets/controllers/` | Le contrôleur Stimulus de la recette |
| `sheet/manifest.json`, `sheet/README.md` | La recette `Sheet` |
| `sheet/templates/components/` | Le composant `Sheet` et ses parties |
| `switch/manifest.json`, `switch/README.md` | La recette `Switch` |
| `switch/templates/components/` | Le composant `Switch` et ses parties |
| `tooltip/manifest.json`, `tooltip/README.md` | La recette `Tooltip` |
| `tooltip/templates/components/` | Le composant `Tooltip` et ses parties |
| `tooltip/assets/controllers/` | Le contrôleur Stimulus de la recette |
| `pagination/manifest.json`, `pagination/README.md` | La recette `Pagination` |
| `pagination/templates/components/` | Le composant `Pagination` et ses parties |
| `carousel/manifest.json`, `carousel/README.md` | La recette `Carousel` |
| `carousel/templates/components/` | Le composant `Carousel` et ses parties |
| `carousel/assets/controllers/` | Le contrôleur Stimulus de la recette |
| `spinner/manifest.json`, `spinner/README.md` | La recette `Spinner` |
| `spinner/templates/components/` | Le composant `Spinner` et ses parties |
| `skeleton/manifest.json`, `skeleton/README.md` | La recette `Skeleton` |
| `skeleton/templates/components/` | Le composant `Skeleton` et ses parties |
| `alert/manifest.json`, `alert/README.md` | La recette `Alert` |
| `alert/templates/components/` | Le composant `Alert` et ses parties |
| `avatar/manifest.json`, `avatar/README.md` | La recette `Avatar` |
| `avatar/templates/components/` | Le composant `Avatar` et ses parties |
| `input/manifest.json`, `input/README.md` | La recette `Input` |
| `input/templates/components/` | Le composant `Input` et ses parties |
| `label/manifest.json`, `label/README.md` | La recette `Label` |
| `label/templates/components/` | Le composant `Label` et ses parties |
| `select/manifest.json`, `select/README.md` | La recette `Select` |
| `select/templates/components/` | Le composant `Select` et ses parties |
| `select/assets/controllers/` | Le contrôleur Stimulus de la recette |
| `separator/manifest.json`, `separator/README.md` | La recette `Separator` |
| `separator/templates/components/` | Le composant `Separator` et ses parties |
| `textarea/manifest.json`, `textarea/README.md` | La recette `Textarea` |
| `textarea/templates/components/` | Le composant `Textarea` et ses parties |
| `checkbox/manifest.json`, `checkbox/README.md` | La recette `Checkbox` |
| `checkbox/templates/components/` | Le composant `Checkbox` et ses parties |
| `breadcrumb/manifest.json`, `breadcrumb/README.md` | La recette `Breadcrumb` |
| `breadcrumb/templates/components/` | Le composant `Breadcrumb` et ses parties |
| `dropdown-menu/manifest.json`, `dropdown-menu/README.md` | La recette `DropdownMenu` |
| `dropdown-menu/templates/components/` | Le composant `DropdownMenu` et ses parties |
| `dropdown-menu/assets/controllers/` | Le contrôleur Stimulus de la recette |
| `table/manifest.json`, `table/README.md` | La recette `Table` |
| `table/templates/components/` | Le composant `Table` et ses parties |
| `composer.json` | `phil/ux-kit`, pour les tests et pour les bundles qui produisent le rendu des recettes dans les leurs |
| `color-picker/manifest.json`, `color-picker/README.md` | La recette `ColorPicker` |
| `color-picker/templates/components/` | Le composant `ColorPicker` |
| `color-picker/assets/controllers/` | Le contrôleur Stimulus de la recette |
| `date-picker/manifest.json`, `date-picker/README.md` | La recette `DatePicker` |
| `date-picker/templates/components/` | Le composant `DatePicker` |
| `date-picker/assets/controllers/` | Le contrôleur Stimulus de la recette |
| `copy-button/manifest.json`, `copy-button/README.md` | La recette `CopyButton` |
| `copy-button/templates/components/` | Le composant `CopyButton` |
| `copy-button/assets/controllers/` | Le contrôleur Stimulus de la recette |
| `animated-button/manifest.json`, `animated-button/README.md` | La recette `AnimatedButton` |
| `animated-button/templates/components/` | Le composant `AnimatedButton` |
| `stepper/manifest.json`, `stepper/README.md` | La recette `Stepper` |
| `stepper/templates/components/` | Le composant `Stepper` et ses parties |
| `activity-calendar/manifest.json`, `activity-calendar/README.md` | La recette `ActivityCalendar` |
| `activity-calendar/templates/components/` | Le composant `ActivityCalendar` |
| `tree/manifest.json`, `tree/README.md` | La recette `Tree` |
| `tree/templates/components/` | Le composant `Tree` et ses parties |
| `tree/assets/controllers/` | Le contrôleur Stimulus de la recette |
| `rating/manifest.json`, `rating/README.md` | La recette `Rating` |
| `rating/templates/components/` | Le composant `Rating` |
| `number-input/manifest.json`, `number-input/README.md` | La recette `NumberInput` |
| `number-input/templates/components/` | Le composant `NumberInput` |
| `number-input/assets/controllers/` | Le contrôleur Stimulus de la recette |
| `file-input/manifest.json`, `file-input/README.md` | La recette `FileInput` |
| `file-input/templates/components/` | Le composant `FileInput` |
| `file-input/assets/controllers/` | Le contrôleur Stimulus de la recette |
| `tag/manifest.json`, `tag/README.md` | La recette `Tag` |
| `tag/templates/components/` | Le composant `Tag` |
| `tag/assets/controllers/` | Le contrôleur Stimulus de la recette |
| `relative-time/manifest.json`, `relative-time/README.md` | La recette `RelativeTime` |
| `relative-time/templates/components/` | Le composant `RelativeTime` |
| `relative-time/assets/controllers/` | Le contrôleur Stimulus de la recette |
| `progress-ring/manifest.json`, `progress-ring/README.md` | La recette `ProgressRing` |
| `progress-ring/templates/components/` | Le composant `ProgressRing` |
| `sparkline/manifest.json`, `sparkline/README.md` | La recette `Sparkline` |
| `sparkline/templates/components/` | Le composant `Sparkline` |
| `before-after/manifest.json`, `before-after/README.md` | La recette `BeforeAfter` |
| `before-after/templates/components/` | Le composant `BeforeAfter` |
| `before-after/assets/controllers/` | Le contrôleur Stimulus de la recette |
| `alert-dialog/manifest.json`, `alert-dialog/README.md` | La recette `AlertDialog` |
| `alert-dialog/templates/components/` | Le composant `AlertDialog` et ses parties |
| `aspect-ratio/manifest.json`, `aspect-ratio/README.md` | La recette `AspectRatio` |
| `aspect-ratio/templates/components/` | Le composant `AspectRatio` |
| `collapsible/manifest.json`, `collapsible/README.md` | La recette `Collapsible` |
| `collapsible/templates/components/` | Le composant `Collapsible` et ses parties |
| `field/manifest.json`, `field/README.md` | La recette `Field` |
| `field/templates/components/` | Le composant `Field` et ses parties |
| `item/manifest.json`, `item/README.md` | La recette `Item` |
| `item/templates/components/` | Le composant `Item` et ses parties |
| `kbd/manifest.json`, `kbd/README.md` | La recette `Kbd` |
| `kbd/templates/components/` | Le composant `Kbd` et ses parties |
| `native-select/manifest.json`, `native-select/README.md` | La recette `NativeSelect` |
| `native-select/templates/components/` | Le composant `NativeSelect` et ses parties |
| `progress/manifest.json`, `progress/README.md` | La recette `Progress` |
| `progress/templates/components/` | Le composant `Progress` |
| `radio-group/manifest.json`, `radio-group/README.md` | La recette `RadioGroup` |
| `radio-group/templates/components/` | Le composant `RadioGroup` et ses parties |
| `scroll-area/manifest.json`, `scroll-area/README.md` | La recette `ScrollArea` |
| `scroll-area/templates/components/` | Le composant `ScrollArea` |
| `toggle/manifest.json`, `toggle/README.md` | La recette `Toggle` |
| `toggle/templates/components/` | Le composant `Toggle` |
| `toggle-group/manifest.json`, `toggle-group/README.md` | La recette `ToggleGroup` |
| `toggle-group/templates/components/` | Le composant `ToggleGroup` et ses parties |
| `login-form/manifest.json`, `login-form/README.md` | Le bloc `LoginForm` |
| `login-form/templates/components/` | Le composant `LoginForm` |
| `signup-form/manifest.json`, `signup-form/README.md` | Le bloc `SignupForm` |
| `signup-form/templates/components/` | Le composant `SignupForm` |
| `chart/manifest.json`, `chart/README.md` | La recette `Chart` |
| `chart/templates/components/` | Le composant `Chart` |
| `chart-js/manifest.json`, `chart-js/README.md` | La recette `ChartJs` |
| `chart-js/templates/components/` | Le composant `ChartJs` |
