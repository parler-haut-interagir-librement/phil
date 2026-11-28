# Reference

Every public type of the package, with its role. Names are relative to `Phil\Rules`.

## Rules

| Type | Kind | Role |
|---|---|---|
| `Rule` | `final readonly class` | A condition checked against `ConditionTypes` and `Limits`: `evaluate(FactSource $facts): Result`, `typeNames()`, `condition`, `types`, `limits`; `VERSION`, `ROOT`. |
| `Limits` | `final readonly class` | `maxBytes`, `maxDepth`, `maxNodes`, `maxEvaluations`. |
| `Condition\Condition` | `interface` | A node of a rule. |
| `Condition\All` | `final readonly class` | Every one of its `conditions` holds. |
| `Condition\Any` | `final readonly class` | One of its `conditions` holds. |
| `Condition\Not` | `final readonly class` | Its `condition` fails. |
| `Condition\Leaf` | `final readonly class` | A condition of a registered `type`, with its `operands`. |

## Facts

| Type | Kind | Role |
|---|---|---|
| `Fact\FactSource` | `interface` | The scope of an evaluation: `has(string $name)`, `get(string $name)`. |
| `Fact\Facts` | `final class` | Facts as values or closures computed once when first read: `of(array $facts)`. |

## Condition types

| Type | Kind | Role |
|---|---|---|
| `Type\ConditionType` | `interface` | `name()`, `operands(): OperandSchema`, `evaluate(Operands $operands, FactSource $facts): Verdict`. |
| `Type\ConditionTypes` | `final readonly class` | The registry: `builtIn()`, `with()`, `has()`, `get()`, `names()`, `describe()`. |
| `Type\BuiltIn\FactEquals`, `FactIn`, `FactContains`, `FactCompare`, `FactPresent`, `DateCompare` | `final readonly class` | The built-in types `fact.equals`, `fact.in`, `fact.contains`, `fact.compare`, `fact.present`, `date.compare`. |
| `Operand\Operand` | `final readonly class` | One operand: `text()`, `identifier()`, `integer()`, `number()`, `boolean()`, `choice()`, `date()`, `scalar()`, `identifierList()`, `scalarList()`; `accept()`, `describe()`. |
| `Operand\OperandKind` | `enum` | `Text`, `Identifier`, `Integer`, `Number`, `Boolean`, `Choice`, `Date`, `Scalar`, `IdentifierList`, `ScalarList`. |
| `Operand\OperandSchema` | `final readonly class` | The operands of a type: `read()`, `describe()`. |
| `Operand\Operands` | `final readonly class` | Checked operands: `has()`, `string()`, `int()`, `float()`, `bool()`, `scalar()`, `strings()`, `scalars()`, `toArray()`. |

## Evaluation

| Type | Kind | Role |
|---|---|---|
| `Evaluation\Result` | `final readonly class` | `passed`, `trace`, `evaluations`; `explanation()`. |
| `Evaluation\Step` | `final readonly class` | One condition of the trace: `path`, `kind`, `outcome`, `type`, `reason`, `details`, `children`; `decisive()`, `toArray()`. |
| `Evaluation\Verdict` | `final readonly class` | What a leaf answers: `passed`, `reason`, `details`; `pass()`, `fail()`. |
| `Evaluation\Outcome` | `enum` | `Passed`, `Failed`, `Skipped`. |
| `Evaluation\NodeKind` | `enum` | `All`, `Any`, `Not`, `Leaf`. |

## JSON

| Type | Kind | Role |
|---|---|---|
| `Json\RuleReader` | `final readonly class` | `fromJson(string $json): Rule`, `fromArray(array $rule): Rule`. |
| `Json\RuleWriter` | `final readonly class` | `toJson(Rule $rule)`, `toArray(Rule $rule)`, `fingerprint(Rule $rule)`. |

## Exceptions

| Type | Kind | Role |
|---|---|---|
| `Exception\RulesException` | `interface` | Every exception of the package. |
| `Exception\InvalidRule` | `final class`, `InvalidArgumentException` | A rule refused when loaded, with the path of the faulty node. |
| `Exception\LimitExceeded` | `final class`, `RuntimeException` | A bound of `Limits` passed. |
| `Exception\InvalidConditionType` | `final class`, `LogicException` | A type or an operand that cannot be declared. |
| `Exception\InvalidLimit` | `final class`, `InvalidArgumentException` | A bound out of its range. |
| `Exception\MissingFact` | `final class`, `OutOfBoundsException` | A fact read that the scope does not hold. |
| `Exception\MissingOperand` | `final class`, `LogicException` | An operand read that the schema does not give. |
