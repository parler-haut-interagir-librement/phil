# Changelog

All notable changes to `phil/ux-kit` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-11

### Added

- The recipe `image`, under the prop names of Symfony UX Image, without a service, JavaScript or inline style: `Image`,
  one `img` of class `isImage`, and `Picture`, its `sources` (`{type, srcset, sizes, media}`, AVIF first) before that
  `img`; `src`, `alt`, `width` and `height` required, an empty `alt` making the image decorative, its `title` and
  `aria-*` attributes then left out; `srcset` as HTML writes it or a map of densities, or of widths with `sizes`;
  `sizes` after `auto,` while the image loads lazily; `loading="lazy"` and `decoding="async"` by default, `priority`
  giving `loading="eager"` and `fetchpriority="high"`; `caption`, a `figure` with its escaped `figcaption`, shown under
  a decorative image too; of the other attributes, only `id`, `class`, `title`, `crossorigin`, `referrerpolicy`,
  `itemprop`, `draggable`, `data-*` and `aria-*`, written with `html_attr()` of `twig/html-extra`, which the recipe
  requires with `twig/extra-bundle`; a `javascript:`, `vbscript:` or `data:` URL other than `data:image/` refused, a
  refused `src` rendering nothing, a refused `srcset` or source left out whole.

## 0.1.0-alpha - 2026-11-11

### Added

- `manifest.json` and `icon.svg`: the kit for the Symfony UX Toolkit, depending on `phil/ux-css` (Composer) and
  `@phil/ux-css` (npm, importmap) and on Symfony UX Twig Component.
- The recipe `Logo`: an image with an optional WebP source, `{theme}` and `{locale}` replaced in its sources, linked
  when it has an `href`; `alt` required.
- The recipe `AnimatedHeadline` and its Stimulus controller `animated-headline`: the words of a headline revealed
  through a clip that closes to a caret and opens on the next one; one word revealed once, then still; several played
  through once back to the first, the run fitted within five seconds by `timing()` and `BUDGET` (WCAG 2.2.2); with
  `loop`, cycled, with a pause/play `<button>` whose labels `pauseLabel` and `playLabel` are props, the heading named
  by its hidden copy through `aria-labelledby`; held fully open while hovered, focused, paused or in a hidden
  document; each move ended on `transitionend` or after its duration; still under `prefers-reduced-motion` and
  without JavaScript; read whole by screen readers from a visually hidden copy, the animated words hidden and
  `aria-live="off"`.
- The recipe `Illustration`: a figure with a WebP source and its fallback, one picture chosen among candidates on the
  server, at random or by a seed; caption, sizes, loading.
- The recipe `AutosaveStatus`: the live region and the notice of a form's draft, for the autosave controller of
  `phil/forms-autosave`, its targets and actions; the labels of its buttons as props.
- The recipes `ReactionButton` and `ReactionPicker`, for `phil/reactions-bundle`: a reaction pressed or not, or as plain
  text, with a touch target of 2.75rem; the other reactions in a disclosure of plain buttons, with the targets of the
  picker controller of the bundle, the texts as props, and of the attributes of a button only `name`, `value`, `title`,
  `data-*` and `aria-*`.
- The recipes `Badge`, `Button`, `ButtonGroup`, `Card`, `Empty`, `Popover`, `Tabs`, `Accordion`, `Dialog`, `Sheet`, `Switch`,
  `Tooltip`, `Pagination`, `Carousel`, `Spinner`, `Skeleton`, `Alert`, `Avatar`, `Input`, `Label`, `Select`, `Separator`,
  `Textarea`, `Checkbox`, `Breadcrumb`, `DropdownMenu` and `Table`, under the names, props, blocks and Stimulus
  controllers of the Shadcn kit of the Toolkit, on semantic elements and the classes of phil-ux; every text a prop or a
  block. `Dialog` and `Sheet` on `dialog`, HTML first: `closedby="any"` and the invokers `command` and `commandfor`,
  which the controller `dialog` leaves to the browser where it runs them and stands in for where it does not;
  `Popover`, `Select` and `DropdownMenu` on the popover API,
  `Tooltip` on a manual popover, `Accordion` on `details` and `summary`, `Switch` on `input role="switch"`, `Carousel`
  on native scroll snapping, the keyboard of WAI-ARIA in each; every part writes its class of phil-ux, `data-slot` kept
  beside it.
