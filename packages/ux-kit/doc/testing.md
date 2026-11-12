# Testing

| Area | What is covered |
|---|---|
| The kit | The kit's manifest and icon, every directory a recipe or left out of the archive, each recipe's manifest, type, name, README and copied files, the recipes it depends on, the controllers its templates name, every prop documented as the Toolkit asks |
| `Logo` | Through `Picture`: an image alone, a WebP source with its fallback linked home, AVIF then WebP by density, size and loading, the theme and the locale replaced in every URL, escaping, a refused URL left out, `alt` required |
| `AnimatedHeadline` | One word, one word with `loop` and no button, several with text around them, the pause button and its labels, the label and the separator, the level kept a heading, escaping |
| `Illustration` | The seed choosing, negative seeds, every candidate drawn without a seed, one URL, sizes, a caption, no candidate |
| `Image` and `Picture` | Lazy and asynchronous by default, `priority` and the choices that override it, unknown values falling back, the density and width maps, `sizes` after `auto,` while lazy, a decorative image leaving out `title` and `aria-*`, the caption and its `figure`, none for an empty caption, kept under a decorative image, the allowed attributes alone, escaping, `javascript:`, `vbscript:` and non-image `data:` URLs refused in every disguise, `data:image/` allowed, `src`, `alt`, `width` and `height` required; the sources in their order, by density, by width with art direction, sized automatically while lazy, a source of an unknown type or without candidates left out, the `Image` alone without sources; the layouts and the `sizes` they compute, each ratio, the fit and the crop, unknown values left out; art direction, the sources with a query first in their type order, a query or a size of the wrong shape |
| `Image` and `Picture` in 0.3 | The preload of the main image through WebLink, its attributes and its absence without `priority`, with `preload: false` or with a media query; the dark and still variants first, as URLs, maps or sources of their own types; the credit, the license and its link, the creation in the words of the vocabulary or of the site, from a code or an enum; an image of a listed host fetched without a referrer, one of another host refused, the page's own origin; `image_sources()` on the assets of AssetMapper |
| `Image` and `Picture` in 0.4 | `deferred` and `fetchpriority="low"`, `priority` winning; each placeholder's class, an unknown one painting nothing; a preview handed to its controller beside one of the page, only a tiny raster image inline in base 64 kept; the variant for forced colours first and never preloaded; the description in its disclosure before the caption, escaped, described by the image beside a description of the page, its identifier from that of the image or its own, a decorative image showing it without being described; `Gallery`, `Lightbox` and `Logo` carrying them; the warnings of `image_check()` in debug, none when turned off or without debug; the priority, layout, ratio, fit and crop of `Logo`, carried by `ComingSoon` with the size of its logo and the loading of its illustration |
| The images in 0.5 | A caption laid over its image, its figure's class, below by default and nothing laid without a caption; `Gallery` as a carousel, its list reached by the keyboard, the names of its markers and buttons, the position of its captions; `Hotspots`, each point a link placed by its classes to the nearest five per cent and by its data attributes, kept on the edge, a point without words, address or place or with a refused address left out, the picture's sources and preload, a refused image; `Hero`, its image the main one of the page, preloaded, covering, its sizes and sources, its heights and alignments, lazy below the fold, a refused image leaving the content; `Video`, its poster, sources and tracks, nothing fetched and no autoplay, a refused source, track or poster left out, its transcript described, nothing without a source; `Figure`, its images under one caption, credit, license and creation, its columns, the grid alone without them |
| `image_filters()`, `picture_props()` and `structured` | `image_filters()`: the sets of an image sorted by width, AVIF then WebP as sources, the fallback's choice, the width read from the last resizing filter or given, the size only when cropped exactly, an unknown set or an unreadable width, the function without LiipImagine, and a `Picture` rendered through LiipImagine itself; `picture_props()`: the first provider that supports the object, its options, the props kept, an object no provider supports; `structured`: the `ImageObject` before the figure or the picture, without a nonce, its address made absolute and read by `phil/url`, its sizes, license and creation, the graph of the page, nothing for a refused or inline image or unless asked, a caption unable to close the script |
| Frames, pixel art, view transitions and the strict pages | `frame`, each word a class, an unknown one left out, `token`; `pixelated`; `transition`, a class and `data-transition`, a name that is no CSS identifier or a keyword of `view-transition-name` left out; carried by `Picture`, `Gallery`, each image its own name, `Lightbox`, the frame and the name on the thumbnail, pixel art on both, and `Hotspots`, never a token; never a style. `RecipesPage`: each mode under the strict policy, its inline scripts that run given its nonce, data blocks and scripts with a source left out, a nonce for each page, the watcher, the stylesheet of the page |
| `Gallery` and `Lightbox` | The list named by its label, its figures, the lightbox of each image and their identifiers, the sizes of the grid, a refused image taking no place; the thumbnail in its button with the invokers, the dialog and its close button, the full image shown whole, the caption under both, the labels as props, a decorative image named by its caption, a refused image, `id` required |
| `image_origin()` and `image_sources()` | `ImageOrigin`: relative paths, inline images and the page's origin local, the hosts of the list and those under them external, every other host, credentials, an IP address, `localhost` unless allowed and every other scheme refused, a `srcset` cut at every space and comma, no host hiding behind a comma, and judged by its worst piece; `ImageSources`: the formats and densities of an image, a single file without descriptor, widths, the formats and fallbacks chosen, the size of the fallback, a path refused, no fallback |
| `AutosaveStatus` | The live region, the hidden notice, its buttons with their targets, actions and default labels; the labels as props, escaped; a `class` added |
| `ReactionButton` | Pressed or not, with an emoji or an image and its replacement text, the counter and its label, none at zero, plain text when it cannot be pressed, other attributes on the root |
| `ReactionPicker` | The disclosure, the hidden search, the groups, each button with its attributes and the text searched, the attributes outside the allowed ones left out, escaping, the labels as props |
| `ComingSoon` | The whole page, its landmarks, the content block; without landmarks nor illustration |
| The controller `animated-headline` | The next word, the timings fitted within the budget, one word revealed once then still, the default run played through once back to the first word within the budget, the server's current word, the loop and its pause/play button, hover and focus holding the word, `transitionend` and its fallback after the duration, the document hidden and shown again or hidden from the start, reduced motion on and off, resize, disconnect, nothing to drive; under Node's test runner |
| The recipes of the Shadcn kit | Each one rendered whole: its elements, its classes, its roles and `aria` attributes, its Stimulus attributes, its variants and sizes, its parts, a tag or a colour refused, the texts as props |
| The manifests | Every template's props and blocks documented, no word written in a template, the names of components and controllers literal, as the Toolkit rewrites them for a subdirectory; a controller named by a template shipped by its recipe or one it depends on |
| The recipes of the kit of its own | `CopyButton`, `AnimatedButton`, `Stepper`, `ActivityCalendar`, `Tree`, `Rating`, `NumberInput`, `FileInput`, `Tag`, `RelativeTime`, `ProgressRing`, `Sparkline`, `BeforeAfter`, `ColorPicker`, `DatePicker`: rendered whole, their props, limits and escaping |
| The recipes of the Shadcn kit on native elements | `AlertDialog` without a dismissal on the backdrop, `AspectRatio` refusing anything but a number or a quotient, `Collapsible`, `Field` and its errors, `Item`, `Kbd`, `NativeSelect`, `Progress` kept within its bounds, `RadioGroup`, `ScrollArea` a region only when named, `Toggle` and `ToggleGroup` on checkboxes and radio buttons: rendered whole |
| The charts | `Chart`: the scale in plain steps from the smallest values to the largest, its decimals, a flat chart, bars sharing their band, a line broken at a missing value, a dot alone, the legend, the table shown or read by screen readers alone, the labels and series bounded; `ChartJs`: its figure and its table read from the chart's data, `render_chart` given by a stub |
| The blocks `LoginForm` and `SignupForm` | Rendered whole, alone and after a refused submission: the field names, the autocompletion, the error tied to the fields, the CSRF field, the links written only with their address and text, a password never written back |
| The controller `image-preview` | A tiny raster image inline alone kept, its blur drawn in an inline SVG at the ratio of the image, painted through the CSSOM and taken away at the load, the error or the disconnection, an image already loaded or failed left alone; under Node's test runner |
| `AspectRatio`, `Badge`, `Screenshots` and `ImageMigration` | `AspectRatio` and a coloured `Badge` without a `style` attribute: a common ratio as a class, any other in `data-ratio-width` and `data-ratio-height`, the colour in `data-color`; `Screenshots`, the shrinking to a scale of the CSS width, the threshold on each channel, a size off by more than a pixel, a row or a column off, the tolerance; `ImageMigration`, real templates made neutral, an image proposed or left with what stands in its way, a picture and its sources as Twig expressions, comments skipped, the proposals rendered by the recipes; `phil:ux-kit:migrate-images`, its report and `--write` |
| `ImageTextCheck` | The name of the file in all its forms, through `phil/url`, the generic words and the redundant starts in English and in French, a width or a height missing or of the wrong shape, each message |
| The controller `lightbox` | Left to the browser where it runs the invokers and `closedby`, opening and closing the dialog once where it does not, a click on the backdrop closing it and one on the image not; under Node's test runner |
| `Celebration` | Rendered whole: its values, its live region empty until it plays or holding the message from the start with `autoplay`, the canvas in a `template`, an unknown effect drawn as confetti, what plays it before the message, its attributes kept, no style written |
| The controller `celebration` | The duration kept within its bounds, the effects, the tokens, the message and the effect of an action or an event, the confetti and the bursts of fireworks spaced, their motion, opacity and drawing; the message said again, the canvas over the page through the CSSOM in the colours of the tokens, at the device pixel ratio, then removed with its bitmap; autoplay, reduced motion by the system, by an ancestor or turned on while it plays, one animation at a time, the tab hidden and shown again, disconnection, no template or no context; under Node's test runner |
| The controllers `tree`, `date-picker`, `color-picker`, `copy-button`, `number-input`, `file-input`, `tag`, `relative-time`, `before-after` | The keyboard and its mirror right to left, what they write, the limits, the sieve of the files, the focus kept nearby, the timers, under Node's test runner |
| The controllers `popover`, `tabs`, `accordion`, `dialog`, `tooltip`, `carousel`, `select`, `dropdown-menu`, `dropdown-menu-sub` | `placement()` of each, the keys and their mirror right to left, what the browser does left to it, the state on the trigger and the panel, the focus, the delays, automatic play held and paused, reduced motion, disconnection; for `dialog`, the invokers and `closedby` detected, left to the browser where it runs them and run by the controller where it does not; under Node's test runner, on the small DOM of `tests/js/fake.mjs` |

