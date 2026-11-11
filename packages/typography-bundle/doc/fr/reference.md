<!-- phil-dev source-sha256=9634b6aef640b526b89d14936c51853f055168b364079a5a8538602d97de2775 -->
# Référence

| Classe | Rôle |
|---|---|
| `Phil\TypographyBundle\PhilTypographyBundle` | Le bundle : la configuration `phil_typography`, ses services et la passe de compilation |
| `Phil\TypographyBundle\Infrastructure\DependencyInjection\TypographyServices` | Enregistre les services ; les identifiants de service en constantes |
| `Phil\TypographyBundle\Infrastructure\DependencyInjection\UjoFilterPass` | Décore le `HtmlRenderer` de `phil/ujo-bundle` avec le filtre typographique, ou retire le filtre |
| `Phil\TypographyBundle\Infrastructure\Twig\TypographyExtension` | Les filtres `typography` et `typography_text` |
| `Phil\TypographyBundle\Infrastructure\Ujo\TypographyHtmlFilter` | Le `HtmlFilter` de `phil/ujo` qui exécute un typographe |

| Identifiant de service | Classe |
|---|---|
| `Phil\Typography\Hyphenation\Dictionary` | `Dictionary` |
| `Phil\Typography\Typographers` | `Typographers` |
| `Phil\Typography\Typographer` | `Typographer` |
| `phil_typography.typographer.<locale>` | `Typographer` |
| `phil_typography.ujo_filter` | `TypographyHtmlFilter` |
| `phil_typography.ujo_renderer` | `Phil\Ujo\Html\HtmlRenderer`, qui décore le moteur de rendu de `phil/ujo-bundle` |
