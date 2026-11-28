# Testing

## The package's tests

The tests are PHPUnit tests in `tests/Unit`, run from the root of the monorepo with `composer test`, or on their own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

They run without a kernel, a database or a network. `tests/Support/Recorder` is a condition type that records which
leaves were evaluated, so that the tests see what a short-circuit skipped. In the monorepo, Infection holds the package
at 100 % of covered mutants.

## The fuzz target

`tests/Fuzz/rule-reader.php` feeds the reader rules nobody wrote, from the seeds of `tests/Fuzz/corpus/rule-reader`:
whatever it reads, it gives a rule or refuses with `InvalidRule` or `LimitExceeded`; a rule it gives writes a canonical
form that reads back to itself, and evaluates without throwing.

```sh
composer fuzz -- rules
```

## The benchmark

`tests/Benchmark/RuleBench` reads a stored rule of some seventy conditions and evaluates it for one subject, the path a
segment or a price book takes once per person or per line:

```sh
composer bench -- packages/rules/tests/Benchmark
```

## Testing an application's condition type

A condition type is tested through a rule, so that its schema is checked as a stored rule would be:

```php
use Phil\Rules\Condition\Leaf;
use Phil\Rules\Fact\Facts;
use Phil\Rules\Rule;
use Phil\Rules\Type\ConditionTypes;

$rule = new Rule(new Leaf('cart.total', ['at_least_cents' => 5000, 'currency' => 'CAD']), new ConditionTypes(new CartTotal()));
$result = $rule->evaluate(Facts::of(['cart' => $cart]));

self::assertFalse($result->passed);
self::assertSame('cart.too-low', $result->trace->reason);
```
