<!-- phil-dev source-sha256=22eee01844c2920fb21f22bb50b66f51e38a2417f1872d4b7b33175175fbd323 -->
# URL

Chaque URL d'une feuille de style — `url(…)` avec ou sans guillemets, dans une déclaration ou dans une source
`@font-face`, une image d'`image-set()`, la feuille de style d'un `@import` — passe par le `UrlRewriter` de la
politique. Celui-ci renvoie l'URL à écrire, ou `null` pour la refuser. Une URL refusée retire sa déclaration (`Url`),
dans `@font-face` cette seule source, et la règle `@import` qui la contient.

```php
interface UrlRewriter
{
    public function rewrite(string $url, UrlKind $kind): ?string;
}
```

L'URL est donnée telle qu'elle est écrite, échappements résolus. `UrlKind` dit ce qu'elle charge : `Image` pour une
déclaration — un arrière-plan, une puce de liste, un masque, un filtre, une image d'`image-set()` —, `Font` pour une
source `@font-face`, `StyleSheet` pour un `@import`. La sortie écrit toujours `url("…")`.

## Les réécrivains

| Réécrivain | Ce qu'il fait |
|---|---|
| `Url\RefuseUrls` | Refuse tout ; la valeur par défaut |
| `Url\AllowedFiles` | Fait correspondre les noms qu'une feuille de style peut écrire aux URL publiques des fichiers du projet, images, polices et feuilles de style séparées |
| `Url\CallbackRewriter` | Demande à une fermeture |

```php
use Phil\CssSanitizer\Url\AllowedFiles;

$files = new AllowedFiles(
    images: ['paper.jpg' => '/media/42/paper.jpg', 'logo.svg' => '/media/42/logo.svg'],
    fonts: ['body.woff2' => '/media/42/body.woff2'],
    styleSheets: ['theme.css' => '/media/42/theme.css'],
);
```

Avec `AllowedFiles`, `url(paper.jpg)` et `url("./paper.jpg")` deviennent `url("/media/42/paper.jpg")`. Toute autre
URL — un autre site, un autre fichier du même site, `../paper.jpg` — est refusée. Un nom de police n'est pas un nom
d'image, ni le nom d'une feuille de style.

```php
use Phil\CssSanitizer\Url\CallbackRewriter;
use Phil\CssSanitizer\UrlKind;

$rewriter = new CallbackRewriter(fn (string $url, UrlKind $kind): ?string =>
    $this->media->publicUrlOf($project, $url, $kind));
```

## `image-set()`

`image-set()` et `-webkit-image-set()` ne sont pas des fonctions de la politique : l'assainisseur les lit lui-même.
Chaque option est une image — une chaîne, lue comme une URL, une `url()`, ou une fonction que la politique permet,
comme un dégradé — suivie d'au plus une résolution (`1x`, `2dppx`, `96dpi`, `38dpcm`) et d'au plus un `type()` qui
contient une chaîne. Chaque URL passe par le réécrivain avec `UrlKind::Image` et s'écrit `url("…")` ; `type()` ne
charge rien et reste tel qu'il est écrit.

```css
a { background-image: image-set("paper.jpg" 1x type("image/jpeg"), url(paper@2x.jpg) 2x) }
/* a { background-image: image-set(url("/media/42/paper.jpg") 1x type("image/jpeg"), url("/media/42/paper@2x.jpg") 2x) } */
```

Une seule URL refusée refuse la déclaration (`Url`, `DangerousUrl`) ; une option d'une autre forme la refuse aussi
(`Value`).

## `@import`

Quand la politique permet `AtRuleKind::Import`, chaque `@import` qui ouvre la feuille de style voit son URL — une
chaîne ou une `url()` — réécrite avec `UrlKind::StyleSheet`, et s'écrit en premier dans la sortie. Une URL que le
réécrivain refuse, ou un fragment, retire la règle (`Url`). Voyez [Règles @](at-rules.md).

## Les fragments

Une URL faite d'un seul fragment — `url(#blur)` pour un filtre SVG, `url(#chapter-2)` dans `target-counter()` —
pointe dans le document et ne charge rien. Elle est gardée telle quelle et n'atteint jamais le réécrivain. Un
`@import` d'un fragment chargerait la page elle-même comme feuille de style : il est refusé.

## Les schémas qui exécutent du code

Une URL dont le schéma est `javascript:`, `vbscript:`, `livescript:` ou `mocha:` est refusée (`DangerousUrl`) avant
que le réécrivain la voie, une fois retirés les blancs ASCII et les caractères de contrôle : `" java\9 script:"` est
détectée. Une URL renvoyée par le réécrivain est vérifiée de la même façon.

## Ce qui ne charge rien

`image()`, `cross-fade()`, `element()`, `src()` et `paint()`, qui prennent une ressource dans une chaîne ou ailleurs
dans la page, ne peuvent jamais être permises. Les conditions `@supports` qui contiennent une URL sont refusées, y
compris dans `@import`, et les requêtes média ne peuvent pas en contenir.