The components are rendered through TwigComponent by a kernel where each recipe's `templates/` is a path of Twig, as
`ux:install` would copy them. Installed on its own, where FrameworkBundle is a development requirement, the
functional suite is skipped.

## In a browser

`tests/Browser` drives every interactive recipe in a real Chrome with `symfony/panther`, against the stylesheet, in
clear, in dark and right to left: `Accordion`, `AnimatedHeadline`, `Carousel`, `Dialog`, `Sheet`, `DropdownMenu` and its
submenu, `Popover`, `Select`, `Tabs`, `Tooltip` and its arrow, `Switch`, `Tree`, `DatePicker`, `ColorPicker`,
`NumberInput`, `FileInput`, `Tag`, `RelativeTime`, `BeforeAfter`, `CopyButton`, `AlertDialog`, `Toggle`, `ToggleGroup`,
`RadioGroup`, `ScrollArea`, the colours and dashes of the series of a `Chart`, a `Picture` shrunk to a phone at its
ratio and another choosing its crop by the width of the screen, an `Image` kept square, covered and cropped, a `Gallery`
whose columns follow its container, a `Lightbox` opened at its thumbnail and closed by Escape and its button, a
`Picture` following the colour scheme and the motion of the system, emulated through the DevTools protocol, and the
recipes and blocks of the kit at the width of a phone. It checks what only a browser does: the top layer of the popover
API and of `dialog`, the place on screen, the focus, Escape and the arrows, a click on the backdrop of a dialog and of
an alert dialog. axe-core audits the images of the page, the gallery, a lightbox open and the picture with its variants,
against the rules of WCAG 2.2 A and AA, in each mode, and the picture with its variant for forced colours, its
placeholder and its description, the image with its preview and the deferred gallery, a lightbox of it open. The suite
checks that a deferred gallery keeps its figures off screen at their ratio and its focus ring whole, that a preview is
painted until its image arrives, and that a picture shows its variant where colours are forced. axe-core also audits
what 0.5 adds, in each mode: a caption over its image, a gallery laid out as a carousel, an image with its points, a
hero, a video with its captions and transcript, and a figure of several images. The suite checks that the caption lies
on the foot of its image, that the carousel is one row that snaps and scrolls at the arrows, its buttons named, that
each point stands at its place, the same right to left, and under the image on a phone, that the image of a hero covers
it, that a video fetches nothing before it is played, keeps its ratio on a phone and plays its file, and that a figure
lays its images in columns over its caption. The button of the demonstration plays a `Celebration`: its message is said,
its canvas comes over the whole page, hidden from screen readers and letting the pointer through, is drawn on and goes;
the DOM event `celebrate` plays it with its own message, `autoplay` plays it at once and its removal frees the page for
the next one, and nothing is drawn under reduced motion, emulated through the DevTools protocol or asked by
`data-motion="reduce"`; axe-core audits it, waiting and playing, in each mode. A last test checks that every part of a
page holding all the recipes carries a class of the sheet.

