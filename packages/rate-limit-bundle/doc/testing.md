# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel: the domain, the rules, the enforcer and the headers on
limiters in memory, the adapter on the real `symfony/rate-limiter`, the keys with real tokens and phil/ip-anonymizer, the
form extension on the real form component, the responder on Twig and the translator, and the container's configuration
and compiler pass on a `ContainerBuilder`. The functional suite, under `tests/Functional`, boots an application with
FrameworkBundle and TwigBundle: a controller limited by its attributes answered with its page in French, an API limited
by its route default answered a problem in JSON, a form limited by its name, each refusal dispatched.

In the monorepo, the tests run on PHP 8.5 and 8.6, and Infection holds the package at 100 % of covered mutants.

## The benchmark

`tests/Benchmark/RateLimitListenerBench.php` measures what the listener adds to a request — no rule, one rule and three,
each over a sliding window on a memory storage, and the headers on the response —, the storage's own I/O aside.

```sh
composer bench -- --filter=RateLimitListenerBench
```
