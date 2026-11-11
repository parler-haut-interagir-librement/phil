<!-- phil-dev source-sha256=6cdcd57c01e4888c252cd174dbe079b9c1e69a501574de68c47c7536737d160d -->
# Règles `@font-face`

`FontFaceRule` écrit le CSS qui sert une face. Elle est immuable : chaque méthode `with…()` retourne une nouvelle règle.

```php
use Phil\Fonts\Css\FontDisplay;
use Phil\Fonts\Css\FontFaceRule;
use Phil\Fonts\Format;

echo FontFaceRule::for($font)
    ->withUrl('/fonts/example.woff2', Format::Woff2)
    ->withUrl('/fonts/example.woff', Format::Woff)
    ->withDisplay(FontDisplay::Fallback)
    ->toCss();
```

```css
@font-face {
    font-family: "Example Sans";
    src: local("Example Sans Bold"), local("ExampleSans-Bold"), url("/fonts/example.woff2") format("woff2"), url("/fonts/example.woff") format("woff");
    font-weight: 700;
    font-style: normal;
    font-stretch: 100%;
    font-display: fallback;
}
```

## Construire une règle

| Méthode | Effet |
|---|---|
| `FontFaceRule::for(Font $font)` | Une règle sous la famille de la face, avec son nom complet et son nom PostScript comme sources `local()` (une seule lorsqu'ils sont identiques), et aucun fichier pour l'instant |
| `FontFaceRule::forFile(FontFile $file, string $url, int $face = 0)` | La même chose, pour une face d'un fichier servi à une URL dans le format du fichier |
| `withUrl(string $url, Format $format)` | Un fichier à télécharger, après les sources déjà données : listez le plus petit format d'abord |
| `withFamily(string $family)` | Un autre nom de famille, pour regrouper des faces sous un même nom ou nommer une face qui n'en a pas |
| `withoutLocal()` | Aucune source `local()` : le navigateur télécharge le fichier même lorsqu'une police du même nom est installée |
| `withDisplay(FontDisplay $display)` | `auto`, `block`, `swap` (la valeur par défaut), `fallback` ou `optional` |
| `withUnicodeRange(?array $ranges = null)` | Un descripteur `unicode-range` : les plages données, ou celles de la table de caractères de la face |

`toCss()` lance `InvalidFontFace` lorsque la règle n'a pas de famille (la police n'en nomme aucune et aucune n'a été
donnée) ou pas de source.

## Descripteurs

| Descripteur | Écrit à partir de |
|---|---|
| `font-weight` | La classe de graisse, ou la plage de l'axe `wght` d'une police variable : `100 900` |
| `font-style` | `normal`, `italic` ou `oblique`, selon le style de la face |
| `font-stretch` | Le pourcentage de la classe de largeur, ou la plage de l'axe `wdth` : `75% 125%` |
| `unicode-range` | Chaque plage sous la forme `U+20-7E`, un point de code isolé sous la forme `U+E9` |

Les plages sont arrondies à trois décimales. La plage d'un axe `slnt` et l'angle d'une face oblique ne sont pas écrits :
ajoutez `font-style: oblique 0deg 12deg` au CSS de l'application lorsque l'inclinaison d'une police variable doit
pouvoir être choisie.

## Échappement

Les noms de police et les URL viennent de fichiers que des personnes téléversent. Chaque chaîne est écrite entre
guillemets doubles ; `"` et `\` sont échappés par une barre oblique inverse, et `<`, `>` et les caractères de contrôle
par des échappements hexadécimaux CSS. Un nom comme `</style><script>` ne peut donc terminer ni la chaîne, ni la règle,
ni l'élément `<style>` qui la contient.

L'URL est écrite telle qu'elle est donnée : la réécrire vers un chemin que l'application sert, ou refuser un schéma,
revient à l'application, ou à `phil/css-sanitizer` lorsque le CSS est vérifié dans son ensemble.
