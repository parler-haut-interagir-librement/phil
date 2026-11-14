# Reference

Every public type and module of the package, with its role.

## PHP

| Type | Role |
|---|---|
| `Contract\Expression` | A node of an expression: its rule as JsonLogic, its children |
| `Expression\Literal` | A constant, its number kept as JavaScript holds it |
| `Expression\ListOf` | A list of expressions |
| `Expression\Variable` | `var`: a value of the answer by name, with an optional default |
| `Expression\Missing` | `missing` and `missing_some` |
| `Expression\Comparison` | Two values compared |
| `Expression\ComparisonOperator` | `==`, `!=`, `===`, `!==`, `<`, `<=`, `>`, `>=` |
| `Expression\Between` | A value within two bounds, included or not |
| `Expression\Not` | `!` and `!!` |
| `Expression\AllOf` | `and` |
| `Expression\AnyOf` | `or` |
| `Expression\Conditional` | `if` |
| `Expression\In` | Membership in a list or a text |
| `Expression\Arithmetic` | Simple arithmetic |
| `Expression\ArithmeticOperator` | `+`, `-`, `*`, `/`, `%`, `min`, `max`, and the operands each takes |
| `JsonLogic\JsonLogicReader` | Reads a rule of the subset, as JSON or decoded, within its limits |
| `JsonLogic\JsonLogicWriter` | Writes an expression as canonical JSON |
| `JsonLogic\Limits` | The limits of the reader |
| `Evaluation\Evaluator` | The value and the truth of an expression for an answer |
| `Typing\VariableType` | What a variable holds |
| `Typing\VariableDefinition` | A variable a rule may read: name, type, choices |
| `Typing\Schema` | The variables a rule may read, by name |
| `Typing\TypeChecker` | Checks an expression against a schema |
| `Typing\VariableNames` | The variables an expression reads |
| `Logic\FieldCondition` | The condition under which a field is shown |
| `Logic\ComputedVariable` | A value computed from the answer, and its type |
| `Logic\FormLogic` | A form's steps in order, checked, written and read back |
| `Logic\Judge` | Runs the steps on a submitted answer |
| `Logic\Verdict` | The answer without hidden fields, the hidden fields, the computed variables |
| `FormsBundle\JsonLogicConditions` | The `ConditionEvaluator` of `phil/forms-bundle` |
| `Exception\FormLogicException` | Everything the library throws |
| `Exception\InvalidLogic` | A rule, an expression or a form's logic refused |

`Evaluation\Values` holds the conversions the evaluator shares with the browser; it is internal.

## JavaScript

| Module | Role |
|---|---|
| `assets/src/logic_controller.js` | The `logic` controller: fields shown and hidden, computed values shown |
| `assets/src/logic.js` | The evaluator of the browser, and the judge of the steps |
