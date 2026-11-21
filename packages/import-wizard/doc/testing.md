# Testing

## The package's tests

The tests are PHPUnit tests in `tests/Unit`, run from the root of the monorepo with `composer test`, or on their own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

They run without a kernel, a database or a network. `tests/Support/MemoryTarget` is a target that keeps its records in
memory, refuses the keys it is told to, and remembers every lookup and batch; `tests/Support/SampleRule` is a rule of
an application, read back by its name. In the monorepo, Infection holds the package at 100 % of covered mutants.

## Testing an application's target

A target is tested with a plan made from a small CSV text and the profile it writes:

```php
use Phil\ImportWizard\Csv\CsvRowSource;
use Phil\ImportWizard\Import\Importer;
use Phil\ImportWizard\Lms\LmsProfiles;
use Phil\ImportWizard\Plan\ImportPlanner;

$plan = new ImportPlanner()->plan(CsvRowSource::fromString("code,name,parent_code\nsci,Sciences,\n"), LmsProfiles::organisationalUnits(), $target);
$result = new Importer()->commit($plan, $target);
```

## The fuzz target

`tests/Fuzz/csv.php` reads text nobody wrote with `CsvRowSource` within small limits, then plans it with the three
formats of `LmsProfiles`: each step returns or throws an `ImportWizardException`, every row keeps a line within the
text, and every row of the file is planned or refused, never both. Its seeds are in `tests/Fuzz/corpus/csv`.

```sh
composer fuzz -- csv
```

## The benchmark

`tests/Benchmark/PlanBench.php` measures the hot paths on 1,000 people and on a chain of 1,000 units written children
first: the file read, the dry run, and the plan written through a target in memory.

```sh
composer bench
```
