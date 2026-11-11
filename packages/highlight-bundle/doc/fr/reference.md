<!-- phil-dev source-sha256=47d56193174eb6fae0674b16a1d85f11de8de70144f8ec7621da7dcfdaf9e6d6 -->
# Référence

| Classe | Rôle |
|---|---|
| `Phil\HighlightBundle\PhilHighlightBundle` | Le bundle : la configuration `phil_highlight`, ses services, la feuille de style déclarée pour AssetMapper, la configuration automatique des langages et la passe de compilation |
| `Phil\HighlightBundle\Infrastructure\DependencyInjection\HighlightServices` | Enregistre les services ; `isName()`, la vérification d'un nom ; les identifiants de service, l'étiquette et l'espace de noms des ressources en constantes |
| `Phil\HighlightBundle\Infrastructure\DependencyInjection\HighlighterPass` | Ajoute les langages étiquetés et les alias au coloriseur ; décore le `HtmlRenderer` de `phil/ujo-bundle`, ou retire le coloriseur de code |
| `Phil\HighlightBundle\Infrastructure\Language\AliasedLanguage` | Un `Language` de `phil/highlight` sous les alias configurés |
| `Phil\HighlightBundle\Infrastructure\Twig\HighlightExtension` | Le filtre `highlight` |

| Service | Classe |
|---|---|
| `Phil\Highlight\Highlighter` | `Highlighter` |
| `phil_highlight.code_highlighter` | `Closure` |
| `phil_highlight.ujo_renderer` | `Phil\Ujo\Html\HtmlRenderer` |
| `Phil\HighlightBundle\Infrastructure\Twig\HighlightExtension` | `HighlightExtension` |

| Constante | Valeur |
|---|---|
| `HighlightServices::LANGUAGE_TAG` | `phil_highlight.language` |
| `HighlightServices::ALIASES` | `phil_highlight.aliases`, le paramètre des alias configurés |
| `HighlightServices::ASSET_NAMESPACE` | `phil-highlight` |

| Fichier | Rôle |
|---|---|
| `assets/styles/highlight.css` | Le thème : voir [Thème](theme.md) |
