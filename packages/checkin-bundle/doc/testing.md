# Testing

## The bundle's tests

`tests/Unit` tests the configuration and the services it registers, the CSRF and catalog passes, the catalogs asked
together, the Doctrine ledger on SQLite in memory — its table, keys, attendance and refusals — and its schema listener,
the trust of a request, the desk configuration, the admission rules and the desks they scope, the event of a scan
recorded, and every endpoint with its errors. `tests/Functional` boots a small application with FrameworkBundle,
AssetMapper, DoctrineBundle on SQLite, the routes, a rule and tickets plugged in by autoconfiguration, and checks a
ticket at two doors, a forbidden checkpoint, a forged CSRF token, a ticket the rule refuses, a manifest, a journal
synchronised twice, the scans heard by a listener, and the controller and stylesheet served by AssetMapper. Its kernel
declares the event dispatcher itself, so that it boots the same from the monorepo and from the bundle installed alone,
where FrameworkBundle is a development dependency.

From the monorepo root:

```sh
vendor/bin/phpunit packages/checkin-bundle/tests
```

The controller and its libraries — the controller on fake DOM nodes, online and offline, with a fake IndexedDB; the
stores, their fallbacks and the move from `localStorage` to IndexedDB; the offline policy, the manifest and its
fingerprints, the journal, the CSRF token and the labels — are tested with Node.js, without installing anything,
Stimulus resolved to a stub:

```sh
cd packages/checkin-bundle && node --import ./tests/js/register.mjs --test 'tests/js/*.test.mjs'
```

`tests/Browser` drives a real Chrome with `symfony/panther` and the system's WebDriver for Chrome, on the page of the
functional application served by PHP's built-in server: a scan online, the network cut through Chrome's DevTools,
scans decided from the manifest kept in IndexedDB, and the journal synchronised once the network is back. It runs
apart, from the monorepo root, after `npm ci --ignore-scripts` in `tests/Browser`:

```sh
composer test:browser
```

The bundle is held to 100 % covered mutation score by Infection.

## Testing an application's check-in

Set `store: memory` in the `test` environment, give the test client a user your voter grants, send the controller's
header and a stateless CSRF token — the cookie `<name>_<token>=<name>` and the header `<name>: <token>`, where the name
is `DeskConfig::for()['csrfCookie']` —, and post to the scan endpoint. `tests/Functional/CheckinBundleTest.php` does
exactly that.
