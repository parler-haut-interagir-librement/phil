# Testing

## The library's tests

The suite `tests/Unit` tests the seed and its stream of choices, fixed against SHA-256; colors, luminance and contrast
against WCAG; every palette shipped for the contrast of its inks; initials of names in Latin, Greek, Cyrillic,
Devanagari, Han, Kana, Hangul, Arabic, Hebrew and Persian, and of names without letters; the canvas and its escaping;
each style over many seeds; and the generator — an avatar fixed byte for byte, the outlines, accessibility, hostile
names parsed back as XML, styles of the application.

From the monorepo root:

```sh
vendor/bin/phpunit packages/avatar/tests
```

The fuzz target `initials` holds the extraction and the generator to their contract on names nobody wrote;
`tests/Benchmark` measures one avatar of each style. In the monorepo, Infection holds the library at 100 % of covered
mutants.

## Testing code that draws avatars

The generator is deterministic and needs nothing: build it in the test, and compare what the code shows with what the
generator draws for the same seed and options.

```php
$expected = new AvatarGenerator()->svg('member-1', new AvatarOptions(name: 'Ada Lovelace', size: 32));

self::assertStringContainsString($expected, $page);
```
