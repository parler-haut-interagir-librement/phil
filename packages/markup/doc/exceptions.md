# Exceptions

Every exception of the library implements `Phil\Markup\Exception\MarkupException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidSource` | `InvalidArgumentException` | A UJO source is not JSON, breaks the structure of a document, or holds a block of no known type or whose data breaks its type |
| `UnknownFormat` | `InvalidArgumentException` | A compiler is asked a format no reader knows; or an add-on gives `DefaultCompiler` a name that is already a format, or not 1 to 16 lower-case letters, digits and hyphens starting with a letter |

The message names the format and what is wrong, for example `The ujo source cannot be read: …`, followed by the
message of `phil/ujo`, which is kept as the previous exception. `UnknownFormat` names the format and the formats the
compiler knows, `The format "textile" is not known; use one of: markdown, html, …`, and keeps them in `$known`.

The readers of the formats people write — Markdown, BBCode, the wikis — never throw: any text is a document, and a
construct that is not well formed is text. `DefaultCompiler::compile()` and `compileFor()` throw `InvalidSource` for
the `ujo` format only; HTML and plain text never throw either.
