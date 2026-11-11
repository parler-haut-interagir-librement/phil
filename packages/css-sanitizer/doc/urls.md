# URLs

Every URL of a style sheet — `url(…)` unquoted or quoted, in a declaration or in an `@font-face` source, an image of
`image-set()`, the style sheet of an `@import` — goes through the policy's `UrlRewriter`, which returns the URL to write
or `null` to refuse it. A refused URL removes its declaration (`Url`), in `@font-face` that source alone, and the
`@import` rule that holds it.

```php
interface UrlRewriter
{
    public function rewrite(string $url, UrlKind $kind): ?string;
}
```

The URL is given as written, escapes resolved. `UrlKind` says what it loads: `Image` for a declaration — a background,
a list marker, a mask, a filter, an image of `image-set()` —, `Font` for an `@font-face` source, `StyleSheet` for an
`@import`. The output always writes `url("…")`.

## Rewriters

| Rewriter | What it does |
|---|---|
| `Url\RefuseUrls` | Refuses everything; the default |
| `Url\AllowedFiles` | Maps the names a style sheet may write to the public URLs of the project's files, images, fonts and style sheets apart |
| `Url\CallbackRewriter` | Asks a closure |

```php
use Phil\CssSanitizer\Url\AllowedFiles;

$files = new AllowedFiles(
    images: ['paper.jpg' => '/media/42/paper.jpg', 'logo.svg' => '/media/42/logo.svg'],
    fonts: ['body.woff2' => '/media/42/body.woff2'],
    styleSheets: ['theme.css' => '/media/42/theme.css'],
);
```

With `AllowedFiles`, `url(paper.jpg)` and `url("./paper.jpg")` become `url("/media/42/paper.jpg")`; any other URL —
another site, another file of the same site, `../paper.jpg` — is refused. A font name is not an image name, and
neither is the name of a style sheet.

```php
use Phil\CssSanitizer\Url\CallbackRewriter;
use Phil\CssSanitizer\UrlKind;

$rewriter = new CallbackRewriter(fn (string $url, UrlKind $kind): ?string =>
    $this->media->publicUrlOf($project, $url, $kind));
```

## `image-set()`

`image-set()` and `-webkit-image-set()` are not functions of the policy: the sanitizer reads them itself. Each option is
an image — a string, read as a URL, a `url()`, or a function the policy allows, such as a gradient — followed by at most
one resolution (`1x`, `2dppx`, `96dpi`, `38dpcm`) and at most one `type()` that holds a string. Each URL goes through
the rewriter with `UrlKind::Image` and is written `url("…")`; `type()` loads nothing and is kept as written.

```css
a { background-image: image-set("paper.jpg" 1x type("image/jpeg"), url(paper@2x.jpg) 2x) }
/* a { background-image: image-set(url("/media/42/paper.jpg") 1x type("image/jpeg"), url("/media/42/paper@2x.jpg") 2x) } */
```

One URL refused refuses the declaration (`Url`, `DangerousUrl`); an option of another form refuses it too (`Value`).

## `@import`

When the policy allows `AtRuleKind::Import`, each `@import` that opens the style sheet has its URL — a string or a
`url()` — rewritten with `UrlKind::StyleSheet`, and is written first in the output. A URL the rewriter refuses, or a
fragment, removes the rule (`Url`). See [At-rules](at-rules.md).

## Fragments

A URL made of a fragment alone — `url(#blur)` for an SVG filter, `url(#chapter-2)` in `target-counter()` — points into
the document and loads nothing. It is kept as it is and never reaches the rewriter. An `@import` of a fragment would
load the page itself as a style sheet: it is refused.

## Schemes that run code

A URL whose scheme is `javascript:`, `vbscript:`, `livescript:` or `mocha:` is refused (`DangerousUrl`) before the
rewriter sees it, after ASCII white space and control characters are taken out — `" java\9 script:"` is caught. A URL
the rewriter returns is checked the same way.

## What loads nothing

`image()`, `cross-fade()`, `element()`, `src()` and `paint()`, which take a resource from a string or from elsewhere in
the page, can never be allowed. `@supports` conditions holding a URL are refused, inside `@import` too, and media
queries cannot hold one.
