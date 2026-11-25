# Testing

```sh
vendor/bin/phpunit packages/reactions-bundle/tests
cd packages/reactions-bundle && node --import ./tests/js/register.mjs --test tests/js
```

| Suite | What it covers |
|---|---|
| `tests/Unit` | The domain, the catalogue, reacting and withdrawing, what a viewer sees, the stores against one contract on SQLite in memory, the guard, the controller, the component, the adapters and the configuration |
| `tests/Functional` | A kernel with the recipes of each kit copied where the Toolkit would, in a subdirectory or not, and the Doctrine store on a SQLite file of its own: the page, the plain forms, the signature, the list of names and the Live Component; with AssetMapperBundle and StimulusBundle, the controllers map StimulusBundle compiles, both controllers fetched lazily |
| `tests/js` | The Stimulus controllers under Node's test runner |
| `tests/Benchmark` | A page of 20 contents read in one batch, and drawn by the whole component in a kernel reset between two requests |
| `tests/Worker` | The page `composer bench:worker phil/reactions-bundle` measures, served by FrankenPHP in worker mode; with `--ext`, on an image with `ext-intl` |
| `tests/Browser` | The checks below in a headless Chrome, with the Live Component and the Stimulus controllers running |

In an application's own tests, the option `store: memory` keeps the reactions in memory for one request: it is
emptied between two. A test that reacts, then reads the page again, uses the Doctrine store on SQLite, as `tests/Functional/App/TestKernel.php` does.

## In a browser

The browser suite runs apart from the others, with the chromedriver of the system and a build of Stimulus named by
`PHIL_STIMULUS_JS`; it is skipped without it:

```sh
PHIL_STIMULUS_JS=node_modules/@hotwired/stimulus/dist/stimulus.js \
    vendor/bin/phpunit --configuration=phpunit.browser.dist.xml packages/reactions-bundle/tests/Browser
```

It serves the kernel of the functional suite through `tests/Browser/public/index.php`, whose page loads the
controllers by an import map, its reactions kept between two requests in the kernel's SQLite file, emptied when the
suite starts, and drives chromedriver on its own port, 9581, beside another suite.

| Check | Expected |
|---|---|
| Pressing a reaction with the keyboard | The focus stays on the button, the counter changes, a screen reader says the reaction is recorded |
| A content drawn twice | Pressing one updates the other |
| The picker | It opens on the search; arrows, Home, End and Escape do as [the component](component.md) says |
| JavaScript off | Every button reacts through the form and comes back to the same page |
| Reached the cap of `per_member` | A sentence after the bar; the other reactions cannot be pressed until one is taken back |

What a screen reader says aloud — the live region, the label and the counter of a button, the disclosure of the names —
is heard by a person, with VoiceOver and NVDA at least: the suite checks the markup they read, not their speech.

## The requests of LiveComponent

`tests/Functional/LiveRequestsTest.php` sends the component the requests the browser sends, and holds it to what
LiveComponent 3.8.1 enforces: under its class name instead of its name, the component answers 404; a 422 raised before
its action, by an argument resolver or a controller attribute, renders nothing of it; and a redirection answers 204 with
`X-Live-Redirect` and its `Location`, without `X-Live-Url`, while an ordinary answer gives back the address of the
page.
