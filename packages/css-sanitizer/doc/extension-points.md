# Extension points

The library is plain PHP and declares no service. What an application chooses is the `Policy` it gives the
sanitizer, and one port: where URLs lead.

## `UrlRewriter`

`Phil\CssSanitizer\UrlRewriter` — every URL of a style sheet goes through the policy's rewriter, which returns the URL
to write or `null` to refuse it. See [URLs](urls.md).

| Method | Contract |
|---|---|
| `rewrite(string $url, UrlKind $kind): ?string` | Takes the URL as written, escapes resolved, and what it loads — `Image`, `Font` or `StyleSheet`; returns a URL that loads nothing the application did not mean, or `null` |

`Url\RefuseUrls`, the default, refuses all; `Url\AllowedFiles` maps file names to public URLs; `Url\CallbackRewriter`
asks a closure. A rewriter of the application's own never returns a `javascript:` URL or a URL of another origin it
has not chosen: the sanitizer refuses schemes that run code, not addresses the application allows.

## The policy

`Policy` is a value: each `with` method returns a new one. See [Policy](policy.md).

| Point | Methods |
|---|---|
| The properties and functions allowed | `withProperties()`, `withoutProperties()`, `withFunctions()`, `withoutFunctions()` |
| The selectors allowed | `withPseudoClasses()`, `withPseudoElements()`, `withAttributes()` and their `without` forms |
| The at-rules allowed | `withAtRules()`, `withoutAtRules()`, `withMediaTypes()` |
| Where the rules apply | `withScope()`: every selector prefixed, so a sheet styles one part of a page |
| `!important` and custom properties | `withImportant()`, `withCustomProperties()` |
| The bounds of the work | `withMaxLength()`, `withMaxDepth()`, `withMaxNesting()` |

What [Never allowed](policy.md#never-allowed) lists is not reachable through any of them.

## The validator

`Validator\SafeCss` uses the `CssSanitizer` given to `SafeCssValidator`: a service declaration with the application's
policy replaces the default one, which refuses every URL — see [Validator](validator.md).
