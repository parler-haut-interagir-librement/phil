# Testing

## The library's tests

The suite `tests/Unit` tests each matcher on its patterns and their guesses, the cover search with hand-made matches,
the feedback of each pattern, the estimator end to end, the breach source against a mock HTTP client, the generators
with seeded randomizers, and both constraints with Symfony's `ConstraintValidatorTestCase`. From the monorepo root:

```sh
vendor/bin/phpunit packages/passwords/tests
```

The library is held to 100 % covered mutation score by Infection.

## Fuzzing

The fuzz target `strength-estimator` gives the estimator inputs nobody wrote, valid UTF-8 or not; `range-response` gives
them to the reader of the Pwned Passwords ranges, which returns occurrences or throws `BreachCheckUnavailable`:

```sh
composer fuzz -- strength-estimator range-response
```

## The benchmark

`tests/Benchmark/StrengthEstimatorBench.php` measures the estimator on ordinary passwords, a passphrase and the most
hostile inputs of 128 characters — a repeated letter, a repeated common password, digits read as dates everywhere —,
and the building of the estimator:

```sh
composer bench -- packages/passwords/tests/Benchmark
```

An ordinary password takes a fraction of a millisecond; the most hostile input stays within a few tens of
milliseconds.

## Testing code that uses the library

The estimator is deterministic once its reference year is given: build it with `referenceYear` so that dates and
years cost the same whatever the year the test runs. Give the breach checker an `InMemoryBreachSource`, and
`PwnedPasswordsSource` a `MockHttpClient`:

```php
$checker = new BreachChecker(InMemoryBreachSource::fromPasswords(['hunter2' => 17]));

$source = new PwnedPasswordsSource(new MockHttpClient(new MockResponse('', ['http_code' => 503])));
```

Give the generators a seeded randomizer, `new Randomizer(new Mt19937(42))`, for a repeatable output.
