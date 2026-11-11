<!-- phil-dev source-sha256=f1af66a743469e896690b870c1c7f4d8450a5a7a9e938cc1536f5b557718e804 -->
# Documents UJO

`phil/ujo` rend les blocs de code d'un document sous la forme `<pre class="ujo-code"><code class="language-…">`. Son
`HtmlRenderer::withCodeHighlighter()` prend un colorateur (une fonction anonyme qui va du code et de son langage au
HTML, ou à `null` pour un langage qu'il ne connaît pas), et `Highlighter::highlight()` a cette forme :

```php
use Phil\Highlight\Highlighter;
use Phil\Ujo\Html\HtmlRenderer;

$renderer = HtmlRenderer::default()->withCodeHighlighter(Highlighter::default()->highlight(...));
```

Aucune des deux bibliothèques ne dépend de l'autre. Le moteur de rendu ne garde le HTML coloré que s'il ne contient
rien d'autre que le texte du code, échappé, dans des éléments `<span class="…">` ; tout autre HTML retombe sur le code
échappé : le moteur de rendu reste ainsi purifié par construction. Un bloc sans langage, ou avec un langage que le
colorateur ne connaît pas, est écrit comme du texte échappé avec sa classe `language-…`, pour que le contrôleur de
coloration de `phil/ux-bundle` s'y essaie dans le navigateur. Donnez au moteur de rendu `highlight()` sans
`LineOptions` : les numéros des [lignes](lines.md) et les annotations retirées changent le texte, et le moteur de
rendu retomberait sur le code échappé.

## À travers un purificateur

Le `SanitizerPurifier` de `phil/markup` retire les classes, sauf celles du code : `language-…` sur `code` à tous les
niveaux, et `hl-…` sur `span` aux niveaux qui gardent les balises span. Du HTML coloré purifié au niveau `all` garde
ses couleurs ; au niveau `forum`, les balises span s'en vont et le texte reste.
