# Exceptions

Every exception of the library implements `Phil\Markup\Exception\MarkupException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `InputTooLarge` | `InvalidArgumentException` | A source is longer than the ceiling of the reader or of `DefaultCompiler` it is given to — `maxBytes`, 1 MiB by default, 4 MiB for UJO —, or an HTML longer than the ceiling of the purifier — `maxBytes`, 4 MiB by default —; `$bytes` and `$limit` say both lengths |
| `InputTooComplex` | `InvalidArgumentException` | A Markdown source holds more lines than the budget of its reader — `maxLines`, 100,000 by default —; `$budget` names the budget, `lines`, and `$limit` its value |
| `InvalidSource` | `InvalidArgumentException` | A UJO source is not JSON, breaks the structure of a document, or holds a block of no known type or whose data breaks its type |
| `MissingExtension` | `LogicException` | `AmmoniaPurifier::defaultPolicies()` is called where the `ammonia` extension of PHP is not loaded; `$extension` names it |
| `UnknownFormat` | `InvalidArgumentException` | A compiler is asked a format no reader knows; or an add-on gives `DefaultCompiler` a name that is already a format, or not 1 to 16 lower-case letters, digits and hyphens starting with a letter |

The message names the format and what is wrong, for example `The ujo source cannot be read: …`, followed by the
message of `phil/ujo`, which is kept as the previous exception. `UnknownFormat` names the format and the formats the
compiler knows, `The format "textile" is not known; use one of: markdown, html, …`, and keeps them in `$known`.

The readers of the formats people write — Markdown, BBCode, the wikis — never throw on what a source says: any text is
a document, and a construct that is not well formed is text. `DefaultCompiler::compile()` and `compileFor()` throw
`InvalidSource` for the `ujo` format only; HTML and plain text never throw either.

Every reader, and `DefaultCompiler` in every format, refuses a source longer than its ceiling by `InputTooLarge`,
`The input is 1048577 bytes long, above the limit of 1048576 bytes.`, before it reads a byte of it: a source is never
cut to fit. See [The ceiling of a source](analyser.md#the-ceiling-of-a-source). Every purifier refuses an HTML longer
than its ceiling the same way, before a sanitizer sees it: see
[The ceiling of an HTML purified](reference.md#the-ceiling-of-an-html-purified). The Markdown reader refuses a source
holding more lines than its budget by `InputTooComplex`, `The input holds more than 100000 lines.`, before it builds
them: see [The budget of lines of Markdown](analyser.md#the-budget-of-lines-of-markdown).
