<!-- phil-dev source-sha256=68521e3d160bb9fd162588068839e9486ac8f6a8dfa73e2813d8ea28aac37004 -->
# Référence

Chaque type et module public du paquet, avec son rôle.

## PHP

| Type | Rôle |
|---|---|
| `Contract\Expression` | Un nœud d'expression : sa règle en JsonLogic, ses enfants |
| `Expression\Literal` | Une constante, son nombre gardé comme JavaScript le tient |
| `Expression\ListOf` | Une liste d'expressions |
| `Expression\Variable` | `var` : une valeur de la réponse par son nom, avec une valeur par défaut facultative |
| `Expression\Missing` | `missing` et `missing_some` |
| `Expression\Comparison` | Deux valeurs comparées |
| `Expression\ComparisonOperator` | `==`, `!=`, `===`, `!==`, `<`, `<=`, `>`, `>=` |
| `Expression\Between` | Une valeur entre deux bornes, comprises ou non |
| `Expression\Not` | `!` et `!!` |
| `Expression\AllOf` | `and` |
| `Expression\AnyOf` | `or` |
| `Expression\Conditional` | `if` |
| `Expression\In` | L'appartenance à une liste ou à un texte |
| `Expression\Arithmetic` | L'arithmétique simple |
| `Expression\ArithmeticOperator` | `+`, `-`, `*`, `/`, `%`, `min`, `max`, et les opérandes que chacun prend |
| `JsonLogic\JsonLogicReader` | Lit une règle du sous-ensemble, en JSON ou décodée, dans ses limites |
| `JsonLogic\JsonLogicWriter` | Écrit une expression en JSON canonique |
| `JsonLogic\Limits` | Les limites du lecteur |
| `Evaluation\Evaluator` | La valeur et la valeur de vérité d'une expression pour une réponse |
| `Typing\VariableType` | Ce qu'une variable contient |
| `Typing\VariableDefinition` | Une variable qu'une règle peut lire : nom, type, choix |
| `Typing\Schema` | Les variables qu'une règle peut lire, par nom |
| `Typing\TypeChecker` | Vérifie une expression contre un schéma |
| `Typing\VariableNames` | Les variables qu'une expression lit |
| `Logic\FieldCondition` | La condition sous laquelle un champ est affiché |
| `Logic\ComputedVariable` | Une valeur calculée à partir de la réponse, et son type |
| `Logic\FormLogic` | Les étapes d'un formulaire dans l'ordre, vérifiées, écrites et relues |
| `Logic\Judge` | Exécute les étapes sur une réponse soumise |
| `Logic\Verdict` | La réponse sans les champs masqués, les champs masqués, les variables calculées |
| `FormsBundle\JsonLogicConditions` | Le `ConditionEvaluator` de `phil/forms-bundle` |
| `Exception\FormLogicException` | Tout ce que la bibliothèque lance |
| `Exception\InvalidLogic` | Une règle, une expression ou la logique d'un formulaire refusée |

`Evaluation\Values` contient les conversions que l'évaluateur partage avec le navigateur ; il est interne.

## JavaScript

| Module | Rôle |
|---|---|
| `assets/src/logic_controller.js` | Le contrôleur `logic` : champs affichés et masqués, valeurs calculées affichées |
| `assets/src/logic.js` | L'évaluateur du navigateur, et le juge des étapes |