Each mode is also written under a strict Content Security Policy, the default policy of a site that sends nothing
inline: `default-src 'self'`, no `'unsafe-inline'` for scripts or styles, the inline scripts of the page itself, its
import map, allowed by a nonce of the page, `img-src 'self' data:`, `object-src 'none'`. The policy is the first element
of the head, in a `meta` element, since the page is served as static files; a watcher, `strict.js`, lists whatever the
browser refuses. Every test of the images and of the celebration runs under it too, and fails at the first refusal: the
previews painted through the CSSOM, the lightboxes, the carousel, the points, the hero, the video and its captions, the
canvas of the celebration. A test opens each strict page, finds nothing refused while every recipe renders, then writes
a `style` attribute and sees it refused. A badge of a colour of its own and an aspect ratio of any ratio hold there
too, without a `style` attribute: the suite measures the box and reads the colour. The suite also checks that the frames, the token, the
pixel art and the name of a view transition come from the sheet, a view transition moving the image under its name; that
the images print legibly, the medium emulated through the DevTools protocol: the carousel a grid printed whole, a figure
never cut and its caption kept with its veil, an image within the page, a deferred gallery rendered, the close button of
a lightbox and the canvas of a celebration left out; and that right to left, a caption reads from the right, a gallery
and a carousel start there, the button at the start of a carousel points to the right and the close button of a lightbox
sits in the top left corner. axe-core audits the frames, the token and the pixel art.

