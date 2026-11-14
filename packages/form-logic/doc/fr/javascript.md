<!-- phil-dev source-sha256=99efab4de892ead5620ceeff25c4e9a3490cac047c3b67719ca3fcaf0ec1b557 -->
# JavaScript

## Le contrôleur

`assets/src/logic_controller.js`, enregistré sous `phil--form-logic--logic`, s'exécute sur la balise du formulaire. Sa
seule valeur, `steps`, est la logique telle que `FormLogic::toArray()` l'écrit.

```twig
<form data-controller="phil--form-logic--logic"
      data-phil--form-logic--logic-steps-value="{{ logic.toArray()|json_encode }}">
    <div data-form-logic-field="guest_name">
        <label for="guest_name">Guest</label>
        <input id="guest_name" name="entry[guest_name]">
    </div>
    <p>Seats: <output data-form-logic-output="seats"></output></p>
</form>
```

| Attribut | Sur | |
|---|---|---|
| `data-form-logic-field` | l'enveloppe d'un champ | Le champ dont la condition affiche ou masque l'enveloppe |
| `data-form-logic-output` | n'importe quel élément | La variable calculée dont la valeur est son texte, vide quand elle vaut `null` |

À sa connexion, et à chaque `input` ou `change` dans le formulaire, le contrôleur collecte la réponse, exécute les
étapes comme le `Judge` du serveur, puis :

- met `hidden` sur l'enveloppe de chaque champ masqué et désactive ses contrôles, afin que le navigateur ne les envoie
  pas ; un contrôle que la page avait désactivé reste désactivé, et les contrôles qu'il avait désactivés sont activés de
  nouveau quand le champ est affiché, leurs valeurs relues ;
- écrit chaque variable calculée dans ses sorties ;
- émet `phil--form-logic--logic:change`, avec `{hidden, computed}` dans `detail`.

Une règle qu'il ne peut pas lire — hors du sous-ensemble ou mal formée — laisse tous les champs affichés et toutes les
sorties vides. Le serveur lit les mêmes règles strictement et juge de nouveau la réponse, quoi que le navigateur ait
envoyé.

## La réponse qu'il collecte

Chaque contrôle activé du formulaire répond sous son nom, `entry[guest_name]` et `guest_name[]` étant lus comme
`guest_name` ; les contrôles submit, button, reset et file sont laissés de côté.

| Contrôle | Valeur |
|---|---|
| Une case à cocher seule | `true` ou `false` |
| Des cases à cocher d'un même nom, ou un nom qui finit par `[]` | la liste des valeurs cochées |
| Une liste à sélection multiple | la liste des valeurs sélectionnées |
| Des boutons radio | la valeur cochée, ou `null` |
| Tout autre contrôle | sa valeur ; une valeur vide est `null` |

## L'évaluateur

`assets/src/logic.js` contient l'évaluateur, règle pour règle le même que les classes PHP `Evaluator` et `Values` :

| Export | |
|---|---|
| `check(rule)` | Refuse en bloc une règle hors du sous-ensemble, branches non atteintes comprises, par `UnreadableRule` |
| `evaluate(rule, data)` | La valeur d'une règle pour ces données |
| `holds(rule, data)` | Sa valeur de vérité |
| `judge(steps, answer)` | Les champs masqués et les variables calculées, tels que le `Judge` du serveur les donne |
| `truthy`, `toNumber`, `toText`, `looselyEqual`, `strictlyEqual`, `less`, `contains` | Les conversions de [Les règles](rules.md#les-conversions) |
