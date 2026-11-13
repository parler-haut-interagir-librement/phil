# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, draws, keeps, redraws and refuses drawings, signs and checks keys, and calls the
Mermaid adapter through Symfony's `MockHttpClient`. In the monorepo, Infection holds the package at 100 % of covered
mutants.

`tests/Integration` calls the real Mermaid service at `PHIL_MERMAID_URL` and is skipped without it:

```sh
docker build -t phil/mermaid-service vendor/phil/svg-blocks-bundle/docker/mermaid
docker run --rm -p 3000:3000 phil/mermaid-service
PHIL_MERMAID_URL=http://localhost:3000 vendor/bin/phpunit --no-configuration tests/Integration
```

## Bench

`tests/Benchmark/StoredMarkupBench.php` measures the rendering of a document with three drawings, the hot path of
reading, and the save of an unchanged document.

## Testing code that draws blocks

Give `BlockMarkup` renderers of your own — a `MarkupRenderer` that returns a fixed markup — and a `Signer` with a test
secret.
