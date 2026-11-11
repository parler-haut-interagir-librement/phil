# Testing

## The library's tests

The suite `tests/Unit` tests each fixer on its own cases and across parts of a run, the typographer on HTML
fragments, documents and plain text, the hyphenator on small hand-written patterns and on the shipped ones, every
shipped language read once, and emoji sequences through every preset. Each language whose patterns are written for
this library has a list of at least forty common words that must break exactly where its rules allow, within its
minimum lengths, and a test checks that the files under `data/hyphenation/` are those `bin/generate-patterns.php`
writes from the rules.

From the monorepo root:

```sh
vendor/bin/phpunit packages/typography/tests
```

The library is held to 100 % covered mutation score by Infection.

## Benchmarks

`TypographerBench` fixes HTML fragments of 1, 10 and 100 KB in English and French, with hyphenation, and texts made of
quotes, dots and dashes left open; it also builds the French typographer. The time grows with the length of the
fragment. From the monorepo root:

```sh
composer bench -- packages/typography/tests/Benchmark
```

In worker mode, `tests/Worker/worker.php` is a page FrankenPHP serves from one process across requests, fixing 10 KB of French and 10 KB of English HTML; the
services it builds before its loop serve every request, as they do in an application's worker. It needs the intl extension in the FrankenPHP image:

```sh
composer bench:worker phil/typography
```

## Testing code that fixes text

The output is deterministic: the same typographer gives the same string. Compare it exactly, writing the invisible
characters by their escapes so the test says what it expects:

```php
self::assertSame("<p>«&nbsp;Oui&nbsp;»\u{202F}!</p>", Typographer::for('fr_FR', false)->fix('<p>"Oui" !</p>'));
```

The HTML5 serializer writes the no-break space as `&nbsp;` and every other character as itself: the narrow no-break
space (U+202F) and the soft hyphen (U+00AD) appear raw. Build the typographer without hyphenation in tests that do
not check it, so a change of patterns does not change unrelated expectations.
