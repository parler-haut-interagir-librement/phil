<!-- phil-dev source-sha256=3a3f9dcb41c137f1393837225e51bb34b71f7285881c4c701072b9e33c23c172 -->
# Référence

| Classe | Rôle |
|---|---|
| `Phil\TextDiffBundle\PhilTextDiffBundle` | Le bundle : la configuration `phil_text_diff`, ses services, et les ressources associées pour AssetMapper |
| `Phil\TextDiffBundle\Infrastructure\DependencyInjection\TextDiffServices` | Enregistre les services ; les espaces de noms des ressources sous forme de constantes |
| `Phil\TextDiffBundle\Infrastructure\Twig\TextDiffExtension` | Les filtres `text_diff` et `text_diff_document` |

| Service | Classe |
|---|---|
| `Phil\TextDiff\TextDiff`, public | `TextDiff`, avec la borne de modifications configurée et, quand `phil/ujo-bundle` est installé, son `Normalizer` |
| `Phil\TextDiffBundle\Infrastructure\Twig\TextDiffExtension` | `TextDiffExtension`, quand Twig est installé |

| Constante | Valeur |
|---|---|
| `PhilTextDiffBundle::CONTROLLERS_DIR` | Le dossier `assets/src` du bundle |
| `PhilTextDiffBundle::STYLES_DIR` | Le dossier `assets/styles` du bundle |
| `TextDiffServices::CONTROLLER_NAMESPACE` | `@phil/text-diff-bundle` |
| `TextDiffServices::ASSET_NAMESPACE` | `phil-text-diff` |

| Fichier | Rôle |
|---|---|
| `assets/package.json` | La déclaration du contrôleur pour StimulusBundle |
| `assets/src/comparison_controller.js` | Le contrôleur `comparison` : voir [JavaScript](javascript.md) |
| `assets/src/lib/marks.js` | Les vues, les arrêts, le pas et les libellés, sans le DOM |
| `assets/styles/text-diff.css` | La feuille de style : voir [Mise en forme](styling.md) |
