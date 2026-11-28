<!-- phil-dev source-sha256=4e8154453d01fe0091b130f9e3d5730ab5ba98672cfc8e81f8807a01ea8e4709 -->
# Référence

Chaque type public du paquet, avec son rôle. Les noms sont relatifs à `Phil\Rules`.

## Règles

| Type | Genre | Rôle |
|---|---|---|
| `Rule` | `final readonly class` | Une condition vérifiée contre `ConditionTypes` et `Limits` : `evaluate(FactSource $facts): Result`, `typeNames()`, `condition`, `types`, `limits` ; `VERSION`, `ROOT`. |
| `Limits` | `final readonly class` | `maxBytes`, `maxDepth`, `maxNodes`, `maxEvaluations`. |
| `Condition\Condition` | `interface` | Un nœud d'une règle. |
| `Condition\All` | `final readonly class` | Chacune de ses `conditions` tient. |
| `Condition\Any` | `final readonly class` | L'une de ses `conditions` tient. |
| `Condition\Not` | `final readonly class` | Sa `condition` échoue. |
| `Condition\Leaf` | `final readonly class` | Une condition d'un `type` enregistré, avec ses `operands`. |

## Faits

| Type | Genre | Rôle |
|---|---|---|
| `Fact\FactSource` | `interface` | La portée d'une évaluation : `has(string $name)`, `get(string $name)`. |
| `Fact\Facts` | `final class` | Des faits sous forme de valeurs ou de fermetures calculées une fois à la première lecture : `of(array $facts)`. |

## Types de condition

| Type | Genre | Rôle |
|---|---|---|
| `Type\ConditionType` | `interface` | `name()`, `operands(): OperandSchema`, `evaluate(Operands $operands, FactSource $facts): Verdict`. |
| `Type\ConditionTypes` | `final readonly class` | Le registre : `builtIn()`, `with()`, `has()`, `get()`, `names()`, `describe()`. |
| `Type\BuiltIn\FactEquals`, `FactIn`, `FactContains`, `FactCompare`, `FactPresent`, `DateCompare` | `final readonly class` | Les types intégrés `fact.equals`, `fact.in`, `fact.contains`, `fact.compare`, `fact.present`, `date.compare`. |
| `Operand\Operand` | `final readonly class` | Un opérande : `text()`, `identifier()`, `integer()`, `number()`, `boolean()`, `choice()`, `date()`, `scalar()`, `identifierList()`, `scalarList()` ; `accept()`, `describe()`. |
| `Operand\OperandKind` | `enum` | `Text`, `Identifier`, `Integer`, `Number`, `Boolean`, `Choice`, `Date`, `Scalar`, `IdentifierList`, `ScalarList`. |
| `Operand\OperandSchema` | `final readonly class` | Les opérandes d'un type : `read()`, `describe()`. |
| `Operand\Operands` | `final readonly class` | Des opérandes vérifiés : `has()`, `string()`, `int()`, `float()`, `bool()`, `scalar()`, `strings()`, `scalars()`, `toArray()`. |

## Évaluation

| Type | Genre | Rôle |
|---|---|---|
| `Evaluation\Result` | `final readonly class` | `passed`, `trace`, `evaluations` ; `explanation()`. |
| `Evaluation\Step` | `final readonly class` | Une condition de la trace : `path`, `kind`, `outcome`, `type`, `reason`, `details`, `children` ; `decisive()`, `toArray()`. |
| `Evaluation\Verdict` | `final readonly class` | Ce qu'une feuille répond : `passed`, `reason`, `details` ; `pass()`, `fail()`. |
| `Evaluation\Outcome` | `enum` | `Passed`, `Failed`, `Skipped`. |
| `Evaluation\NodeKind` | `enum` | `All`, `Any`, `Not`, `Leaf`. |

## JSON

| Type | Genre | Rôle |
|---|---|---|
| `Json\RuleReader` | `final readonly class` | `fromJson(string $json): Rule`, `fromArray(array $rule): Rule`. |
| `Json\RuleWriter` | `final readonly class` | `toJson(Rule $rule)`, `toArray(Rule $rule)`, `fingerprint(Rule $rule)`. |

## Exceptions

| Type | Genre | Rôle |
|---|---|---|
| `Exception\RulesException` | `interface` | Toute exception du paquet. |
| `Exception\InvalidRule` | `final class`, `InvalidArgumentException` | Une règle refusée au chargement, avec le chemin du nœud fautif. |
| `Exception\LimitExceeded` | `final class`, `RuntimeException` | Une borne de `Limits` dépassée. |
| `Exception\InvalidConditionType` | `final class`, `LogicException` | Un type ou un opérande qui ne peut pas être déclaré. |
| `Exception\InvalidLimit` | `final class`, `InvalidArgumentException` | Une borne hors de sa plage. |
| `Exception\MissingFact` | `final class`, `OutOfBoundsException` | Un fait lu que la portée ne contient pas. |
| `Exception\MissingOperand` | `final class`, `LogicException` | Un opérande lu que le schéma ne donne pas. |
