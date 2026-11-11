# Testing

## The library's tests

The suite `tests/Unit` checks every type with data-driven cases: its least data completed with defaults, its full
data kept in order, each required value, each bound, each choice, each length and form, each older form read, and the
exact HTML and text of each type and tune. The sanitizer is tested on the tricks a fragment can hold — scripts in any
case, encoded addresses, misnested and unclosed elements, quotes in attributes.

From the monorepo root:

```sh
vendor/bin/phpunit packages/ujo/tests
```

The library is held to 100 % covered mutation score by Infection.

## Fuzzing

The fuzz target `ujo` gives the decoder and the renderers inputs nobody wrote, from the seeds of
`tests/Fuzz/corpus/`: a document either decodes and renders, or fails with an exception of the library. From the monorepo root:

```sh
composer fuzz -- ujo
```

## Benchmarks

`UjoBench` decodes, checks and renders documents of 10, 100 and 1 000 blocks of every common type, and a paragraph of
inline tags nested 1 000 and 5 000 deep; each step grows with the number of blocks, never with its square. A document
rendered as the normalizer returned it is not checked again. From the monorepo root:

```sh
composer bench -- packages/ujo/tests/Benchmark
```

## Testing code that stores documents

Normalize in the test what the editor would send, and compare canonical forms:

```php
$ujo = new Ujo();
$stored = $ujo->encode($ujo->decode($postedJson));

self::assertSame('<p class="ujo-paragraph">Hello <b>world</b></p>', $ujo->toHtml($stored));
```

A document built by hand does not need ids from an editor: any identifier of 1 to 64 letters, digits, `_` or `-`,
unique in the document, will do.
