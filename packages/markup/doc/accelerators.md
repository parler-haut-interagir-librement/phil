# Accelerators

The library runs in plain PHP. One PHP extension, when it is loaded, purifies faster: `ammonia`, which parses with
html5ever, the HTML5 parser of Servo, and rewrites the tree from allow lists, in Rust. Nothing requires it, and nothing
changes for a reader of the page: both purifiers keep the same elements at each level.

## `AmmoniaPurifier`

`Adapter\AmmoniaPurifier` implements `HtmlPurifier` on the extension. `defaultPolicies($mediaHosts)` translates the
policies of `SanitizerPurifier::defaultPolicies($mediaHosts)` — `SanitizerPurifier::configurations()` gives them —
into one policy of the extension per level: the same elements and attributes, the same link schemes, relative links and
images, no `rel` added to links, comments removed.

```php
use Phil\Markup\Adapter\Purifiers;

$purifier = Purifiers::defaultPolicies();                   // AmmoniaPurifier where the extension is loaded
$purifier = Purifiers::defaultPolicies(['cdn.example.com']); // the hosts images may come from, either way
$purifier = Purifiers::defaultPolicies(extension: false);    // always SanitizerPurifier
```

`AmmoniaPurifier::isAvailable()` tells whether the extension is loaded; `AmmoniaPurifier::defaultPolicies()` throws
`MissingExtension` when it is not. Its `maxBytes`, 4 MiB by default, refuses a longer HTML before either purifier sees
it, as `SanitizerPurifier` does: see [The ceiling of an HTML purified](reference.md#the-ceiling-of-an-html-purified).

### What the two keep

Both outputs are judged on properties, not bytes: nothing that runs, the elements of the level, the same elements once
purified again, and the same elements as the other purifier. The tests give both the same hostile HTML — known XSS
vectors, mutation XSS, address schemes, elements that act on the page, deep trees — at each level, and the fuzz target
`markup-purifier` gives them inputs nobody wrote.

Where the outputs differ, neither keeps anything that runs:

| Input | `SanitizerPurifier` | `AmmoniaPurifier` |
|---|---|---|
| `style` | removed | removed; CSS belongs to `phil/css-sanitizer` |
| `srcset`, `cite`, `poster` and the other addresses besides `href` and `src` | kept at `all` when their scheme is allowed | removed: the extension checks the scheme of `href` and `src` only |
| The scheme of an image | `http`, `https`, or relative | a link scheme, or relative: a `mailto:` or `tel:` image, which no browser loads, may remain |
| An element no standard defines, outside the level | removed with its text | removed, its text kept and escaped |
| The safe attributes at `all`, on MathML elements | removed | kept, as on every HTML element |
| Serialization | `<br />`, `&#43;` | `<br>`, `+` |

Inline SVG stays with `phil/svg-sanitizer`, as with the sanitizer.

### What goes to the sanitizer

`AmmoniaPurifier` gives six kinds of HTML to the `SanitizerPurifier` of the same policies:

| HTML | Why |
|---|---|
| Shorter than `$minimumBytes`, 256 bytes by default (`AmmoniaPurifier::MINIMUM_BYTES`) | The extension builds its policy again at each call, 25 to 45 µs, which a name or a caption does not repay |
| More than 10,000 tags (`AmmoniaPurifier::MAXIMUM_TAGS`) | The time of an HTML5 parser grows with the square of the depth of the tree, faster in the extension than in the sanitizer: nested `div` elements by the tens of thousands take seconds |
| Holding `<noscript` | The extension reads its content as text, as a browser running scripts does, the sanitizer as markup: both are inert, but they keep different elements |
| Not valid UTF-8 | The extension refuses it; the sanitizer gives an empty string |
| Holding `<input` | The sanitizer writes each checkbox again as the disabled checkbox of a checklist at `all`, and removes any other input |
| At `forum` and `all`, holding `src`, when `$mediaHosts` is not `null` | The extension cannot check the host of an image |

## Measures

Purification alone, the HTML a Markdown document compiles to, PHP 8.5 ZTS on FrankenPHP, `PurifierBench`:

| Length | Level | `SanitizerPurifier` | `AmmoniaPurifier` |
|---|---|---|---|
| 300 bytes | `basic` / `forum` / `all` | 38 / 52 / 57 µs | 34 / 34 / 49 µs |
| 12 KB | `basic` / `forum` / `all` | 0.97 / 1.34 / 1.46 ms | 0.53 / 0.62 / 0.58 ms |
| 100 KB | `basic` / `forum` / `all` | 8.1 / 10.8 / 12.5 ms | 4.1 / 4.3 / 4.4 ms |

On 100 KB, the peak memory falls from 43–52 MB to 6.4 MB. A page that compiles 10 KB of Markdown and purifies it
spends most of its time in the reader: in worker mode, the extension moves it by a few percent. The gain is in the
purification of stored HTML, `markup_purify` of `phil/markup-bundle`, and in long texts.

To measure on your machine, in the `bench-ext` image of `phil/dev-tools`, from the monorepo root:

```sh
PHIL_DEV_BENCH_EXT_PIE="switon/ammonia" vendor/bin/phil-dev bench:worker phil/markup --ext --build
vendor/bin/phpbench run packages/markup/tests/Benchmark/PurifierBench.php --report=phil
```

## Installing the extension

The extension is the Composer package `switon/ammonia`, installed by PIE:

```sh
pie install switon/ammonia
```

PIE takes a prebuilt binary when one matches PHP, or compiles it with Rust 1.85 or later. In `composer.json`, the
library suggests it as `ext-ammonia`.

| Constraint | Detail |
|---|---|
| PHP | 8.2 to 8.5, thread safe (ZTS, as FrankenPHP runs it) or not; **not 8.6**: under 8.6 the library uses the sanitizer, and the tests of the extension do not run |
| Systems | Prebuilt for Linux with glibc, x86_64 or arm64, glibc of Ubuntu 24.04 or later; macOS on Apple silicon; Windows x64. Elsewhere, Alpine (musl) among them, PIE compiles it |
| Loading | By path: `extension=/path/to/libammonia.so` (`.dylib` on macOS, `ammonia.dll` on Windows); `extension=ammonia` looks for another file name. PIE writes the line itself |

The purifier in Rust has no memory corruption of its own; the binding between PHP and Rust, written with `ext-php-rs`,
has had no audit. Load the extension where the gain is measured, and keep the sanitizer elsewhere.

## In Symfony

`phil/markup-bundle` chooses with its `purifier` option: `auto` by default, `sanitizer`, or `ammonia`.
