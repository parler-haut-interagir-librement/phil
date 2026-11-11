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

## Testing code that draws charts

The image is deterministic: compare it with a golden file, or look for the `<title>` of a mark.
