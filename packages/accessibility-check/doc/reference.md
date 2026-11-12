# Reference

## Checking

| Type | Role |
|---|---|
| `AccessibilityChecker` | Runs the rules the policy keeps over HTML (`checkHtml()`) or a UJO document (`checkUjo()`), and returns a `Report` |
| `Policy` | Limits, headings, contrast and colours, colour tokens, language, generic link texts, rules turned off or given another severity, locale |
| `HeadingPolicy` | `Single`, `None` or `Any`: the first-level headings a content may hold |
| `ContrastLevel` | `AA` or `AAA`: the contrast ratios required, and their success criterion |
| `Severity` | `Error`, `Warning` or `Notice`, with `isAtLeast()` |
| `SuccessCriterion` | The 86 success criteria of WCAG 2.2 by number, with `title()`, `level()` and `understandingUrl()` |
| `ConformanceLevel` | `A`, `AA` or `AAA` |

## The report

| Type | Role |
|---|---|
| `Report` | The outcome of each rule and the findings, filtered, translated and serialized to JSON |
| `RuleResult` | The outcome of one rule: identifier, criterion, severity, number of findings, `passed()` |
| `Finding` | One problem: rule, criterion, severity, message, location |
| `Location` | Where a finding is: line, element path and snippet in HTML; block, type and pointer in UJO |
| `Message` | A key of the domain `phil_accessibility_check` and the values of its placeholders |
| `Violation` | What a rule yields: a location, a message, and its own severity or criterion when it has one |

## Rules

| Type | Role |
|---|---|
| `Contract\Rule` | Identifier, success criterion and severity of a rule |
| `Contract\HtmlRule` | A rule that reads HTML |
| `Contract\UjoRule` | A rule that reads UJO documents |
| `Rule\DefaultRules` | The fifteen rules of the library, in the order they run |
| `Rule\FragmentRule` | The base of a rule that reads the data of each block, then each of its HTML fields |
| `Rule\ImageAlt`, `HeadingOrder`, `HeadingH1`, `EmptyHeading`, `LinkName`, `ButtonName`, `LinkText`, `ColorContrast`, `TableHeaders`, `HtmlLang`, `LangChange`, `FrameTitle`, `VideoCaptions`, `DuplicateId`, `SvgName` | The rules: see [Rules](rules.md) |
| `Rule\Heading` | A heading of a content, from HTML or from UJO |
| `Rule\FileName` | Whether a text alternative is a file name |
| `Rule\GenericLinkText` | The generic link texts of six languages, and how a link name is compared with them |

## Reading

| Type | Role |
|---|---|
| `Html\HtmlTarget` | HTML parsed and indexed, with what the rules ask of an element |
| `Ujo\UjoTarget` | A UJO document as the rules read it: its blocks and its HTML fields |
| `Ujo\UjoReader` | Walks the blocks and their data for `UjoTarget` |
| `Ujo\BlockVisit` | A block as the check meets it: its pointer and its language |
| `Ujo\Fragment` | An HTML field of a block, parsed |
| `Ujo\PlainFields` | The fields of block data that never hold HTML |
| `Colour\Colour` | A colour of sRGB, with the relative luminance and the contrast ratio of WCAG |
| `Colour\ColourParser` | Reads the colours of CSS |
| `Language\LanguageTag` | Whether a language tag of BCP 47 is well formed, and its primary language |

## Messages

| Type | Role |
|---|---|
| `Translation\Messages` | The messages for `symfony/translation`: `resources()`, `translator()`, `translate()`, `locale()` |
| `Translation\English`, `French`, `Spanish`, `German`, `Italian`, `Portuguese` | The messages of each language |

## Symfony

| Type | Role |
|---|---|
| `Validator\AccessibleContent`, `AccessibleContentValidator` | The constraint and its validator |
| `Workflow\PublicationGuard` | The guard that holds back a publication |
| `Contract\SubjectContent` | The content of the subject of a workflow, for the guard |
| `Console\CheckCommand` | The command `phil:accessibility:check` |

## Exceptions

| Type | Role |
|---|---|
| `Exception\AccessibilityCheckException` | Implemented by every exception of the library |
| `Exception\InvalidPolicy` | A policy or a set of rules that cannot be used |
| `Exception\LimitExceeded` | A content over a limit of the policy |
| `Exception\InvalidContent` | HTML that is not UTF-8 |
