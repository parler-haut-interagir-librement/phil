# HTML and text

## Three entry points

| Method | Input | Output |
|---|---|---|
| `fix(string $html)` | A fragment, as found in the body of a page | The fragment, serialized as HTML5 |
| `fixDocument(string $html)` | A whole document, doctype, head and title included | The document |
| `fixText(string $text)` | Plain text, read as one block; bytes that are not UTF-8 become `?` | The text |

`Typographers::fixHtml(string $html, $locale = null)` fixes a fragment with the typographer of a locale, the default
one when `null`, and each passage whose `lang` names another language — `<span lang="en">`, `<blockquote lang="de">` —
with the typographer of that language: its quotation marks, its spaces before punctuation, its hyphenation. A passage
in a language no typographer serves is fixed as the text around it; a passage is its own run, so a pair of quotation
marks is never split across two languages.

A fragment is parsed in the context of `<body>`, as `innerHTML` would parse it: a `<td>` outside a table is dropped,
as a browser drops it. The output is serialized by PHP's HTML5 serializer: a no-break space is written `&nbsp;`, the
other characters as they are, attributes with double quotes. A `<` or a `>` in an attribute value is then written
`&lt;` or `&gt;`, as the current HTML standard serializes it and PHP's serializer does not: a tool that reads a tag up
to its first `>`, such as a difference of HTML, never cuts it inside a value.

## Only the text changes

A fixer receives text and returns text; it never sees markup. Elements, attributes — an `alt`, a `title`, an `href`
— and comments are written back as they were parsed. What the fixers add are characters: typographic quotes, dashes,
no-break spaces, soft hyphens. HTML that was safe before is as safe after, which is why the library can run on the
output of a sanitizer.

## Runs of text

The text is fixed block by block. Every text node between two block boundaries forms one `Run`: the parts are the
text nodes in reading order, so a quote opened before `<em>` and closed after it is one pair, and a French space goes
before a `!` that follows a `</strong>`. The boundaries are the elements that open and close blocks — `p`, `h1` to
`h6`, `li`, `td`, `th`, `blockquote`, `div`, `section`, `figcaption`, `dt`, `dd`, `caption`, `option`, `title` and
the others listed in `Blocks::BOUNDARIES` — and `br` and `hr`.

A fixer that changes a part may look at the characters around it — the last character of the parts before and the
first of the parts after — but never changes them: `Run::replace()` keeps a result only when the neighbours are
unchanged. A fixer that pairs things, such as `SmartQuotes`, reads the whole run as one list of characters.

## Protected elements

The text of a protected element is never changed. It still belongs to its run, as a locked part: `"<code>x</code>"`
gets its quotes, and `x <code>y</code>"z"` closes the quote after `y`. The default list is `pre`, `code`, `kbd`,
`script`, `style` and `textarea`; the second argument of the constructor replaces it:

```php
$typographer = new Typographer($fixers, ['pre', 'code', 'kbd', 'samp', 'var', 'script', 'style', 'textarea']);
```

## Emoji

Emoji are not letters, digits or punctuation to any fixer. Every sequence — joined by zero-width joiners, with skin
tones, variation selectors, keycaps, flags or tag characters — comes out byte for byte: hyphenation only breaks runs
of letters and their combining marks, normalization to form C leaves emoji as they are, and a quote next to an emoji
is paired as next to a word.
