# Checking

## HTML

```php
$report = $checker->checkHtml($html);            // the language of the content is the policy's
$report = $checker->checkHtml($html, 'fr-CA');   // a content that does not say its language is in French
```

The HTML is a whole page or a fragment. It is parsed by `Dom\HTMLDocument`, as a browser parses it, after two checks:
a content larger than the policy's `maxBytes` throws `LimitExceeded` before it is parsed, and one that is not UTF-8
throws `InvalidContent`. Its elements are then indexed once, in document order and without recursion: elements nested
deeper than `maxDepth`, or more than `maxElements`, throw `LimitExceeded`. Each rule that implements `HtmlRule` and that
the policy keeps then reads the index.

A content that begins — after a byte order mark, spaces and comments — with `<!doctype` or `<html` is a **whole page**:
`html-lang` checks its language, and `heading-h1` notes a page without first-level heading. Any other content is a
fragment shown inside a page.

## UJO

```php
use Phil\Ujo\Ujo;

$document = new Ujo()->decode($json);            // read and normalized by phil/ujo
$report = $checker->checkUjo($document, 'fr');
```

The blocks are visited in document order, each nested block right after its parent, whether the document was
normalized or not: a nested block is a `Block` of `phil/ujo`, or an array of the form `{id, type, data}`. A block's
language is the one of its `language` tune, else its parent's, else the document's. Each rule that implements
`UjoRule` reads what a block's data says — the alternative text of an `image` block, the `title` of an `embed`, the
tracks of a `video`, the `level` of a `header` — and the rules that read HTML read each field of the data that holds
HTML, parsed apart, so that a finding names the block and the field.

A text of the data that holds a `<` is HTML, unless it is a plain field of its type: the source of a `code` block, the
LaTeX of a `math` block, every field of a `chart`… ([extension points](extension-points.md#the-plain-fields-of-ujo)).
The blocks count against `maxElements` and the nesting of blocks and data against `maxDepth`; each HTML field is
bounded as an HTML content is.

## The report

| Member | Gives |
|---|---|
| `passes(Severity $minimum = Error)` | whether no finding is `$minimum` or graver: whether the content may be published |
| `findings(Severity $minimum = Notice)` | the findings `$minimum` or graver |
| `withSeverity(Severity)` | the findings of exactly one severity |
| `ofRule(string)` | the findings of one rule |
| `results`, `result(string)` | the `RuleResult` of each rule that ran — identifier, criterion, severity, number of findings, `passed()` —, or of one |
| `message(Finding)` | what a finding says, in the report's locale |
| `inLocale(string)` | the same report, its messages in another locale |
| `count()`, `toArray()`, `jsonSerialize()` | the number of findings; the report as an array and as JSON |

The findings come rule after rule, in the order the rules run, and in the order of the content within a rule. A rule
that does not read the format checked — `html-lang` on a UJO document — or that the policy turns off has no result.

## A finding

| Property | Holds |
|---|---|
| `rule` | the identifier of the rule, such as `image-alt` |
| `criterion` | the `SuccessCriterion` of WCAG 2.2 it fails, with `title()`, `level()` and `understandingUrl()` |
| `severity` | `Severity::Error`, `Warning` or `Notice` |
| `message` | a `Message`: the key of the domain `phil_accessibility_check` and the values of its placeholders |
| `location` | a `Location` |

A `Location` holds what is known of where the finding is: in HTML, the `line` (from 1), the `element` path from the
body, such as `div:nth-of-type(2) > p > a`, and a `snippet` of its opening tag cut to 160 characters; in UJO, the
`block` identifier, the `blockType` and the `pointer` of the field, such as `blocks[2].data.items[0].content`, with the
element path inside the field when the finding is in its HTML. `describe()` writes it for a person, `toArray()` keeps
the fields that are known.

## JSON

```json
{
    "passed": false,
    "locale": "en",
    "counts": {"error": 1, "warning": 0, "notice": 0},
    "rules": [
        {"rule": "image-alt", "criterion": "1.1.1", "level": "A", "severity": "error", "passed": false, "findings": 1}
    ],
    "findings": [
        {
            "rule": "image-alt",
            "criterion": "1.1.1",
            "level": "A",
            "severity": "error",
            "message": "The image /a.png has no text alternative: describe it in an alt text, or give it alt=\"\" when it is only decoration.",
            "messageKey": "image-alt.missing",
            "parameters": {"source": "/a.png"},
            "location": {"block": "i1", "blockType": "image", "pointer": "blocks[0]"}
        }
    ]
}
```

`passed` is `passes()`, at the error severity. `messageKey` and `parameters` let an application translate a finding
with a translator of its own.
