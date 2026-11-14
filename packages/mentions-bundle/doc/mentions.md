# Mentions

A `Mention` is one of two things:

| Made by | Holds |
|---|---|
| `Mention::ofHandle('ada', 6)` | A handle typed after `@`, and the character offset of the `@` in the text |
| `Mention::ofMember($reference, '@Ada')` | A member named by reference, and the label the mark shows |

`key()` tells two mentions of the same member apart from the others: `handle:ada`, compared without case, or
`member:user:42`. A text that mentions a member twice counts them once.

## Plain text and Markdown

```php
use Phil\MentionsBundle\Application\Extraction\TextMentions;

new TextMentions()->find('Thanks @ada, and @Bob.');            // @ada at 7, @Bob at 17
new TextMentions()->find('See `@code` and @ada', markdown: true); // @ada only
```

A `@` counts only where a word does not go on before it: `ada@example.com`, `\@ada`, `@@ada` and
`https://example.test/@ada` hold no mention. In Markdown, these are not read:

| Construct | Example |
|---|---|
| Fenced code blocks, of backticks or tildes, up to the same fence or the end of the text | ```` ``` ```` … ```` ``` ```` |
| Code spans on one line, of one or two backticks | `` `@ada` `` |
| The destinations of links and images | `[text](https://example.test/@ada)` |
| Links in angle brackets | `<https://example.test/@ada>` |

Offsets are counted in characters of the original text, whatever was blanked. A text that is not valid UTF-8 holds no
mention.

## The pattern

`MentionPattern` says what a handle looks like after its `@`. The default takes 1 to 64 letters of any script, digits,
`_`, `.` and `-`, and starts and ends with a letter, a digit or `_`: in `Thanks @ada.` the handle is `ada`, and in
`@jean-luc.picard` it is `jean-luc.picard`.

An application gives its own, in `pattern`:

```yaml
phil_mentions:
    pattern: '[a-z0-9_]{3,20}'
```

The expression is checked once, when the container is built. It is refused when it:

- is empty or longer than 256 bytes;
- holds a `~`, or a group named `handle`;
- does not compile on its own — unbalanced parentheses could escape the group around it —, or around the `@`;
- matches an empty handle.

It is read in UTF-8, so `\p{L}` names any letter.

## UJO documents

```php
use Phil\MentionsBundle\Application\Extraction\UjoMentions;

UjoMentions::find($document); // the marks <a data-mention="kind:id">…</a>, block by block
```

The block editor of `phil/block-editor-bundle` writes a chosen member as `<a data-mention="user:42" href="…">@Ada</a>`,
which `phil/ujo` keeps. `UjoMentions` reads every string of every block, nested blocks included, in reading order; the
label is the text of the mark, its entities decoded. A mark whose reference is not one, `kind:identifier`, is passed
over. The document is read in the canonical form `phil/ujo` writes: decode or normalize it first.

## Limits

`MentionLimits` bounds what one text may do:

| Limit | Default | Range | Refused with |
|---|---|---|---|
| `perText` | 20 | 1 to 1 000 | `MentionsRefused::tooMany()`, before the directory is asked |
| `textLength` | 100 000 characters | 1 to 10 000 000 | `MentionsRefused::tooLong()`, before the text is read |

A text over a limit is refused as a whole, so that one message cannot notify a whole community; the application shows
the message of the exception, or its own. Reading is linear in the length of the text: see the benchmarks in
[Testing](testing.md#benchmarks).
