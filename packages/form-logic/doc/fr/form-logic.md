<!-- phil-dev source-sha256=eb9d2414bd6aa493e010b50062ebd7148a30b0377a8d5fbc97cd21dbc0b46aeb -->
# Logique de formulaire

## Expressions

Une règle lue par `JsonLogicReader` est un arbre d'objets immuables sous `Phil\FormLogic\Expression`, chacun
implémentant `Phil\FormLogic\Contract\Expression`. Une application peut aussi les construire directement :

```php
use Phil\FormLogic\Expression\{AllOf, Between, Comparison, ComparisonOperator, Literal, Not, Missing, Variable};

$minorWithGuardian = new AllOf([
    new Between(new Literal(0), new Variable('age'), new Literal(17), inclusive: true),
    new Not(new Missing(['guardian_name'])),
]);
```

| Nœud | Règle |
|---|---|
| `Literal` | Une constante |
| `ListOf` | Une liste d'expressions |
| `Variable` | `var`, avec une valeur par défaut facultative |
| `Missing` | `missing`, ou `missing_some` avec `$atLeast` |
| `Comparison` | `==`, `!=`, `===`, `!==`, `<`, `<=`, `>`, `>=`, par `ComparisonOperator` |
| `Between` | `<` ou `<=` de trois opérandes |
| `Not` | `!`, ou `!!` avec `$double` |
| `AllOf`, `AnyOf` | `and`, `or` |
| `Conditional` | `if` |
| `In` | `in` |
| `Arithmetic` | `+`, `-`, `*`, `/`, `%`, `min`, `max`, par `ArithmeticOperator` |

Chaque nœud se vérifie à sa construction : un nom qui n'est pas un nom de variable, une opération sans opérande, un
nombre non fini lancent `InvalidLogic`. `jsonLogic()` donne la règle, prête pour `json_encode()` ; `JsonLogicWriter`
l'écrit en JSON canonique, que le lecteur relit en un arbre égal.

`Evaluator::evaluate()` donne la valeur d'une expression pour une réponse, et `holds()` sa valeur de vérité. La
réponse est une table de noms de champs vers des valeurs, comme [Les règles](rules.md#les-valeurs) la décrivent.

## Types

`TypeChecker` vérifie une expression contre un `Schema` de `VariableDefinition` — un nom, un `VariableType` et les
choix d'un choix —, telle qu'un formulaire est défini :

| Type | Comparé avec |
|---|---|
| `Text` | des textes ; examiné avec `in` |
| `Number` | des nombres ; ordonné, placé dans des intervalles, utilisé dans des calculs |
| `Boolean` | `true` et `false` |
| `Date` | des jours valides, `YYYY-MM-DD` ; ordonné et placé dans des intervalles |
| `Choice` | ses seuls choix |
| `Choices` | examiné avec `in`, pour l'un de ses choix |

Chaque variable que l'expression lit doit être dans le schéma ; une variable comparée à une constante doit l'être à une
valeur de son type, `null` désignant une variable sans réponse ; deux variables comparées doivent être du même type ;
seuls les nombres et les jours sont ordonnés. Le vérificateur lance `InvalidLogic` en nommant la première règle
enfreinte.

`VariableNames::of()` liste les variables qu'une expression lit, chacune une fois.

## La logique d'un formulaire

`FormLogic` contient les étapes d'un formulaire, dans l'ordre où elles sont évaluées :

- une `FieldCondition` — un champ et l'expression sous laquelle il est affiché et sa réponse conservée ;
- une `ComputedVariable` — un nom, une expression et le type qu'elle donne (un nombre, un texte, un booléen ou un
  jour).

Chaque étape lit la réponse, les champs masqués avant elle lus comme sans réponse, et les variables calculées avant
elle. Une étape ne lit jamais une étape qui la suit, ni elle-même, et deux étapes ne partagent jamais un nom : l'ordre
dit tout, et `FormLogic` refuse une logique qui l'enfreint. Un formulaire a 500 étapes au plus.

`check(Schema $fields)` vérifie chaque étape contre les types des champs, chaque variable calculée étant ensuite
lisible par les étapes qui la suivent avec son propre type. `toArray()` écrit les étapes en données, les règles en
JsonLogic, pour le stockage et pour le contrôleur Stimulus ; `FormLogic::fromArray()` les relit par un
`JsonLogicReader` :

```json
[
    {"field": "guest_name", "when": {"==": [{"var": "bringing_guest"}, true]}},
    {"compute": "seats", "type": "number", "value": {"+": [1, {"if": [{"var": "bringing_guest"}, 1, 0]}]}}
]
```

## Le juge

Le navigateur ne fait qu'afficher et masquer ; le serveur décide. `Judge::judge(FormLogic $logic, array $answer)`
exécute les étapes dans l'ordre sur la réponse soumise :

- un champ dont la condition ne tient pas est masqué : sa valeur est retirée de la réponse, et les étapes qui le suivent
  le lisent comme sans réponse, de sorte qu'un champ qui dépend d'un champ masqué est masqué lui aussi ;
- une variable calculée est évaluée et lisible par les étapes qui la suivent ; une valeur soumise du même nom est
  retirée, puisque le serveur la calcule, jamais le navigateur.

Le `Verdict` contient la réponse sans les champs masqués, les champs masqués dans l'ordre et les variables calculées,
pour valider la réponse et la stocker avec elles. `isShown()` dit si un champ a été posé.

## Avec phil/forms-bundle

`Phil\FormLogic\FormsBundle\JsonLogicConditions` implémente le `ConditionEvaluator` du bundle. Une condition est une
opération du sous-ensemble ; `check()` type chaque champ selon ce qu'il prend — un choix par ses choix, une case à
cocher comme un booléen, une date ou une date de naissance comme un jour, tout autre champ comme un texte — et refuse,
en `InvalidForm`, une condition que le lecteur ou le vérificateur de types refuse. `holds()` évalue la condition sur
les valeurs de l'entrée, un champ non posé n'ayant aucune valeur.

Le bundle garde son propre ordre de champs et son propre stockage : les variables calculées, que son port ne porte pas,
sont celles de l'application, par `FormLogic` et le `Judge`.

Avec cette bibliothèque installée, la page du bundle applique aussi ses conditions dans le navigateur, quel que soit
l'évaluateur qui les lit sur le serveur : son `LogicSteps` les écrit comme les étapes du contrôleur, et son
`_form.html.twig` met le contrôleur sur le formulaire et `data-form-logic-field` sur chaque rangée conditionnelle. Un
champ qu'une autre condition lit reçoit une variable calculée, `_shown_0`, `_shown_1`… — les noms de champs du bundle
ne commencent jamais par `_` —, que les conditions qui le lisent exigent, de sorte que le navigateur distingue un champ
laissé vide d'un champ non posé, comme le fait le bundle ; le contrôleur est servi comme le dit
[Installation](installation.md#le-controleur-stimulus).
