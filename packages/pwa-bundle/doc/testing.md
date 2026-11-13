# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
node --import ./tests/js/register.mjs --test tests/js
```

| Suite | What it covers |
|---|---|
| `tests/Unit` | The manifest and its rules, the reader, the JSON of the manifest, the script of the worker and its version, the controller and the Twig functions, the configuration and its pass. |
| `tests/Functional` | A small kernel with FrameworkBundle, AssetMapper and TwigBundle: the manifest with an icon versioned by AssetMapper, `/sw.js` and the worker it imports, served by AssetMapper, the headers of a worker served below its scope, the offline page, the layout. |
| `tests/js` | Under Node's test runner, with `@hotwired/stimulus` resolved to a stub by `register.mjs`: the worker in a context of its own, with a cache storage in memory and a network the test answers — install, activate, every strategy, offline, what is never kept —, and both controllers with a browser made of plain objects. |

Nothing needs a network or a browser. In the monorepo, Infection holds the PHP at 100 % of covered mutants.

## Fuzzing

`tests/Fuzz/manifest.php` reads a manifest from JSON nobody wrote: it reads with its start within its scope and its
paths local, or is refused with `InvalidManifest`. From the monorepo root:

```sh
composer fuzz -- manifest --budget=60
```

## Trying it in a browser

Browsers register a worker on HTTPS or `localhost` only. In the developer tools, the Application panel shows the
manifest, the worker, its caches, and an Offline switch to try the offline page.
