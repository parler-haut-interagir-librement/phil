# phil/ux-kit

**PHIL UX kit for the Symfony UX Toolkit: the same recipes as the Shadcn kit, styled with phil-ux, installed with
`ux:install --kit`.**

The kit is a directory of recipes laid out as the Symfony UX Toolkit expects, its `manifest.json` at the root. Each
recipe is a Twig component — and its Stimulus controller when it moves —, which `ux:install` copies into the
application, where it becomes the application's own code. The look comes from phil-ux, the stylesheet of
[`phil/ux-css`](https://github.com/parler-haut-interagir-librement/ux-css), on which every recipe depends; the recipes
bring only their Twig and their Stimulus.

The kit takes the recipe names, props, blocks and Stimulus controllers of the Shadcn kit, so that a bundle works with
either kit, and adds recipes of its own. Their Tailwind versions, under the same names and props, are the kit
[`phil/ux-kit-shadcn`](https://github.com/parler-haut-interagir-librement/ux-kit-shadcn).

## Status

| Recipe | Type | What it is | Status |
|---|---|---|---|
| [Logo](logo/README.md) | component | A `Picture` with its AVIF and WebP sources and its densities, its variants for forced colours, dark systems and reduced motion, swapped by theme or locale, linked home; `alt` required | Implemented and tested |
| [AnimatedHeadline](animated-headline/README.md) | component | A headline whose words are revealed through a clip, played through once, or cycled with a pause button; still under reduced motion; read whole by screen readers | Implemented and tested |
| [Illustration](illustration/README.md) | component | A figure showing one picture chosen among candidates on the server, by a seedable random | Implemented and tested |
| [Image](image/README.md) | component | An image of the content, `Image` or `Picture` with its AVIF and WebP sources, by density or by width, under the prop names of Symfony UX Image: the main image preloaded through WebLink, `fetchpriority="low"` for one far below, a placeholder colour of the tokens, an optional blurred preview, variants for forced colours, dark systems and reduced motion, a long description, credit, license and creation, a caption laid over the image on a veil, the images of other hosts refused unless listed, `image_sources()` on AssetMapper, `image_filters()` on LiipImagine, `picture_props()` from a provider, an `ImageObject` in JSON-LD, warnings in development for a text alternative left wrong; no inline style | Implemented and tested |
| [Gallery](gallery/README.md) | component | A list of figures in a grid that follows its container, each image opened in a `Lightbox` when asked; a long gallery rendered as it comes near, with `content-visibility`; or a carousel that scrolls and snaps without a script, its markers and buttons drawn by the browser where it can | Implemented and tested |
| [Lightbox](lightbox/README.md) | component | An image enlarged in a native modal `dialog`, opened by the invokers of HTML, the `lightbox` controller standing in where the browser does not run them | Implemented and tested |
| [Hotspots](hotspots/README.md) | component | An image with points of interest, a list of links over it, each placed by classes and a typed `attr()`, never an inline style; a list under the image on a phone | Implemented and tested |
| [Hero](hero/README.md) | component | An image across the whole box that stays a real image, the main one of the page by default, and the words of the page over it on a veil | Implemented and tested |
| [Video](video/README.md) | component | A video with the controls of the browser that never plays on its own: nothing fetched before it is played, its poster, captions and transcript | Implemented and tested |
| [Figure](figure/README.md) | component | Several images in a grid under one caption, credit and license | Implemented and tested |
| [AutosaveStatus](autosave-status/README.md) | component | The live region and the notice of a form's draft, for the autosave controller of `phil/forms-autosave` | Implemented and tested |
| [ReactionButton](reaction-button/README.md) | component | One reaction of `phil/reactions-bundle`: a button that says it is pressed, or plain text; a target wide and tall enough for a finger | Implemented and tested |
| [ReactionPicker](reaction-picker/README.md) | component | The other reactions of `phil/reactions-bundle`: a disclosure of plain buttons, for the picker controller of the bundle | Implemented and tested |
| [ComingSoon](coming-soon/README.md) | block | A full-height split page: logo, eyebrow, animated headline, paragraphs, illustration; stacked on small screens | Implemented and tested |
| [Badge](badge/README.md) | component | A short label: a tag, a status, a count. | Implemented and tested |
| [Button](button/README.md) | component | A button, or a link that looks like one with `as="a"`. | Implemented and tested |
| [ButtonGroup](button-group/README.md) | component | Buttons that belong together, joined: a view switcher, the previous and next periods. | Implemented and tested |
| [Card](card/README.md) | component | A card: its picture, a header, its content and a footer. | Implemented and tested |
| [Empty](empty/README.md) | component | What a list shows when it has nothing: a picture, a title, a sentence, and what to do. | Implemented and tested |
| [Popover](popover/README.md) | component | A panel opened by a button, on the HTML popover API: it opens and closes, Escape and a click outside close it, without JavaScript; the controller places it next to its button. | Implemented and tested |
| [QrCode](qr-code/README.md) | component | A QR code in a figure, with what it holds written beside it, a link for a web address, drawn on the server by `phil/qr-code-bundle`. | Implemented and tested |
| [Tabs](tabs/README.md) | component | Tabs over panels. Without JavaScript every panel shows; with it, one at a time, the arrows, Home and End moving between the tabs. | Implemented and tested |
| [Accordion](accordion/README.md) | component | Sections that open and close, on `details` and `summary`: they work without JavaScript, the browser keeping one open at a time; the controller adds the arrows, Home and End. | Implemented and tested |
| [Dialog](dialog/README.md) | component | A modal window, on the native `dialog` element and its invokers: the browser makes the page inert, keeps the focus inside, closes it on Escape and gives the focus back. | Implemented and tested |
| [Sheet](sheet/README.md) | component | A panel sliding from an edge of the screen: a `Dialog` that keeps to one side, with the same controller. | Implemented and tested |
| [Switch](switch/README.md) | component | An on and off control: a checkbox with the role `switch`, which the browser checks and submits without JavaScript. | Implemented and tested |
| [Tooltip](tooltip/README.md) | component | A short description of a control, shown on hover and focus: it can be hovered, stays until left, and Escape dismisses it (WCAG 1.4.13). | Implemented and tested |
| [Pagination](pagination/README.md) | component | The links between the pages of a list. | Implemented and tested |
| [Carousel](carousel/README.md) | component | Slides in a row, on native scroll snapping: they scroll by touch, trackpad and keyboard without JavaScript; the controller adds the buttons, the arrows and, on request, the automatic play. `::scroll-button()` being in one engine alone, the buttons do not rely on it. | Implemented and tested |
| [Spinner](spinner/README.md) | component | What shows while something loads; phil-ux stills it under `prefers-reduced-motion`. | Implemented and tested |
| [Skeleton](skeleton/README.md) | component | A placeholder for content on its way, hidden from screen readers; phil-ux stills it under `prefers-reduced-motion`. | Implemented and tested |
| [Alert](alert/README.md) | component | A message that stands out: an error, a confirmation. | Implemented and tested |
| [Avatar](avatar/README.md) | component | The picture of a person, over the initials that show when it is missing or broken, without script. | Implemented and tested |
| [Input](input/README.md) | component | A text field; any attribute reaches the element. | Implemented and tested |
| [Label](label/README.md) | component | The name of a form control. | Implemented and tested |
| [Select](select/README.md) | component | A list of options to choose one from, submitted as a field of the form when it has a `name`. For a page without JavaScript, the `select` element is styled by phil-ux on its own. | Implemented and tested |
| [Separator](separator/README.md) | component | A line between two parts of a page. | Implemented and tested |
| [Textarea](textarea/README.md) | component | A field of several lines. | Implemented and tested |
| [Checkbox](checkbox/README.md) | component | A checkbox, drawn by phil-ux. | Implemented and tested |
| [Breadcrumb](breadcrumb/README.md) | component | Where the page sits in the site. | Implemented and tested |
| [DropdownMenu](dropdown-menu/README.md) | component | A menu of actions opened by a button, on the popover API: Escape and a click outside close it and the focus goes back to the button. | Implemented and tested |
| [Table](table/README.md) | component | A table of data, which scrolls sideways inside its own box on a small screen. | Implemented and tested |
| [AlertDialog](alert-dialog/README.md) | component | A question in a modal window: Escape dismisses it, a click outside does not, the focus starts on the harmless answer. | Implemented and tested |
| [AspectRatio](aspect-ratio/README.md) | component | A box kept to a ratio, its picture or video covering it. | Implemented and tested |
| [Collapsible](collapsible/README.md) | component | A part of the page that opens and closes, on `details` and `summary`, without JavaScript. | Implemented and tested |
| [Field](field/README.md) | component | A form field — label, control, help, errors — and sets of fields under a legend. | Implemented and tested |
| [Item](item/README.md) | component | One entry of a list: its media, its title and description, its actions. | Implemented and tested |
| [Kbd](kbd/README.md) | component | A key of the keyboard, or the keys of a shortcut, on `kbd`. | Implemented and tested |
| [NativeSelect](native-select/README.md) | component | The `select` element of the browser, dressed by phil-ux. | Implemented and tested |
| [Progress](progress/README.md) | component | The progress of a task, on the native `progress` element. | Implemented and tested |
| [RadioGroup](radio-group/README.md) | component | Radio buttons sharing a name, which the browser moves between with the arrows. | Implemented and tested |
| [ScrollArea](scroll-area/README.md) | component | A box whose content scrolls inside it, a region the keyboard reaches when named. | Implemented and tested |
| [Toggle](toggle/README.md) | component | A button that stays pressed, on a checkbox inside its label, without JavaScript. | Implemented and tested |
| [ToggleGroup](toggle-group/README.md) | component | Toggles that belong together, on radio buttons or checkboxes, without JavaScript. | Implemented and tested |
| [LoginForm](login-form/README.md) | block | A sign-in form in a card, for the form login of Symfony Security. | Implemented and tested |
| [SignupForm](signup-form/README.md) | block | A sign-up form in a card, each field naming its errors, a password never written back. | Implemented and tested |
| [ColorPicker](color-picker/README.md) | component | The colour well of the browser, its hexadecimal value and swatches to choose from. The well is the field: it works alone without a script. | Implemented and tested |
| [DatePicker](date-picker/README.md) | component | The date field of the browser, which works alone, and a button opening a month on a grid, in a popover. | Implemented and tested |
| [CopyButton](copy-button/README.md) | component | A button that copies a text, given or read from another element, and says so. | Implemented and tested |
| [Celebration](celebration/README.md) | component | A message said in a live region, and confetti or fireworks drawn over the page on a canvas for a few seconds, in the colours of the theme; the message alone where motion is reduced. Played at once, on an event or by a button. | Implemented and tested |
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
| [Chart](chart/README.md) | component | A chart drawn by the server in SVG, bars or lines, with the table of its values. | Implemented and tested |
| [ChartJs](chart-js/README.md) | component | A chart of Symfony UX Chart.js in a figure, with its caption and the table of its values. | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | `ux:install` from the public repository, the files to copy until then, the stylesheet. |
| [Recipes](doc/recipes.md) | Each recipe, its props, blocks and controller. |
| [A coming soon page](doc/coming-soon.md) | A whole page built from the `ComingSoon` block. |
| [Extension points](doc/extension-points.md) | What an application changes, and how. |
| [Testing](doc/testing.md) | How the kit is tested. |
| [Reference](doc/reference.md) | Every file of the kit. |

## Quick start

```sh
php bin/console ux:install coming-soon --kit=github.com/parler-haut-interagir-librement/ux-kit
```

```twig
<twig:ComingSoon
    logoSrc="{{ asset('images/logo.svg') }}"
    logoAlt="Studio"
    eyebrow="{{ 'coming_soon'|trans }}"
    words="Studio"
    :paragraphs="['site.info'|trans]"
    :illustrations="[asset('images/main.png')]"
    illustrationAlt=""
/>
```

## Requirements

Symfony UX Twig Component 3.7 or later; Symfony UX Toolkit 3.7 or later to install the recipes; the StimulusBundle for
the recipes that move and the blurred preview of `Image`; `twig/extra-bundle`, `twig/html-extra`, `symfony/web-link`
and the bundle of this package for `Image`, which lists the hosts whose images a page may show; `phil/qr-code-bundle`
for `QrCode`; Symfony UX Chart.js for `ChartJs`; the phil-ux stylesheet of `phil/ux-css`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
