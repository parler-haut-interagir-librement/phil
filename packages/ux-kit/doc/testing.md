# Testing

| Area | What is covered |
|---|---|
| The kit | The kit's manifest and icon, every directory a recipe or left out of the archive, each recipe's manifest, type, name, README and copied files, the recipes it depends on, the controllers its templates name, every prop documented as the Toolkit asks |
| `Logo` | An image alone, a WebP source with its fallback linked home, size and loading, the theme and the locale replaced, escaping, `alt` required |
| `AnimatedHeadline` | One word, one word with `loop` and no button, several with text around them, the pause button and its labels, the label and the separator, the level kept a heading, escaping |
| `Illustration` | The seed choosing, negative seeds, every candidate drawn without a seed, one URL, sizes, a caption, no candidate |
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
| The controllers `tree`, `date-picker`, `color-picker`, `copy-button`, `number-input`, `file-input`, `tag`, `relative-time`, `before-after` | The keyboard and its mirror right to left, what they write, the limits, the sieve of the files, the focus kept nearby, the timers, under Node's test runner |
| The controllers `popover`, `tabs`, `accordion`, `dialog`, `tooltip`, `carousel`, `select`, `dropdown-menu`, `dropdown-menu-sub` | `placement()` of each, the keys and their mirror right to left, what the browser does left to it, the state on the trigger and the panel, the focus, the delays, automatic play held and paused, reduced motion, disconnection; for `dialog`, the invokers and `closedby` detected, left to the browser where it runs them and run by the controller where it does not; under Node's test runner, on the small DOM of `tests/js/fake.mjs` |

The components are rendered through TwigComponent by a kernel where each recipe's `templates/` is a path of Twig, as
`ux:install` would copy them. Installed on its own, where FrameworkBundle is a development requirement, the
functional suite is skipped.

## In a browser

`tests/Browser` drives every interactive recipe in a real Chrome with `symfony/panther`, against the stylesheet, in clear,
in dark and right to left: `Accordion`, `AnimatedHeadline`, `Carousel`, `Dialog`, `Sheet`, `DropdownMenu` and its
submenu, `Popover`, `Select`, `Tabs`, `Tooltip` and its arrow, `Switch`, `Tree`, `DatePicker`, `ColorPicker`, `NumberInput`,
`FileInput`, `Tag`, `RelativeTime`, `BeforeAfter`, `CopyButton`, `AlertDialog`, `Toggle`, `ToggleGroup`,
`RadioGroup`, `ScrollArea`, the colours and dashes of the series of a `Chart`, and the recipes and blocks of the kit at
the width of a phone. It
checks what only a browser does: the top layer of the
popover API and of `dialog`, the place on screen, the focus, Escape and the arrows, a click on the backdrop of a dialog
and of an alert dialog.
A last test checks that every part of a page holding all the recipes carries a class of the sheet.

The suite is apart from `composer test`, in `phpunit.browser.dist.xml`, and runs at the end of a batch:

```sh
composer test:browser
```

It needs Chrome and the `chromedriver` of the system on the `PATH`, of the same major version as Chrome: `brew install
--cask chromedriver` on macOS, `apt install chromium-driver` on Debian.
`PANTHER_NO_HEADLESS=1` shows the window; `phpunit.browser.dist.xml` sets `PANTHER_NO_REDUCED_MOTION`, so that what the
suite watches moves. Without the driver the suite fails with Panther's message. The page is written
under the temporary directory by `RecipesPage`, with the real `@hotwired/stimulus`, pinned in `tests/Browser/package.json`
and its lock file and installed by the script: a development dependency of these tests, which the kit does not publish.

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
            __DIR__.'/resources',                     // its app.js and page.html.twig
            __DIR__.'/var/app.css',                   // its stylesheet
            sys_get_temp_dir().'/my-kit-browser',     // the web root written
            __DIR__.'/node_modules/@hotwired/stimulus/dist/stimulus.js',
        );
    }
}
```

The suite pins its own `@hotwired/stimulus` and requires, for development, what the classes use: `phpunit/phpunit`,
`symfony/panther`, `php-webdriver/webdriver`, `symfony/http-kernel` and `twig/twig`, which `phil/ux-kit` suggests. Each
test then opens one of `modes()` and drives the page with `open()`, `focus()`, `press()`, `click()`,
`clickBeside()`, a real click on the backdrop beside an element, `read()`, `perform()`, `waitUntil()` and
`waitForFocus()`.

## Without a browser

```sh
vendor/bin/phpunit packages/ux-kit/tests/Unit packages/ux-kit/tests/Functional
node --import ./tests/js/register.mjs --test tests/js     # from packages/ux-kit
```

The kit also passes the Toolkit's linter, `ux:toolkit:lint-kit`, run on a copy of the recipes alone, as the archive
holds them.
