# phil/ux-kit-shadcn

**Tailwind versions, in the style of the Shadcn kit, of the extra PHIL UX recipes, for `ux:install --kit`.**

The PHIL UX kit takes the recipes of the Shadcn kit of the Symfony UX Toolkit and adds recipes of its own, which
PHIL bundles may require. This kit holds those extra recipes alone, written in Tailwind on the tokens of the Shadcn
kit (`bg-background`, `text-muted-foreground`), under the same names, props, blocks and Stimulus controllers as in
[`phil/ux-kit`](https://github.com/parler-haut-interagir-librement/ux-kit). An application that uses the Shadcn kit
installs the Shadcn recipes from it and the PHIL recipes from here; a bundle renders with either.

Two names are shared with the Shadcn kit. `Carousel` is here under the props of the PHIL UX kit, on native scroll
snapping instead of a carousel library: install it from one kit or the other, as both write the same files. The
Shadcn kit's `DatePicker`, a popover around its calendar, is the one to install: the native date field of the PHIL UX
kit is not repeated here.

## Status

| Recipe | Type | What it is | Status |
|---|---|---|---|
| [Logo](logo/README.md) | component | A `Picture` with its AVIF and WebP sources and its densities, its variants for forced colours, dark systems and reduced motion, swapped by theme or locale, linked home; `alt` required | Implemented and tested |
| [AnimatedHeadline](animated-headline/README.md) | component | A headline whose words are revealed through a clip, played through once, or cycled with a pause button; still under reduced motion; read whole by screen readers | Implemented and tested |
| [Illustration](illustration/README.md) | component | A figure showing one picture chosen among candidates on the server, by a seedable random | Implemented and tested |
| [Image](image/README.md) | component | An image of the content, `Image` or `Picture` with its AVIF and WebP sources, by density or by width, under the prop names of Symfony UX Image: the main image preloaded through WebLink, `fetchpriority="low"` for one far below, a placeholder colour, an optional blurred preview, variants for forced colours, dark systems and reduced motion, a long description, credit, license and creation, the images of other hosts refused unless listed, an `ImageObject` in JSON-LD, frames, tokens and pixel art, a name for view transitions, legible on paper and right to left; no inline style, working under a strict Content Security Policy | Implemented and tested |
| [Gallery](gallery/README.md) | component | A list of figures in a grid on a container query, each image opened in a `Lightbox` when asked; a long gallery rendered as it comes near, with `content-visibility` | Implemented and tested |
| [Lightbox](lightbox/README.md) | component | An image enlarged in a native modal `dialog`, opened by the invokers of HTML, the `lightbox` controller standing in where the browser does not run them | Implemented and tested |
| [Hotspots](hotspots/README.md) | component | An image with points of interest, a list of links over it, each placed by utilities and a typed `attr()`, never an inline style; a list under the image on a phone | Implemented and tested |
| [Hero](hero/README.md) | component | An image across the whole box that stays a real image, the main one of the page by default, and the words of the page over it on a veil | Implemented and tested |
| [Video](video/README.md) | component | A video with the controls of the browser that never plays on its own: nothing fetched before it is played, its poster, captions and transcript | Implemented and tested |
| [Figure](figure/README.md) | component | Several images in a grid under one caption, credit and license | Implemented and tested |
| [QrCode](qr-code/README.md) | component | A QR code drawn on the server by `phil/qr-code-bundle`, in a figure with what it holds written beside it | Implemented and tested |
| [AutosaveStatus](autosave-status/README.md) | component | The live region and the notice of a form's draft, for the autosave controller of `phil/forms-autosave` | Implemented and tested |
| [ReactionButton](reaction-button/README.md) | component | One reaction of `phil/reactions-bundle`: a button that says it is pressed, or plain text; a target wide and tall enough for a finger | Implemented and tested |
| [ReactionPicker](reaction-picker/README.md) | component | The other reactions of `phil/reactions-bundle`: a disclosure of plain buttons, for the picker controller of the bundle | Implemented and tested |
| [ColorPicker](color-picker/README.md) | component | The colour well of the browser, its hexadecimal value and swatches to choose from. The well is the field: it works alone without a script. | Implemented and tested |
| [CopyButton](copy-button/README.md) | component | A button that copies a text, given or read from another element, and says so. | Implemented and tested |
| [Celebration](celebration/README.md) | component | A message said in a live region, and an effect drawn over the page on a canvas for a few seconds, in the `--primary` and `--chart-*` tokens: confetti, fireworks, a laser show, balloons and seven more; the message alone where motion is reduced or the person chose none, fewer pieces when discreet or tired. Played at once, on an event, by a button or by a Turbo Stream. | Implemented and tested |
| [SeasonalDecor](seasonal-decor/README.md) | component | A decor for the holidays, chosen by the site and off by default, drawn over the page for a few seconds, a still picture where motion is reduced, a fixed poppy for a day of remembrance, an egg hunt and a secret code; the holiday of the day from `seasonal_decor()` of phil/ux-kit. | Implemented and tested |
| [Podium](podium/README.md) | component | The first three of a ranking rising on their steps, the winner last, then said; standing from the start where motion is reduced. | Implemented and tested |
| [ScratchCard](scratch-card/README.md) | component | A prize under a cover scratched away by a pointer, or revealed at once by its button, in the `--muted-foreground` token; then said and celebrated. | Implemented and tested |
| [AnimatedButton](animated-button/README.md) | component | A button, or a link when it has an address, that moves under the pointer and the focus: a light sweeping across it, a lift or its arrow pushed forward. Pure CSS. | Implemented and tested |
| [Stepper](stepper/README.md) | component | The steps of a process, the finished ones marked, the current one announced, reading downwards on a phone. | Implemented and tested |
| [ActivityCalendar](activity-calendar/README.md) | component | Activity over time: a cell per day, each read through its label, the colour never standing alone. Wider than the screen, it scrolls by itself and takes the focus to do so. | Implemented and tested |
| [Tree](tree/README.md) | component | A tree of items, on the WAI-ARIA pattern. | Implemented and tested |
| [Rating](rating/README.md) | component | A note out of a number of stars: radio buttons with a name, a single image without one, in half stars. | Implemented and tested |
| [NumberInput](number-input/README.md) | component | A number field with a button on each side. | Implemented and tested |
| [FileInput](file-input/README.md) | component | A file field that also takes a drop, listing what it holds with the size of each. | Implemented and tested |
| [Tag](tag/README.md) | component | A short label, which a button can take away. | Implemented and tested |
| [RelativeTime](relative-time/README.md) | component | A moment written by the server as a date, then by a script as the time from now, which it keeps right. | Implemented and tested |
| [ProgressRing](progress-ring/README.md) | component | Progress as a ring, with the percentage in its centre. Pure CSS and SVG. | Implemented and tested |
| [Sparkline](sparkline/README.md) | component | A small line of values, drawn by the server. | Implemented and tested |
| [BeforeAfter](before-after/README.md) | component | Two pictures one above the other, the first cut where a slider says. | Implemented and tested |
| [ComingSoon](coming-soon/README.md) | block | A full-height split page: logo, eyebrow, animated headline, paragraphs, illustration; stacked on small screens | Implemented and tested |
| [LoginForm](login-form/README.md) | block | A sign-in form in a card, for the form login of Symfony Security. | Implemented and tested |
| [SignupForm](signup-form/README.md) | block | A sign-up form in a card, each field naming its errors, a password never written back. | Implemented and tested |
| [Chart](chart/README.md) | component | A chart drawn by the server in SVG, bars or lines, its series in the chart tokens of the Shadcn kit, with the table of its values. | Implemented and tested |
| [Carousel](carousel/README.md) | component | Slides in a row on the native scroll snapping of the browser, the kit's buttons on each side, an optional automatic play with its pause button, still under reduced motion; no carousel library | Implemented and tested |
| [ChartJs](chart-js/README.md) | component | A chart of Symfony UX Chart.js in a figure, with its caption and the table of its values. | Implemented and tested |
| [ThemeSwitcher](theme-switcher/README.md) | component | The choice of the colour scheme, light, dark or the system's, kept in the browser and written on the page before it is first painted. | Implemented and tested |
| [StickyHeader](sticky-header/README.md) | component | The header of the page, kept at the top of the screen, anchors stopping below it; it may slide away while the reader scrolls down, inert while hidden. | Implemented and tested |
| [Timeline](timeline/README.md) | component | Dated entries down a line, each with its marker, its date and its content. | Implemented and tested |
| [FloatingLabel](floating-label/README.md) | component | A field whose label sits inside the empty control and moves above it once the control holds a value or has the focus. | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Tailwind and the Shadcn kit, `ux:install`, the files to copy until the repository is published. |
| [Differences](doc/differences.md) | What differs from the PHIL UX kit: the classes only. |
| [Extension points](doc/extension-points.md) | What an application changes, and how. |
| [Testing](doc/testing.md) | How the kit is tested. |
| [Reference](doc/reference.md) | Every file of the kit. |

## Quick start

```sh
php bin/console ux:install coming-soon --kit=github.com/parler-haut-interagir-librement/ux-kit-shadcn
```

```twig
<twig:ComingSoon logoSrc="/images/logo.svg" logoAlt="Studio" eyebrow="Coming soon" words="Studio" />
```

## Requirements
 Tailwind CSS 4 with the Shadcn kit's stylesheet and tw-animate-css, as the Shadcn kit installs them; Symfony UX Twig
Component 3.7 or later; Symfony UX Toolkit 3.7.1 or later to install the recipes; the StimulusBundle for the recipes
with a controller (`AnimatedHeadline`, `Carousel`, `Celebration`, `Lightbox`, `ColorPicker`, `CopyButton`, `Tree`,
`NumberInput`, `FileInput`, `Tag`, `RelativeTime`, `BeforeAfter`, `ThemeSwitcher`, `StickyHeader`, `SeasonalDecor`,
`Podium`, `ScratchCard`) and the blurred preview of `Image`; `twig/extra-bundle`, `twig/html-extra`, `symfony/web-link`
and `phil/ux-kit`, whose bundle lists the hosts whose images a page may show, for `Image`; the `--chart-1` to
`--chart-5` tokens of the Shadcn kit for `Chart`; Symfony UX Chart.js for `ChartJs`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
