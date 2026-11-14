# Form logic

## Expressions

A rule read by `JsonLogicReader` is a tree of immutable objects under `Phil\FormLogic\Expression`, each implementing
`Phil\FormLogic\Contract\Expression`. An application may also build them directly:

```php
use Phil\FormLogic\Expression\{AllOf, Between, Comparison, ComparisonOperator, Literal, Not, Missing, Variable};

$minorWithGuardian = new AllOf([
    new Between(new Literal(0), new Variable('age'), new Literal(17), inclusive: true),
    new Not(new Missing(['guardian_name'])),
]);
```

| Node | Rule |
|---|---|
| `Literal` | A constant |
| `ListOf` | A list of expressions |
| `Variable` | `var`, with an optional default |
| `Missing` | `missing`, or `missing_some` with `$atLeast` |
| `Comparison` | `==`, `!=`, `===`, `!==`, `<`, `<=`, `>`, `>=`, by `ComparisonOperator` |
| `Between` | `<` or `<=` of three operands |
| `Not` | `!`, or `!!` with `$double` |
| `AllOf`, `AnyOf` | `and`, `or` |
| `Conditional` | `if` |
| `In` | `in` |
| `Arithmetic` | `+`, `-`, `*`, `/`, `%`, `min`, `max`, by `ArithmeticOperator` |

Each node checks itself when built: a name that is not a variable name, an operation without operands, a non-finite
number throws `InvalidLogic`. `jsonLogic()` gives the rule, ready for `json_encode()`; `JsonLogicWriter` writes it as
the canonical JSON the reader reads back to an equal tree.

`Evaluator::evaluate()` gives the value of an expression for an answer, and `holds()` its truth. The answer is a map
of field names to values, as [The rules](rules.md#the-values) describe.

## Types

`TypeChecker` checks an expression against a `Schema` of `VariableDefinition`s — a name, a `VariableType`, and the
choices of a choice —, the way a form is defined:

| Type | Compared with |
|---|---|
| `Text` | texts; looked into with `in` |
| `Number` | numbers; ordered, placed in intervals, computed with |
| `Boolean` | `true` and `false` |
| `Date` | valid days, `YYYY-MM-DD`; ordered and placed in intervals |
| `Choice` | its choices only |
| `Choices` | looked into with `in`, for one of its choices |

Every variable the expression reads must be in the schema; a variable compared with a constant must be compared with a
value of its type, `null` telling an unanswered variable; two variables compared must be of the same type; only numbers
and days are ordered. The checker throws `InvalidLogic` naming the first rule broken.

`VariableNames::of()` lists the variables an expression reads, each once.

## A form's logic

`FormLogic` holds the steps of a form, in the order they are evaluated:

- a `FieldCondition` — a field and the expression under which it is shown and its answer kept;
- a `ComputedVariable` — a name, an expression and the type it gives (a number, a text, a boolean or a day).

Each step reads the answer, the fields hidden before it read as unanswered, and the variables computed before it. A
step never reads a step after it, nor itself, and two steps never share a name: the order says everything, and
`FormLogic` refuses logic that breaks it. A form has at most 500 steps.

`check(Schema $fields)` checks every step against the fields' types, each computed variable then readable by the steps
after it with its own type. `toArray()` writes the steps as data, the rules as JsonLogic, for storage and for the
Stimulus controller; `FormLogic::fromArray()` reads them back through a `JsonLogicReader`:

```json
[
    {"field": "guest_name", "when": {"==": [{"var": "bringing_guest"}, true]}},
    {"compute": "seats", "type": "number", "value": {"+": [1, {"if": [{"var": "bringing_guest"}, 1, 0]}]}}
]
```

## The judge

The browser only shows and hides; the server decides. `Judge::judge(FormLogic $logic, array $answer)` runs the steps
in order on the submitted answer:

- a field whose condition does not hold is hidden: its value is dropped from the answer, and the steps after it read it
  as unanswered, so a field that depends on a hidden one is hidden too;
- a computed variable is evaluated and readable by the steps after it; a submitted value of the same name is dropped,
  since the server computes it, never the browser.

The `Verdict` holds the answer without the hidden fields, the hidden fields in order, and the computed variables, to
validate the answer against and to store with it. `isShown()` tells whether a field was asked.

## With phil/forms-bundle

`Phil\FormLogic\FormsBundle\JsonLogicConditions` implements the bundle's `ConditionEvaluator`. A condition is one
operation of the subset; `check()` types each field by what it takes — a choice by its choices, a checkbox as a boolean,
a date or a birth date as a day, any other field as a text — and refuses, as an `InvalidForm`, a condition the reader
or the type checker refuses. `holds()` evaluates the condition on the entry's values, a field not asked having no
value.

The bundle keeps its own order of fields and its own storage: computed variables, which its port does not carry, are
the application's, through `FormLogic` and the `Judge`.

With this library installed, the bundle's page applies its conditions in the browser too, whichever evaluator reads
them on the server: its `LogicSteps` writes them as the steps of the controller, and its `_form.html.twig` puts the
controller on the form and `data-form-logic-field` on each conditional row. A field that another condition reads gets
a computed variable, `_shown_0`, `_shown_1`… — the bundle's field names never start with `_` —, which the conditions
that read it require, so the browser keeps a field left empty and a field not asked apart, as the bundle does; the
controller is served as [Installation](installation.md#the-stimulus-controller) says.