The suite is apart from `composer test`, in `phpunit.browser.dist.xml`, and runs at the end of a batch:

```sh
composer test:browser
```

It needs Chrome and the `chromedriver` of the system on the `PATH`, of the same major version as Chrome: `brew install
--cask chromedriver` on macOS, `apt install chromium-driver` on Debian.
`PANTHER_NO_HEADLESS=1` shows the window; `phpunit.browser.dist.xml` sets `PANTHER_NO_REDUCED_MOTION`, so that what the
suite watches moves. Without the driver the suite fails with Panther's message. The page is written
under the temporary directory by `RecipesPage`, with the media files of `tests/Browser/resources/media`, a two-second
WebM clip and its WebVTT captions, the real `@hotwired/stimulus`, pinned with `axe-core` in
`tests/Browser/package.json` and its lock file and installed by the script: development dependencies of these tests,
which the kit does not publish.

## Visual tests

Each layout of the images is compared with a reference screenshot, kept in `tests/Browser/screenshots/`: an image
`fixed`, `constrained`, `full-width` and cropped to a ratio, a gallery in a grid, a figure of three columns, in flat
colours without text, in a box of a set width. `assertLooksLike()` takes the screenshot of the element, shrinks it to
half its width in CSS pixels, whatever the density of the screen, and counts the pixels whose red, green or blue moved
by more than 48: the test fails above 2 % of them, or when the size changed by more than a pixel. The references are a
few hundred bytes each. The comparison is `Screenshots`, in `src/Test/`, on GD: no other tool.

