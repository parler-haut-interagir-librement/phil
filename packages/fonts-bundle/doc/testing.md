# Testing

## The bundle's tests

From the monorepo root:

```sh
vendor/bin/phpunit packages/fonts-bundle/tests
```

`tests/Unit` writes small font files byte by byte (`Fixture\Fonts`: TrueType, OpenType, collections, WOFF and WOFF2)
into temporary directories, and reads WOFF2 through `str_pad`, which returns the stream of the fixture as it is, so the
WOFF2 paths run with and without `ext-brotli`. The media library runs in memory, and the TrueType faces go through the
native writer of `phil/paged-media`. `tests/Functional` boots a small application — FrameworkBundle, TwigBundle — and
checks the catalogue, `fonts_css()` and the commands. The bundle is held to 100 % covered mutation score by Infection.

## Testing an application that uses it

Point `phil_fonts.directory` at a directory of test fonts in the test environment, or build a `FontCatalogue` of a
temporary directory in a unit test. To check that a build refuses an unchecked font, run `phil:fonts:licences` with
`CommandTester` and assert on its exit code.
