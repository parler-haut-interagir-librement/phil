# Testing

## The library's tests

The suite `tests/Unit` tests the tokenizer on every kind of token, escape, bad string and bad URL, and on the lines it
counts; the parser on rules, declarations, `!important`, parse errors and its bound of nesting; the serializer on
escapes, on the comments between tokens — every pair of the table of section 9 —, and on reading back what it wrote;
every selector form, allowed and refused; and the sanitizer on each at-rule, each reason of removal, URLs, scope,
limits, and CSS that tries to close its `<style>` element. `NestingTest` and `ImportsAndImageSetsTest` hold the security
cases of nested rules — `&` that would leave the scope, nesting past the policy's levels, nesting past the depth limit —
of `@import`, and of URLs in `image-set()` beside `type()`.

From the monorepo root:

```sh
vendor/bin/phpunit packages/css-sanitizer/tests
```

The library is held to 100 % covered mutation score by Infection.

## Fuzzing

The fuzz targets `css-sanitizer-sheet` and `css-sanitizer-declarations` give the sanitizer inputs nobody wrote,
from the seeds of `tests/Fuzz/corpus/`: whatever it reads, it returns what it kept and never throws. The target
`css-sanitizer-nesting` does the same under a policy that keeps nested rules, `@import` and `image-set()`, and checks
that the sanitized sheet, sanitized again, comes back whole and unchanged. From the monorepo root:

```sh
composer fuzz -- css-sanitizer-sheet css-sanitizer-declarations css-sanitizer-nesting
```

## Benchmarks

`CssSanitizerBench` sanitizes style sheets of 1, 10 and 100 KB, sheets of denied properties, and sheets made of
comments, strings and blocks left open; the time grows with the length of the sheet. From the monorepo root:

```sh
composer bench -- packages/css-sanitizer/tests/Benchmark
```

## Testing code that sanitizes CSS

The output is deterministic. Compare the CSS exactly, and the removals by reason and subject:

```php
$sanitized = new CssSanitizer()->sanitize('a { color: red; position: fixed }');

self::assertSame("a {\n  color: red;\n}\n", $sanitized->css);
self::assertSame(
    [[RemovalReason::Property, 'position']],
    array_map(static fn (Removal $removal): array => [$removal->reason, $removal->subject], $sanitized->removals),
);
```

To test a rewriter, give it to a policy and sanitize a declaration that holds a URL; to test a form, use Symfony's
`ConstraintValidatorTestCase` with `SafeCssValidator`, as the library's own test does.
