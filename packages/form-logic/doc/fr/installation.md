<!-- phil-dev source-sha256=5f3461134facdb23ba8de71e51656555c575e831f9b809f2a0525c1d1049d68e -->
# Installation

```sh
composer require phil/form-logic
```

La bibliothèque exige PHP 8.5 ou une version plus récente, avec `ext-mbstring`. Elle n'a aucune autre dépendance
d'exécution : une application construit elle-même le lecteur, l'évaluateur et le juge, ou les enregistre comme
services, chacun sans état et sûr à partager entre les requêtes.

## Le contrôleur Stimulus

Le contrôleur est dans `assets/src/logic_controller.js`, avec son évaluateur `assets/src/logic.js` ;
`assets/package.json` le déclare comme le contrôleur `logic` du nom npm `@phil/form-logic`, avec Stimulus 3 en
dépendance paire. Avec AssetMapper et StimulusBundle, l'application associe le répertoire et active le contrôleur :

```yaml
# config/packages/asset_mapper.yaml
framework:
    asset_mapper:
        paths:
            assets/: ~
            vendor/phil/form-logic/assets/src: '@phil/form-logic'
```

```json
{
    "controllers": {
        "@phil/form-logic": {
            "logic": { "enabled": true, "fetch": "eager" }
        }
    }
}
```

Le second bloc va dans `assets/controllers.json`. Le contrôleur s'appelle alors `phil--form-logic--logic` ; voir
[JavaScript](javascript.md) pour le balisage qu'il lit.

## Avec phil/forms-bundle

`phil/forms-bundle` lit les conditions de ses formulaires par son port `ConditionEvaluator`, par défaut avec ses
propres trois opérateurs. `JsonLogicConditions` les lit comme tout le sous-ensemble de cette bibliothèque, vérifié
contre les types des champs. L'application installe les deux paquets et fait pointer l'alias du port :

```yaml
# config/services.yaml
services:
    Phil\FormLogic\FormsBundle\JsonLogicConditions: ~
    Phil\FormsBundle\Domain\Contract\ConditionEvaluator: '@Phil\FormLogic\FormsBundle\JsonLogicConditions'
```

Le bundle vérifie alors chaque condition quand un formulaire est défini, et l'évalue quand une entrée arrive. Sa page
écrit aussi le contrôleur de cette bibliothèque sur un formulaire dont des champs ont des conditions, alias ou non :
avec le contrôleur servi comme ci-dessus et l'import map chargée par la page de l'application, le navigateur affiche et
masque ces champs à mesure que le formulaire se remplit ; voir
[Logique de formulaire](form-logic.md#avec-phil-forms-bundle).
