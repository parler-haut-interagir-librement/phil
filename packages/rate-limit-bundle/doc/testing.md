# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel: the domain, the rules, the enforcer and the headers on
limiters in memory, the adapters on the real `symfony/rate-limiter`, the consumption of Symfony's attribute, the keys with real tokens and phil/ip-anonymizer, the
form extension on the real form component, the responder on Twig and the translator, and the container's configuration
and compiler pass on a `ContainerBuilder`. The functional suite, under `tests/Functional`, boots an application with
FrameworkBundle and TwigBundle: a controller limited by Symfony's attributes answered with its page in French, an
attribute without key counted by the limiter's strategy, an explicit key kept hashed and the consumed limit handed to the
action, the route default counted before the attributes, an API limited by its route default answered a problem in
JSON, a limiter of the framework's configuration answered with the page, a form limited by its name, each refusal
dispatched as the bundle's event and Symfony's.

In the monorepo, the tests run on PHP 8.5 and 8.6, and Infection holds the package at 100 % of covered mutants.

## The benchmark

`tests/Benchmark/RateLimitListenerBench.php` measures what the bundle adds to a request — no rule, one rule and three in
a route default, and one consumption of Symfony's attribute, each over a sliding window on a memory storage, and the
headers on the response —, the storage's own I/O aside.

```sh
composer bench -- --filter=RateLimitListenerBench
```
