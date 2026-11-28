# Reference

Every public type of the package, with its role.

## Checking

| Type | Role |
|---|---|
| `Phil\EmailPreview\EmailChecker` | Runs its rules on an email — `DefaultRules::all()` when given none — and returns a `Report`. Keeps no state between two emails. |
| `Phil\EmailPreview\CheckedEmail` | An email as the rules read it: `subject`, `html`, `text`, `sending`, and `document`, its HTML parsed once as a `Dom\HTMLDocument`. `fromRendered()` reads a `RenderedEmail` of phil/email-templates-bundle, `fromMime()` a `Symfony\Component\Mime\Email`. `MAX_HTML_BYTES` is the largest HTML parsed. |
| `Phil\EmailPreview\Sending` | How the email is sent: `bulk`, `headers` by lowercase name, `recipientTokens`. `bulk()` builds a bulk sending; `header()` reads a header whatever the case of its name. |
| `Phil\EmailPreview\Report` | What a check found: the `rules` that ran and their `findings`, in order. `passes()`, `errors()`, `warnings()`, `ofRule()`, `toText()`, `toHtml()`, `toArray()`; countable and serializable to JSON. |
| `Phil\EmailPreview\Finding` | One problem: `rule`, `severity`, `message` and the `subject` it is about, when it is about one thing. `error()` and `warning()` build one; `toText()` writes it on one line. |
| `Phil\EmailPreview\Severity` | `Error`, fixed before the email is sent, or `Warning`, which asks for a look. |

## Previews

| Type | Role |
|---|---|
| `Phil\EmailPreview\Preview\PreviewChecks` | Renders each variant of a template with the `PreviewRenderer` of phil/email-templates-bundle and checks it, as it would be sent. |
| `Phil\EmailPreview\Preview\CheckedPreview` | One preview checked: its `variant`, the rendered `email` and its `report`. |

## Rules

| Type | Role |
|---|---|
| `Phil\EmailPreview\Contract\Rule` | A check of an email: `name()` and `check()`, which yields its findings. |
| `Phil\EmailPreview\Rule\DefaultRules` | `all()`: the six rules below, in the order of the report. |
| `Phil\EmailPreview\Rule\TextPart` | `text-part`: a plain-text part that gives every `http:`, `https:` and `mailto:` address of the HTML; a `mailto:` address is looked for without its scheme. |
| `Phil\EmailPreview\Rule\OneClickUnsubscribe` | `one-click-unsubscribe`: on a bulk sending, `List-Unsubscribe` with an `https:` address in angle brackets, and `List-Unsubscribe-Post` equal to `POST_VALUE`, `List-Unsubscribe=One-Click`. |
| `Phil\EmailPreview\Rule\TrackingPixel` | `tracking-pixel`: no remote image of one pixel or less on both sides, by its `width` and `height` attributes or its style, and no remote image address that holds a recipient token, as is or encoded by `rawurlencode()`. `cid:` and `data:` images are not remote. |
| `Phil\EmailPreview\Rule\NoStyleElement` | `no-style-element`: no `<style>` element; one finding gives their count. |
| `Phil\EmailPreview\Rule\MaxWidth` | `max-width`: no element wider than `pixels`, 600 by default, by its `width` attribute or the `width`, `min-width` or `max-width` of its style. Percentages and other units are not read. |
| `Phil\EmailPreview\Rule\SubjectLength` | `subject-length`: a warning when the trimmed subject holds more than `length` graphemes, 50 by default; a subject that is not valid UTF-8 is counted by code point. |

## Exceptions

| Type | Role |
|---|---|
| `Phil\EmailPreview\Exception\EmailPreviewException` | Every exception of the library. |
| `Phil\EmailPreview\Exception\EmailTooLarge` | An HTML larger than `CheckedEmail::MAX_HTML_BYTES`, refused before it is parsed. |
