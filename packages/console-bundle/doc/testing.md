# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel: the attributes and the guards, the confirmation on real
console inputs, the reader on every shape of command — invokable services, method-level commands, classic commands,
lazy and traced ones —, the stamping on memory streams, the listener in a console application with an event dispatcher
and a mock clock, the lint command, the Monolog switch on real loggers, and the container's configuration and compiler
pass. The functional suite, under `tests/Functional`, boots an application with FrameworkBundle and MonologBundle: a
method-level command forbidden in the test environment, another confirmed, timestamped, printing its debug logs and its
summary, and the lint command in JSON.

In the monorepo, the tests run on PHP 8.5 and 8.6, and Infection holds the package at 100 % of covered mutants.

## Testing a guarded command

In an application's tests, the kernel's environment is `test`: a command forbidden in `prod` runs there. A command
that asks for a confirmation in `test` needs its answer, or `--force-environment`:

```php
$tester = new ApplicationTester($application);
$tester->setInputs(['test']);
$tester->run(['command' => 'app:customers:anonymize'], ['interactive' => true]);

$tester->run(['command' => 'app:customers:anonymize', '--force-environment' => true], ['interactive' => false]);
```

## The benchmark

`tests/Benchmark/ConsoleListenerBench.php` measures what the listener adds to a command, without and with timestamps,
and the stamping of a hundred lines written at once.

```sh
composer bench -- --filter=ConsoleListenerBench
```
