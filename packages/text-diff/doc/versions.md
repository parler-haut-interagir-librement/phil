# Versions of contents

`phil/content-bundle` keeps the versions of its contents: a published version is frozen, the next edit forks a new
one, and a published body keeps its UJO source with its compiled HTML (`Body`, format `ujo`). The bundle stores and
publishes versions but shows none of them, so there is no screen of its own to plug a difference into; an application
that shows the history of a content compares two versions with this library.

## Two published versions

Both bodies are UJO: compare them as documents.

```php
use Phil\ContentBundle\Domain\Capability\BodyCapable;
use Phil\TextDiff\TextDiff;
use Phil\Ujo\Ujo;

function bodyDifference(BodyCapable $old, BodyCapable $new, TextDiff $differ, Ujo $ujo): string
{
    $before = $old->getBody();
    $after = $new->getBody();

    if (null === $before || null === $after) {
        return '';
    }

    return $differ->documentToHtml($differ->documents($ujo->decode($before->source), $ujo->decode($after->source)));
}
```

Pass the application's `HtmlRenderer` — the one `phil/ujo-bundle` configures — as the second argument of
`documentToHtml()` so that the difference is rendered like the page.

## A draft and a published version

A draft keeps the format of its author: Markdown, BBCode, a wiki syntax, or UJO. Two ways to compare it with the
published version:

- **as the reader will see it**: convert the draft to UJO with the converter of `phil/markup-bundle` (or the
  `BodyConverter` of the content bundle), then compare documents as above. The readers of written formats number
  blocks in reading order (`b1`, `b2`…), so a block inserted near the top renumbers every block after it, and blocks
  are then paired by position rather than by identity; for such texts, the rendered HTML compares better:
  `$differ->toHtml($differ->html($before->html, $after->html))`;
- **as the author wrote it**: when both versions are in the same written format, compare their sources by paragraphs
  or lines, `$differ->paragraphs($before->source, $after->source)`, and show the result in a `pre-wrap` element.

## Other fields

Titles, summaries and other short texts compare by words, `$differ->words()`; a value that is not text — a date, a
flag — needs no library.