- The recipe `QrCode`, around the component of `phil/qr-code-bundle`.
- The recipes `AlertDialog`, `AspectRatio`, `Collapsible`, `Field`, `Item`, `Kbd`, `NativeSelect`, `Progress`,
  `RadioGroup`, `ScrollArea`, `Toggle` and `ToggleGroup`, under the names, props and blocks of the Shadcn kit, HTML
  first: `AlertDialog` on `dialog` with the role `alertdialog`, `closedby="closerequest"`, the invokers and the
  controller `dialog`, its cancel button focused; `Collapsible` on `details`; `Kbd`, `NativeSelect` and `Progress` on
  their native elements; `RadioGroup`, `Toggle` and `ToggleGroup` on radio buttons and checkboxes, without a
  controller; `ScrollArea` a region the keyboard reaches when it is named; `AspectRatio` refusing any ratio but a number
  or a quotient.
- The block `ComingSoon`: the logo, an eyebrow and the animated headline in an `hgroup`, paragraphs and a content block
  in the left column, as `header` and `main` landmarks or not; an illustration filling the right column with a slanted
  entrance; stacked on small screens.
- The blocks `LoginForm` and `SignupForm`, of plain form elements in a card: the field names of the form login of
  Symfony Security, the last user name written back, the error a live alert the fields name, the CSRF token, the links
  to a forgotten password and to a sign-up; an optional name, the email, the new password with its rule and its
  repetition, the errors of each field tied to it, a password never written back; every word a prop.
- `.gitattributes`: the archive `ux:install` downloads holds the recipes and `src/`, the package's other files left out.
- The recipes `ColorPicker`, `DatePicker`, `CopyButton`, `AnimatedButton`, `Stepper`, `ActivityCalendar`, `Tree`, `Rating`,
  `NumberInput`, `FileInput`, `Tag`, `RelativeTime`, `ProgressRing`, `Sparkline` and `BeforeAfter`, of this kit alone, with
  the controllers they need; every part with its class of phil-ux, under `prefers-reduced-motion`, right to left, at
  375 pixels, and at the keyboard of WAI-ARIA or of the native control they stand on; each works, or shows what it can,
  without a script.
- The recipes `Chart` and `ChartJs`, of this kit alone. `Chart` draws bars or lines in SVG on the server, on a scale of
  four to six plain steps whatever the values, a missing value breaking the line, the last 366 labels and the first
  six series kept, each series in a tone of phil-ux, its line from the second on with a dash of its own, and a
  legend; `ChartJs` puts the canvas of Symfony UX Chart.js in the same figure. The drawing hidden, both write the
  table of their values, read by screen readers or shown in a disclosure.
- `Tooltip`: the controller writes on the bubble the side it went to, `isTop`, `isBottom`, `isStart` or `isEnd`, for
  phil-ux to draw the arrow, and keeps the arrow on the trigger when the bubble is pushed back in the viewport.
- The suite `tests/Browser`, on the real `@hotwired/stimulus` pinned in its own `package.json`.
- `Phil\UxKit\Test\BrowserTestCase` and `Phil\UxKit\Test\RecipesPage` in `src/Test/`, autoloaded: the headless Chrome
  and the page of every recipe in clear, in dark and right to left, for the browser suites of the kits built on the
  same controllers; the build of Stimulus is the suite's own; `clickBeside()`, a real click on the backdrop beside an
  element.
