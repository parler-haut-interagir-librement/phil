# phil/form-logic

**Typed conditional logic for forms, evaluated alike on the server and in the browser, the server judging.**

phil/form-logic decides which questions of a form are asked and computes values from the answers: a field shown only
to those who bring a guest, a section for minors, a total of seats, a price band. The logic is a structure of
immutable PHP objects — comparisons, `and`, `or`, `not`, membership, intervals, presence and simple arithmetic —,
checked against the types of the fields when the form is defined, so that a condition comparing a choice with a value
it never takes is refused then rather than found false later.

It is stored and exchanged as a strict subset of JsonLogic, which other tools read: `{"in": [{"var": "team"},
["Blue", "Green"]]}`. Any operator outside the subset is refused when the rule is read, and a rule's size, depth and
nodes are bounded, so that evaluating it costs time in proportion to its size.

The same logic runs twice. In the browser, a Stimulus controller shipped with the package shows and hides the fields
and shows the computed values as the form is filled. On the server, the `Judge` evaluates the answer again whatever the
browser sent: it drops the values of hidden fields, computes the variables and gives the verdict the application
validates and stores, the computed variables with the answer. A shared suite of cases runs through both evaluators.

It is a plain PHP library, with an adapter for the conditions of `phil/forms-bundle` and a controller served by
AssetMapper, with no build step.

## Status

| Part | State |
|---|---|
| The expressions: comparisons, intervals, `and`, `or`, `!`, `!!`, `if`, `in`, `missing`, `missing_some`, arithmetic | Implemented and tested |
| The JsonLogic reader, strict and bounded, and its writer | Implemented, tested and fuzzed |
| The evaluator, with the conversions of JsonLogic | Implemented and tested against the shared cases and the test cases JsonLogic publishes |
| The type checker of the variables a rule reads | Implemented and tested |
| A form's logic: field conditions and computed variables in order, the `Judge` and its `Verdict` | Implemented and tested |
| The `logic` Stimulus controller and its evaluator | Implemented; tested under Node against the same cases, and in Chrome in an application with AssetMapper |
| `JsonLogicConditions`, the `ConditionEvaluator` of `phil/forms-bundle` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the library, the Stimulus controller, `phil/forms-bundle`. |
| [The rules](doc/rules.md) | The JsonLogic subset: each operator, the values, the conversions, the limits, what is refused. |
| [Form logic](doc/form-logic.md) | Expressions in PHP, types, field conditions, computed variables, the judge and its verdict. |
| [JavaScript](doc/javascript.md) | The controller, the markup it reads, the answer it collects, its events, its evaluator. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | The PHP and Node tests, the shared and the published cases, the browser suite, the fuzz target, the benchmarks. |
| [Reference](doc/reference.md) | Every public type and module of the package, with its role. |

## Quick start

```sh
composer require phil/form-logic
```

```php
use Phil\FormLogic\Logic\FormLogic;
use Phil\FormLogic\Logic\Judge;

$logic = FormLogic::fromArray([
    ['field' => 'guest_name', 'when' => ['==' => [['var' => 'bringing_guest'], true]]],
    ['compute' => 'seats', 'type' => 'number', 'value' => ['+' => [1, ['if' => [['var' => 'bringing_guest'], 1, 0]]]]],
]);

$verdict = new Judge()->judge($logic, $request->request->all('entry'));
$verdict->answer;   // the answer, without the values of hidden fields
$verdict->computed; // ['seats' => 2], to store with the answer
```

```twig
<form data-controller="phil--form-logic--logic"
      data-phil--form-logic--logic-steps-value="{{ logic.toArray()|json_encode }}">
    <div data-form-logic-field="guest_name">…</div>
    <output data-form-logic-output="seats"></output>
</form>
```

## Requirements

PHP 8.5 or later with `ext-mbstring`. For the controller: Stimulus 3 in the browser, served by AssetMapper and
StimulusBundle. For the adapter: `phil/forms-bundle`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
