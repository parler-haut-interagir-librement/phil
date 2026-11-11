# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\CssSanitizer\CssSanitizer` | `final readonly class` | Style sheets and `style` attributes sanitized under a policy |
| `Phil\CssSanitizer\Policy` | `final readonly class` | What a style sheet may hold, its rewriter, scope and limits |
| `Phil\CssSanitizer\AtRuleKind` | enum | The at-rules a policy may allow: media, supports, page, font-face, import |
| `Phil\CssSanitizer\Sanitized` | `final readonly class` | What was kept, as CSS and as values, and what was removed |
| `Phil\CssSanitizer\Removal` | `final readonly class` | One removal: its reason, subject and line; `Sanitized` lists the first `MAX_REMOVALS` and counts the others |
| `Phil\CssSanitizer\RemovalReason` | enum | Why something was removed, and its description |
| `Phil\CssSanitizer\UrlRewriter` | interface | What each URL becomes, or `null` to refuse it |
| `Phil\CssSanitizer\UrlKind` | enum | What a URL loads: an image, a font or a style sheet |
| `Phil\CssSanitizer\Url\RefuseUrls` | `final readonly class` | Refuses every URL |
| `Phil\CssSanitizer\Url\AllowedFiles` | `final readonly class` | Maps names to the public URLs of allowed files |
| `Phil\CssSanitizer\Url\CallbackRewriter` | `final readonly class` | A rewriter written as a closure |
| `Phil\CssSanitizer\Serializer` | `final class` | CSS written back from values, declarations and style sheets |
| `Phil\CssSanitizer\Sheet\StyleSheet` | `final readonly class` | The kept `@import` rules and other rules of a style sheet |
| `Phil\CssSanitizer\Sheet\ImportRule` | `final readonly class` | A kept `@import`: its rewritten URL and its conditions |
| `Phil\CssSanitizer\Sheet\StyleRule` | `final readonly class` | A kept style rule: its selectors, declarations and nested rules |
| `Phil\CssSanitizer\Sheet\GroupRule` | `final readonly class` | A kept at-rule with its prelude, declarations and rules |
| `Phil\CssSanitizer\Syntax\Tokenizer` | `final class` | The tokenizer of CSS Syntax Level 3 |
| `Phil\CssSanitizer\Syntax\Parser` | `final readonly class` | The parser of CSS Syntax Level 3 |
| `Phil\CssSanitizer\Syntax\TokenType` | enum | The kinds of token |
| `Phil\CssSanitizer\Syntax\ComponentValue` | interface | A token, a simple block or a function |
| `Phil\CssSanitizer\Syntax\Token` | `final readonly class` | A token with its escapes resolved |
| `Phil\CssSanitizer\Syntax\SimpleBlock` | `final readonly class` | The values between matching brackets |
| `Phil\CssSanitizer\Syntax\FunctionValue` | `final readonly class` | A function and its arguments |
| `Phil\CssSanitizer\Syntax\Stylesheet` | `final readonly class` | The rules of a parsed style sheet |
| `Phil\CssSanitizer\Syntax\QualifiedRule` | `final readonly class` | A prelude and a block |
| `Phil\CssSanitizer\Syntax\AtRule` | `final readonly class` | An at-rule's name, prelude and optional block |
| `Phil\CssSanitizer\Syntax\Declaration` | `final readonly class` | A property, its value and `!important` |
| `Phil\CssSanitizer\Syntax\Malformed` | `final readonly class` | What the parser skipped as a parse error |
| `Phil\CssSanitizer\Check\Sanitization` | `final class`, internal | One pass over a parsed source: rules kept, removals recorded |
| `Phil\CssSanitizer\Check\Selectors` | `final readonly class`, internal | The selector grammar and the policy's names |
| `Phil\CssSanitizer\Check\Values` | `final readonly class`, internal | Values checked, URLs rewritten |
| `Phil\CssSanitizer\Check\Preludes` | `final readonly class`, internal | Media queries, conditions, page selectors and the conditions of `@import` |
| `Phil\CssSanitizer\Check\Imports` | `final readonly class`, internal | An `@import` rule checked, its URL rewritten |
| `Phil\CssSanitizer\Check\FontSources` | `final readonly class`, internal | The sources of `@font-face` checked one by one |
| `Phil\CssSanitizer\Validator\SafeCss` | `final class` | The constraint: CSS kept whole |
| `Phil\CssSanitizer\Validator\SafeCssValidator` | `final class` | Each removal a violation |
| `Phil\CssSanitizer\Exception\CssSanitizerException` | interface | Every exception of the library |
| `Phil\CssSanitizer\Exception\InvalidPolicy` | `final class` | A policy asked for what is never allowed, or an impossible limit |
| `Phil\CssSanitizer\Exception\NestingTooDeep` | `final class` | Blocks nested deeper than the parser's limit |
