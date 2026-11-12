# Installation

## The stylesheet

Every recipe takes its look from phil-ux. Install it once, as its [installation guide](https://github.com/parler-haut-interagir-librement/ux-css/blob/main/doc/installation.md)
explains — `composer require phil/ux-css` with AssetMapper, `npm install @phil/ux-css`, or a copy of
`dist/phil-ux.css` — and link it from the base template.

## With `ux:install`

The Toolkit downloads a kit from a public GitHub repository, as a zip archive, without authentication:

```sh
php bin/console ux:install coming-soon --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install logo --kit=github.com/parler-haut-interagir-librement/ux-kit:v0.1.0
```

A recipe brings the recipes it depends on: `coming-soon` installs `Logo`, `AnimatedHeadline` and `Illustration`
too. The components go to `templates/components/` and the controllers to `assets/controllers/`, or to the
directories and subdirectory the `ux_toolkit` configuration sets: with a subdirectory `ui`, the copies become
`<twig:ui:Logo>` and `ui--animated-headline`, rewritten by the Toolkit in every copied file. The command prints the
packages the recipes need; it installs none.

`ux:install` reads a kit from GitHub or among the Toolkit's own kits, never from a local path: `--kit=../ux-kit` is
refused as an invalid kit name.

## The PHP of the image recipes

`Image`, `Picture`, `Logo`, `Gallery` and `Lightbox` call three Twig functions of `phil/ux-kit`, which is also a
Symfony bundle, and `preload()` of Symfony WebLink; the StimulusBundle registers the `image-preview` controller of a
blurred preview:

```sh
composer require phil/ux-kit symfony/web-link symfony/stimulus-bundle
```

Symfony Flex registers `Phil\UxKit\PhilUxKitBundle`; without Flex, add it to `config/bundles.php`. Its configuration
lists the hosts whose images a page may show, those under each included; every other host is refused:

```yaml
# config/packages/phil_ux_kit.yaml
phil_ux_kit:
    image:
        hosts: ['images.example.com']
        allow_localhost: false   # true in development only
        check: null              # the debug of the kernel when null
```

`check` logs a warning, on the `phil_ux_kit` channel, for an image whose text alternative is the name of its file or
only says it is an image, and for one without a width or a height; it follows the debug of the kernel unless set.
`image_sources()` reads the assets of AssetMapper, `symfony/asset-mapper`, when it is installed.

## Until the repository is published

Copy the files of the recipes the page needs, from this directory, keeping their names:

| Recipe | Copy | To |
|---|---|---|
| Logo | `logo/templates/components/Logo.html.twig` | `templates/components/Logo.html.twig` |
| AnimatedHeadline | `animated-headline/templates/components/AnimatedHeadline.html.twig` | `templates/components/AnimatedHeadline.html.twig` |
| AnimatedHeadline | `animated-headline/assets/controllers/animated_headline_controller.js` | `assets/controllers/animated_headline_controller.js` |
| Illustration | `illustration/templates/components/Illustration.html.twig` | `templates/components/Illustration.html.twig` |
| Image | `image/templates/components/` | `templates/components/` |
| Image | `image/assets/controllers/` | `assets/controllers/` |
| Gallery | `gallery/templates/components/` | `templates/components/` |
| Lightbox | `lightbox/templates/components/` | `templates/components/` |
| Lightbox | `lightbox/assets/controllers/` | `assets/controllers/` |
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
| Celebration | `celebration/templates/components/` | `templates/components/` |
| Celebration | `celebration/assets/controllers/` | `assets/controllers/` |
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

Then:

```sh
composer require symfony/ux-twig-component symfony/stimulus-bundle
```

The StimulusBundle registers every controller of `assets/controllers/` under its file name: the copied controller is
`animated-headline`, as the template names it. `AlertDialog` runs on the controller of `Dialog`, copied with it;
`ChartJs` needs `composer require symfony/ux-chartjs`.
