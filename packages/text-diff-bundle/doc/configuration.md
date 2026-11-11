# Configuration

```yaml
phil_text_diff:
    max_edits: 1000
    tokenizer: words
    html_sanitizer: html_sanitizer.sanitizer.app
```

| Key | Default | Meaning |
|---|---|---|
| `max_edits` | `1000` | The most edits a comparison searches for, from zero; past it, the rest of the two texts is shown as replaced whole |
| `tokenizer` | `words` | What `text_diff` compares by when the template names nothing: `words`, `lines`, `paragraphs` or `html` |
| `html_sanitizer` | none | Service id of a `Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface`: the HTML a comparison by `html` is given unmarked goes through it; without one, that HTML is escaped |

`max_edits` keeps time and memory in check on texts rewritten from end to end: the search grows with the length of
the texts times the number of edits, and its memory with the square of that number. A thousand keeps a comparison
within a few megabytes; the documentation of `phil/text-diff` says more under *Comparing texts*.

`html_sanitizer` names one of the sanitizers FrameworkBundle declares:

```yaml
framework:
    html_sanitizer:
        sanitizers:
            app.revisions:
                allow_safe_elements: true

phil_text_diff:
    html_sanitizer: html_sanitizer.sanitizer.app.revisions
```

A configuration value outside these — a negative bound, an unknown tokenizer — fails the container build.
