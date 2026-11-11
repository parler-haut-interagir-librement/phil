# Extension points

The library is plain PHP and declares no service. What an application adds is fixers and hyphenation patterns, and
what it chooses is which fixers run for a locale and which elements are left alone.

## `Fixer`

`Phil\Typography\Fixer\Fixer` — one typographic rule applied to a run of text. See [Fixers](fixers.md#writing-a-fixer).

| Method | Contract |
|---|---|
| `fix(Run $run): Run` | Rewrites text only, through `Run::replace()`, `Run::map()` or `Run::characters()`: what it returns is written into text nodes, so markup it writes stays characters |

## Typographers

| Point | How |
|---|---|
| The fixers of a locale | `Typographer::for($locale)` gives the preset of `Preset`; `new Typographer($fixers)` takes any list, the application's fixers among the shipped ones, in the order they run |
| One typographer per locale | `Typographers`: a default locale, and fixer names by locale in place of their presets |
| Elements left alone | The `$protected` tags of `Typographer::for()`, the constructors and `Typographers`: `Typographer::PROTECTED_TAGS` by default — `pre`, `code`, `kbd`, `script`, `style`, `textarea` |
| Hyphenation | The `$hyphenation` flag of `Typographer::for()`, and the `Dictionary` it reads patterns from |
| Patterns the library does not ship | `new Dictionary($directory)` over `<tag>.pat.txt` and `<tag>.hyp.txt` files, or `Patterns::parse()` and `new Hyphenator($patterns)` — see [Hyphenation](hyphenation.md#patterns-of-your-own) |

The library never fetches patterns: patterns under a licence it does not ship are the application's to provide, under
its own responsibility.
