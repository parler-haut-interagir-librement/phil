# Testing

## The library's tests

The suite `tests/Unit` tests the policy — its defaults, every `with…()`, what is never allowed —, every reason of
removal and of refusal, references and their scheme tricks, the CSS of attributes and of `<style>` elements, animation
with and without the policy, and the limits at their bounds.

`Html5DifferentialTest` reads every output as a browser reads an SVG inlined in a page: parsed again by
`Dom\HTMLDocument`, PHP's HTML5 parser, inside a `div`, after a paragraph and inside a link, under the default policy,
with `<style>` elements, with animations and with everything optional. The charges are the bypasses known to SVG
sanitizers — a `<style>` or a CDATA section that closes early, markup in a title, a description or a text, an HTML
integration point, MathML, names of attributes in capitals or under a prefix, schemes hidden by references or
whitespace, an animation that rewrites a link, comments and processing instructions HTML reads otherwise — and those of
the advisories that follow: none may leave a script, an HTML element, an `on…` attribute or a `javascript:` address.
The check itself is tested against pages written by hand that hold each.

From the monorepo root:

```sh
vendor/bin/phpunit packages/svg-sanitizer/tests
```

The library is held to 100 % covered mutation score by Infection.

## Fuzzing

The fuzz target `svg-sanitizer` gives the sanitizer inputs nobody wrote, from the seeds of `tests/Fuzz/corpus/`:
whatever it reads, it returns what it kept or a refusal, and never throws nor warns. From the monorepo root:

```sh
composer fuzz -- svg-sanitizer
```

## Testing code that sanitizes SVG

The output is deterministic. Compare the SVG exactly, and the removals by reason and subject:

```php
$sanitized = new SvgSanitizer()->sanitize('<svg><rect onclick="x"/></svg>');

self::assertSame('<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>', $sanitized->svg);
self::assertSame(
    [[RemovalReason::EventHandler, 'onclick']],
    array_map(static fn (Removal $removal): array => [$removal->reason, $removal->subject], $sanitized->removals),
);
```

A test of the application's own policy sanitizes one image that holds what the policy should keep, and one that holds
what it should remove.

A form that validates with `SafeSvg` is tested with Symfony's `ConstraintValidatorTestCase` and `SafeSvgValidator`, as
the library's own test does.
