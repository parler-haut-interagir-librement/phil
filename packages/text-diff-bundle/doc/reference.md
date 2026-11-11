# Reference

| Class | Role |
|---|---|
| `Phil\TextDiffBundle\PhilTextDiffBundle` | The bundle: the `phil_text_diff` configuration, its services, and the assets mapped for AssetMapper |
| `Phil\TextDiffBundle\Infrastructure\DependencyInjection\TextDiffServices` | Registers the services; the asset namespaces as constants |
| `Phil\TextDiffBundle\Infrastructure\Twig\TextDiffExtension` | The `text_diff` and `text_diff_document` filters |

| Service | Class |
|---|---|
| `Phil\TextDiff\TextDiff`, public | `TextDiff`, with the configured bound of edits and, when `phil/ujo-bundle` is installed, its `Normalizer` |
| `Phil\TextDiffBundle\Infrastructure\Twig\TextDiffExtension` | `TextDiffExtension`, when Twig is installed |

| Constant | Value |
|---|---|
| `PhilTextDiffBundle::CONTROLLERS_DIR` | The bundle's `assets/src` folder |
| `PhilTextDiffBundle::STYLES_DIR` | The bundle's `assets/styles` folder |
| `TextDiffServices::CONTROLLER_NAMESPACE` | `@phil/text-diff-bundle` |
| `TextDiffServices::ASSET_NAMESPACE` | `phil-text-diff` |

| File | Role |
|---|---|
| `assets/package.json` | The controller's declaration for StimulusBundle |
| `assets/src/comparison_controller.js` | The `comparison` controller: see [JavaScript](javascript.md) |
| `assets/src/lib/marks.js` | The views, the stops, the step and the labels, without the DOM |
| `assets/styles/text-diff.css` | The stylesheet: see [Styling](styling.md) |
