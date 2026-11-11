# Testing

| Area | What is covered |
|---|---|
| `MenuItem`, `Menu` | Building, refusals, children, `map()` children first, an item whose children are unchanged kept, renamed children checked, the breadcrumb |
| `Menus` | Current items by route, parameters and path, ancestors, decorators in order and by menu, names, refusals |
| `MenuExtension`, `PhilMenuBundle` | The request read, the services and the autoconfiguration |

A functional suite boots a kernel with FrameworkBundle, TwigBundle and the bundle, a builder and a decorator found
by autoconfiguration: `MenuBundleTest` renders the three templates in French on a page of the menu and on one
outside it. Installed on its own, where FrameworkBundle is a development requirement, the suite is skipped.

```sh
vendor/bin/phpunit packages/menu-bundle/tests
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark` measure the hot paths at several sizes. From the root of the monorepo:

```sh
composer bench -- packages/menu-bundle/tests/Benchmark
```
