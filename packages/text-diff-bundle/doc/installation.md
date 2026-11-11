# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | As `phil/text-diff` |
| `phil/text-diff` | The comparison of texts and documents |
| `phil/ujo` | The documents `text_diff_document` reads |
| `symfony/config`, `symfony/dependency-injection` 8.2 | The configuration and the services |
| `twig/twig` 3.21, optional | The `text_diff` and `text_diff_document` filters |
| `phil/ujo-bundle`, optional | Documents read and rendered with the application's block types |
| `symfony/html-sanitizer` 8.2, optional | HTML a template does not mark safe, sanitized rather than escaped |
| `symfony/stimulus-bundle`, optional | The `comparison` controller |
| `symfony/asset-mapper` 8.2, optional | The controller and the stylesheet served without a build step |

## Installing

```sh
composer require phil/text-diff-bundle
```

Symfony Flex enables the bundle; without Flex, add it to `config/bundles.php`:

```php
return [
    // ...
    Phil\TextDiffBundle\PhilTextDiffBundle::class => ['all' => true],
];
```

With no configuration, the differ searches up to a thousand edits and `text_diff` compares by words.

## The controller

With AssetMapper, the bundle maps its `assets/src` folder as `@phil/text-diff-bundle/`. StimulusBundle registers the
controller from the application's `assets/controllers.json`:

```json
{
    "controllers": {
        "@phil/text-diff-bundle": {
            "comparison": { "enabled": true, "fetch": "lazy" }
        }
    },
    "entrypoints": []
}
```

The controller is then `phil--text-diff-bundle--comparison`, loaded the first time a page uses it. With another
pipeline, register `vendor/phil/text-diff-bundle/assets/src/comparison_controller.js` under that name.

## The stylesheet

AssetMapper also maps `assets/styles` as `phil-text-diff/`; a layout links it:

```twig
<link rel="stylesheet" href="{{ asset('phil-text-diff/text-diff.css') }}">
```

With another pipeline, import `vendor/phil/text-diff-bundle/assets/styles/text-diff.css`. See [Styling](styling.md).
