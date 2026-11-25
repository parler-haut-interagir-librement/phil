# Installation

## Tailwind and the Shadcn kit

The recipes are written for Tailwind CSS 4. They read the tokens of the Shadcn kit — `bg-background`,
`text-foreground`, `text-muted-foreground`, `ring-ring`, `--radius`, `--chart-1` to `--chart-5` — and the
`animate-in` utilities of tw-animate-css 1.3.4 or later, whose logical `slide-in-from-end` follows the direction of
the page. Install Tailwind 4, tw-animate-css and the Shadcn kit's stylesheet as the Shadcn kit's own installation
guide says, then make Tailwind read the installed components:

```css
/* assets/styles/app.css */
@import 'tailwindcss';
@import 'tw-animate-css';
@source '../../templates/components';
```

The recipes with a Stimulus controller — `AnimatedHeadline`, `Carousel`, `Celebration`, `Lightbox`, `ColorPicker`,
`CopyButton`, `Tree`, `NumberInput`, `FileInput`, `Tag`, `RelativeTime`, `BeforeAfter`, `ThemeSwitcher`, `StickyHeader`
and the blurred preview of `Image` — need the StimulusBundle, which loads the controllers `ux:install` copies into `assets/controllers/`.

## With `ux:install`

The recipes install with Symfony UX Toolkit 3.7.1 or later:

```sh
php bin/console ux:install coming-soon --kit=github.com/parler-haut-interagir-librement/ux-kit-shadcn
```

`coming-soon` installs `Logo`, `AnimatedHeadline` and `Illustration` too, into the directories and the subdirectory
of the application's `ux_toolkit` configuration. With a subdirectory, `ui` for instance, the Toolkit prefixes the
identifiers of the kit's controllers wherever a template names them, `data-controller="ui--carousel"` and
`data-ui--carousel-target`, including the `data-controller` written in a Twig hash, which 3.7.1 is the first version
to rewrite; the controllers of a bundle, such as the autosave controller of `phil/forms-autosave`, keep their
identifiers. `ux:install` reads a kit from GitHub or among the Toolkit's own kits, never from a local path.

The Shadcn kit and this kit can both be installed in one application: the Shadcn recipes come from the Toolkit's
`shadcn` kit, the others from this one. A recipe name both kits hold, `Carousel`, writes the same files: install it
from the kit whose behaviour the page wants, and answer no when `ux:install` offers to overwrite it from the other.
The date field is the Shadcn kit's `DatePicker`; this kit has none.

## Until the repository is published

Copy the files of the recipes the page needs, from this directory, keeping their names:

| Recipe | Copy | To |
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
