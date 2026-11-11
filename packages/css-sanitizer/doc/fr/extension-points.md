<!-- phil-dev source-sha256=a9f1eced97c4d306c74b658d0d6e9bd6e87900d970ec064e26c3bedf6c5ac98b -->
# Points d'extension

La bibliothèque est en PHP simple et ne déclare aucun service. Ce que choisit une application, c'est la `Policy`
qu'elle donne à l'assainisseur, et un port : où mènent les URL.

## `UrlRewriter`

`Phil\CssSanitizer\UrlRewriter` : chaque URL d'une feuille de style passe par le réécrivain de la politique, qui
renvoie l'URL à écrire ou `null` pour la refuser. Voir [URL](urls.md).

| Méthode | Contrat |
|---|---|
| `rewrite(string $url, UrlKind $kind): ?string` | Prend l'URL telle qu'elle est écrite, échappements résolus, et ce qu'elle charge — `Image`, `Font` ou `StyleSheet` ; renvoie une URL qui ne charge rien que l'application n'a pas voulu, ou `null` |

`Url\RefuseUrls`, la valeur par défaut, refuse tout ; `Url\AllowedFiles` fait correspondre des noms de fichiers à des
URL publiques ; `Url\CallbackRewriter` demande à une fermeture. Un réécrivain propre à l'application ne renvoie jamais
une URL `javascript:` ni une URL d'une autre origine qu'elle n'a pas choisie : l'assainisseur refuse les schémas qui
exécutent du code, pas les adresses que l'application permet.

## La politique

`Policy` est une valeur : chaque méthode `with` en renvoie une nouvelle. Voir [Politique](policy.md).

| Point | Méthodes |
|---|---|
| Les propriétés et fonctions permises | `withProperties()`, `withoutProperties()`, `withFunctions()`, `withoutFunctions()` |
| Les sélecteurs permis | `withPseudoClasses()`, `withPseudoElements()`, `withAttributes()` et leurs formes `without` |
| Les règles @ permises | `withAtRules()`, `withoutAtRules()`, `withMediaTypes()` |
| Où s'appliquent les règles | `withScope()` : chaque sélecteur reçoit un préfixe, pour qu'une feuille stylise une partie d'une page |
| `!important` et propriétés personnalisées | `withImportant()`, `withCustomProperties()` |
| Les bornes du travail | `withMaxLength()`, `withMaxDepth()`, `withMaxNesting()` |

Ce que liste [Jamais permis](policy.md#jamais-permis) n'est accessible par aucune d'elles.

## Le validateur

`Validator\SafeCss` utilise le `CssSanitizer` donné à `SafeCssValidator`. Une déclaration de service avec la politique
de l'application remplace celle par défaut, qui refuse toutes les URL ; voir [Validateur](validator.md).
