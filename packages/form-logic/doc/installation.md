# Installation

```sh
composer require phil/form-logic
```

The library needs PHP 8.5 or later with `ext-mbstring`. It has no other runtime dependency: an application builds
the reader, the evaluator and the judge itself, or registers them as services, each stateless and safe to share
between requests.

## The Stimulus controller

The controller is in `assets/src/logic_controller.js`, with its evaluator `assets/src/logic.js`; `assets/package.json`
declares it as the controller `logic` of the npm name `@phil/form-logic`, with Stimulus 3 as a peer dependency. With
AssetMapper and StimulusBundle, the application maps the directory and enables the controller:

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

The second block goes in `assets/controllers.json`. The controller is then named `phil--form-logic--logic`; see
[JavaScript](javascript.md) for the markup it reads.

## With phil/forms-bundle

`phil/forms-bundle` reads the conditions of its forms through its port `ConditionEvaluator`, by default with its own
three operators. `JsonLogicConditions` reads them as the whole subset of this library, checked against the types of
the fields. The application installs both packages and aliases the port:

```yaml
# config/services.yaml
services:
    Phil\FormLogic\FormsBundle\JsonLogicConditions: ~
    Phil\FormsBundle\Domain\Contract\ConditionEvaluator: '@Phil\FormLogic\FormsBundle\JsonLogicConditions'
```

The bundle then checks each condition when a form is defined, and evaluates it when an entry comes. Its page also
writes this library's controller on a form whose fields have conditions, alias or not: with the controller served as
above and the import map loaded by the application's page, the browser shows and hides those fields as the form is
filled; see [Form logic](form-logic.md#with-phil-forms-bundle).
