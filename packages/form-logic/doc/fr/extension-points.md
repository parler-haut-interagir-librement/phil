<!-- phil-dev source-sha256=e88c12430a77fc88f992d930095f6580004382926010942d7435ceb887e5077b -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

## Configuré par l'application

| Type | Ce qu'il règle |
|---|---|
| `Limits` | La taille d'une règle que `JsonLogicReader` lit : octets, profondeur, nœuds, éléments de liste, longueur de texte |
| `Schema` et `VariableDefinition` | Les variables qu'une règle peut lire, avec leurs types et leurs choix |
| `ComputedVariable::$type` | Le type qu'une variable calculée donne aux étapes qui la suivent |

## Ports implémentés par le paquet

| Port | Implémentation |
|---|---|
| `Phil\FormsBundle\Domain\Contract\ConditionEvaluator` de `phil/forms-bundle` | `JsonLogicConditions`, en lui faisant pointer l'alias du port |

## Ce sur quoi on s'appuie

- Le langage est fermé : les évaluateurs connaissent les nœuds de `Phil\FormLogic\Expression` et rien d'autre. Une
  expression d'une autre classe lance `InvalidLogic` ; un opérateur est ajouté par la bibliothèque, en PHP et en
  JavaScript ensemble, avec ses cas communs.
- `FormLogic::toArray()` est le format que le contrôleur lit et le format à stocker ; il ne change qu'avec une version
  mineure, et `FormLogic::fromArray()` le relit.
- `Evaluator`, `JsonLogicReader`, `TypeChecker` et `Judge` ne gardent aucun état entre les appels : une instance sert
  toutes les requêtes d'un *worker*.