A missing reference is written from the run and the test marked incomplete, so that it is seen; after a deliberate
change of layout, write them again and review the images in the diff:

```sh
PHIL_UX_UPDATE_SCREENSHOTS=1 composer test:browser
```

On a mismatch, the screenshot seen is written under the temporary directory, in `phil-ux-screenshots/`, beside the
message. The references are taken on the Chrome of the suite; another engine, or Chrome on another system, may smooth
edges differently, which the tolerance absorbs, but not a change of fonts: the layouts compared hold no text.

## Benchmark

`tests/Benchmark/GalleryBench.php` measures, with PHPBench, the rendering through TwigComponent of a gallery of 100
images, each with a `srcset` of three widths: in a grid, each opening a lightbox, deferred, and with the AVIF and WebP
sources of `Picture`. The template is compiled before the measures.

```sh
composer bench -- packages/ux-kit/tests/Benchmark
```

## For another kit

`BrowserTestCase` and `RecipesPage` are published in `src/Test/`, under `Phil\UxKit\Test`, for the kits whose recipes
run the controllers of this one. A suite extends the case and says where its page comes from:

```php
use Phil\UxKit\Test\BrowserTestCase;
use Phil\UxKit\Test\RecipesPage;

final class InteractiveRecipesTest extends BrowserTestCase
{
    protected static function recipesPage(): RecipesPage
    {
        return new RecipesPage(
            new TestKernel('test', true),             // an application where the kit's recipes are installed
            TestKernel::KIT_DIR,                      // the kit, whose */assets/controllers are copied
            __DIR__.'/resources',                     // its app.js, page.html.twig, page.css and media/
            __DIR__.'/var/app.css',                   // its stylesheet
            sys_get_temp_dir().'/my-kit-browser',     // the web root written
            __DIR__.'/node_modules/@hotwired/stimulus/dist/stimulus.js',
        );
    }
}
```

The suite pins its own `@hotwired/stimulus` and requires, for development, what the classes use: `phpunit/phpunit`,
`symfony/panther`, `php-webdriver/webdriver`, `symfony/http-kernel` and `twig/twig`, which `phil/ux-kit` suggests. Each
test then opens one of `modes()`, or of `strictModes()`, the same pages under the strict policy, where `open()` and the
end of the test fail on anything the browser refused, and drives the page with `open()`, `focus()`, `press()`, `click()`,
`clickBeside()`, a real click on the backdrop beside an element, `read()`, `perform()`, `waitUntil()` and
`waitForFocus()`. A suite that returns the directory of its references from `screenshots()` compares parts of its page
with `assertLooksLike()`; it then requires `ext-gd` for development.

## Without a browser

```sh
vendor/bin/phpunit packages/ux-kit/tests/Unit packages/ux-kit/tests/Functional
node --import ./tests/js/register.mjs --test tests/js     # from packages/ux-kit
```

The kit also passes the Toolkit's linter, `ux:toolkit:lint-kit`, run on a copy of the recipes alone, as the archive
holds them.
