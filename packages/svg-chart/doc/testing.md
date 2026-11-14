# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, checks the reading of configurations and every refusal, the round ticks of the
scale, the legend, the thinning of labels, the arcs of slices, and whole images against golden files in
`tests/Unit/Fixture`, which were looked at when they were written. In the monorepo, Infection holds the package at
100 % of covered mutants.

The checks of `ResvgRasterizer` — limits, fonts, sanitizing, the order of measuring and drawing — run everywhere, with a
renderer that draws nothing. The tests of the renderer itself carry `#[RequiresPhpExtension('resvg')]` and run where
the extension is loaded: the size of a chart's PNG at each scale, the refusals of the renderer, and the files it never
reads, an absolute path, an escape from its directory and a remote address, each drawn as nothing beside the same
image unconfined. The fixture font, `tests/Unit/Fixture/fonts/test.ttf`, was made for the tests.

## Testing code that draws charts

The image is deterministic: compare it with a golden file, or look for the `<title>` of a mark. Code that draws PNG
images takes a `Contract\Rasterizer`: give it one in its tests that returns known bytes and keeps the SVG it was asked.
