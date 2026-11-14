# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

## Configured by the application

| Type | What it sets |
|---|---|
| `Limits` | How large a rule `JsonLogicReader` reads: bytes, depth, nodes, list items, text length |
| `Schema` and `VariableDefinition` | The variables a rule may read, with their types and choices |
| `ComputedVariable::$type` | The type a computed variable gives to the steps after it |

## Ports implemented by the package

| Port | Implementation |
|---|---|
| `Phil\FormsBundle\Domain\Contract\ConditionEvaluator` of `phil/forms-bundle` | `JsonLogicConditions`, by aliasing the port to it |

## Relied on

- The language is closed: the evaluators know the nodes of `Phil\FormLogic\Expression` and nothing else. An expression
  of another class throws `InvalidLogic`; an operator is added by the library, in PHP and in JavaScript together, with
  its shared cases.
- `FormLogic::toArray()` is the format the controller reads and the format to store; it changes only with a minor
  version, and `FormLogic::fromArray()` reads it back.
- `Evaluator`, `JsonLogicReader`, `TypeChecker` and `Judge` keep no state between calls: one instance serves every
  request of a worker.
