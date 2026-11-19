# Testing

## The package's tests

`tests/Unit` covers the expressions and their checks, the reader — every operator, the decoded and JSON forms, each
limit at its bound, what is refused — and the writer, the conversions of `Values` — numbers written as JavaScript
writes them, loose and strict equality, the order of texts by UTF-16 code units —, the evaluator, the type checker and
the schema, a form's logic, its order and its check, the judge and its verdict, `JsonLogicConditions` on the fields
of `phil/forms-bundle`, and the steps that bundle's page writes, read as a logic whose judge hides the fields the bundle
does not ask, under either evaluator.

`tests/js` runs the evaluator, the controller and the published cases under Node's test runner, Stimulus resolved to
a stub, on a small stand-in for the DOM: the answer collected from each kind of control, fields hidden in cascade and
shown again with their values, computed outputs, unreadable rules and the events.

From the monorepo root, then from the package:

```sh
vendor/bin/phpunit packages/form-logic/tests
node --import ./tests/js/register.mjs --test tests/js     # from packages/form-logic
```

In the monorepo, Infection holds the package at 100 % of covered mutants.

## The shared cases

`tests/Fixtures/cases.json` holds the cases both evaluators pass: under `evaluate`, a rule, an answer and the value
expected, grouped by operator after a heading; under `refuse`, a rule and why the reader refuses it. PHPUnit evaluates
each case, then reads the rule again from its canonical form and evaluates it once more; Node checks and evaluates
each case with `assets/src/logic.js`. A case added to the file runs in both.

The cases were written for this package, after the published description of each operator of JsonLogic, and add the
rules of the package: text read as a number in decimal notation only, non-finite results as `null`, numbers written as
text, texts ordered by UTF-16 code units.

## The cases JsonLogic publishes

`tests/Fixtures/json-logic/tests.json` is the file of test cases of JsonLogic, kept unchanged with its MIT notice in
`LICENSE` beside it: from https://github.com/jwadhams/json-logic, branch `gh-pages`, commit `2964b8da` of 2024-07-09,
SHA-256 `a202b65edda0d7ab687c758b8f10cfe9ba75561cd7166e5650710f88f26303a4`. `OfficialCasesTest` and
`tests/js/official.test.mjs` run each case:

- a rule of the subset gives the value JsonLogic expects — 198 cases;
- a rule the reader refuses is listed under `refused` in `differences.json`, grouped by reason: operators outside the
  subset (`cat`, `substr`, `merge`, `map`, `filter`, `reduce`, `all`, `none`, `some`, `?:`), a variable named by a
  number or by no name, a name computed by an operation, an `if` without a condition and its value, a `missing` that
  names nothing — 71 rules, in 80 cases. A refusal not listed, or a rule listed but read, fails the suite.

No published case reaches the differences on values; `differences.json` lists them under `values`, each with a rule,
the value the library gives and its reason, and both suites check that value: a text read as a number in decimal
notation only, two lists never equal, a non-finite result as `null`, a variable at `null` that takes its default, and
`!=` true for a field without a value, as the basic conditions of `phil/forms-bundle` give it too.

The archive Composer installs holds `tests/`, the file and its notice with it, so the suites run from an installed
copy of the package.

## The browser suite

`tests/Browser/LogicBrowserTest.php` drives the controller in Chrome with Panther, in an application installed as
[Installation](installation.md#the-stimulus-controller) says: `BrowserKernel` maps `assets/src` as `@phil/form-logic`
in AssetMapper, enables the controller in `assets/controllers.json` for StimulusBundle, and serves the form through
PHP's built-in server. It checks the import map, fields hidden and shown in cascade with their controls disabled and
their values read back, a control the page disabled left so, a computed output, a hidden field the browser does not
send and the server judges hidden too, and a rule outside the subset that leaves every field shown.

It needs the chromedriver of the system and Stimulus, from `npm ci --ignore-scripts` in `tests/Browser`, and is skipped
without them. From the monorepo root, with the other browser suites:

```sh
composer test:browser
```

## The fuzz target

`tests/Fuzz/json-logic-reader.php` fuzzes the reader with the seeds of `tests/Fuzz/corpus/json-logic-reader`: whatever
it reads, it gives an expression or throws `InvalidLogic`; an expression writes a canonical rule that reads back to
itself, and evaluates without throwing.

```sh
composer fuzz -- json-logic-reader
```

## The benchmarks

`tests/Benchmark/EvaluatorBench.php` reads and evaluates rules of 250, 500 and 1,000 nodes — comparisons joined by
`and`, memberships, nested operations — and judges a form of 100 steps. The time grows with the nodes, not with their
square.

```sh
composer bench -- --filter=EvaluatorBench
```
