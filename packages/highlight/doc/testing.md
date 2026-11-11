# Testing

## The library's tests

The suite `tests/Unit` tests each tokenizer on exact expectations — every token, written `{type:text}` — for each
language and each branch of its reading, every word of every shipped list read as its list says, the cursor and the
output, the HTML and the highlighter, and the lines: their spans, numbers, ranges and annotations in every kind of
comment, the line ranges and their bounds. A robustness test feeds every language sources it was not written for,
checks that no byte is lost and no token is empty, nor a line lost when the block is written line by line, and reads
hostile sources — ten thousand open quotes, brackets or marks — under a second each.

From the monorepo root:

```sh
vendor/bin/phpunit packages/highlight/tests
```

The library is held to 100 % covered mutation score by Infection; the robustness test is left out of it.

## Fuzzing

Two fuzz targets read inputs nobody wrote, from the seeds of `tests/Fuzz/corpus/<target>`: `line-ranges`, whose ranges
read back to themselves or are refused with `InvalidLineRanges`, and `line-annotations`, the lines of a block in five
languages with their annotations read, one `hl-line` per line and, without annotations, the code back byte for byte.
From the monorepo root:

```sh
composer fuzz -- line-ranges line-annotations
```

## Benchmarks

`HighlighterBench` highlights sources of 1, 10 and 100 KB in PHP, JavaScript, HTML, CSS, YAML, Twig and SQL, and sources
made of strings and comments left open; it also builds the default highlighter. The time grows with the length of the
source. From the monorepo root:

```sh
composer bench -- packages/highlight/tests/Benchmark
```

In worker mode, `tests/Worker/worker.php` is a page FrankenPHP serves from one process across requests, highlighting 10 KB of PHP and 10 KB of JavaScript; the
services it builds before its loop serve every request, as they do in an application's worker:

```sh
composer bench:worker phil/highlight
```

## Testing code that highlights

The output is deterministic. Compare it exactly, or compare tokens:

```php
self::assertSame('<span class="hl-keyword">echo</span> <span class="hl-number">1</span>', Highlighter::default()->toHtml('echo 1', 'php'));
```

A test of a page that shows code need not depend on the colours: check that the code's text is in it, escaped, and
that the block carries its `language-…` class.
