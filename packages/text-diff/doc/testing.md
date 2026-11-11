# Testing

## The library's tests

The suite `tests/Unit` tests each tokenizer on exact tokens and on giving its text back; the algorithm on exact scripts
— the example of Myers' paper among them —, on the bound of edits and on the joining of white space, and, for every
pair of token lists up to five tokens of two letters and three tokens of three letters, that the script gives both
lists back, is normalized and is shortest, against a longest common subsequence computed separately; the change list
and its offsets in code points; the HTML of plain text and of markup; the pairing of documents — kept, changed,
moved, added, removed, nested, past the bound — and their HTML.

From the monorepo root:

```sh
vendor/bin/phpunit packages/text-diff/tests
```

The library is held to 100 % covered mutation score by Infection.

## Benchmarks

`TextDiffBench` compares texts of 1, 10 and 50 KB with a few edits, texts rewritten from end to end, which stop at the
bound of edits, and documents of 100 and 1 000 blocks. From the monorepo root:

```sh
composer bench -- packages/text-diff/tests/Benchmark
```

In worker mode, `tests/Worker/worker.php` is a page FrankenPHP serves from one process across requests, comparing 5 KB of text word by word and 5 KB of HTML; the
services it builds before its loop serve every request, as they do in an application's worker:

```sh
composer bench:worker phil/text-diff
```

## Testing code that shows differences

The output is deterministic. Compare HTML exactly, or compare the change list:

```php
self::assertSame('a <del>b</del><ins>c</ins>', (new TextDiff())->toHtml((new TextDiff())->words('a b', 'a c')));

$changes = (new TextDiff())->words('a b', 'a c')->changes();
self::assertSame([ChangeKind::Replacement, 'b', 'c'], [$changes[0]->kind, $changes[0]->old, $changes[0]->new]);
```

For documents, assert on the kinds and identifiers of `DocumentDiff::changes()` rather than on rendered HTML, which
depends on the renderer.
