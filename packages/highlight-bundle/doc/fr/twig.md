<!-- phil-dev source-sha256=759f21e34de5e678f758e83e1e1afe9f0c7270d41f92fd198a3bc64251f840de -->
# Twig

Le filtre est enregistré quand Twig est installé.

| Filtre | Entrée | Sortie |
|---|---|---|
| `highlight(language = null, lines = null)` | Du code, sous forme de texte | Le code échappé, enveloppé dans des `span` `hl-`, marqué sûr |

```twig
<pre><code class="language-php">{{ snippet|highlight('php') }}</code></pre>
```

Le filtre donne ce qui va à l'intérieur de `<code>` : ni `pre`, ni `code`, ni structure de lignes propre.
`white-space: pre`, que le navigateur donne à `pre` et que le [thème](theme.md) conserve, préserve les lignes. La classe
`language-…` permet au contrôleur de coloration de `phil/ux-bundle` de savoir que le bloc est du code, et au thème de le
mettre en page.

## Échappement

Le code est toujours du texte. Chaque caractère est échappé (`&`, `<`, `>`, `"` et `'`), l'UTF-8 invalide devient
U+FFFD, et le seul balisage du résultat est celui des `span` du coloriseur. Une chaîne marquée sûre, par `|raw` ou un
autre filtre, est quand même lue comme du code : ses balises sont affichées, jamais exécutées.

| Langage | Résultat |
|---|---|
| Un langage que lit le coloriseur, quelle que soit la casse : `php`, `PHP`, `js` | Le code coloré |
| Un langage inconnu, ou aucun | Le code échappé, sans `span` |
| N'importe lequel, sur `null` | Une chaîne vide |

```twig
<pre><code class="language-{{ block.language }}">{{ block.code|highlight(block.language) }}</code></pre>
```

## Lignes

`lines` écrit le bloc ligne par ligne, chaque ligne dans un `<span class="hl-line">`, comme le décrit la documentation
des lignes de `phil/highlight` :

```twig
<pre><code class="language-php">{{ snippet|highlight('php', lines: {numbers: true, highlight: '2,4-5', annotations: true}) }}</code></pre>
```

| Clé | Valeur | Effet |
|---|---|---|
| `numbers` | `true`, `false` ou le numéro de la première ligne | Chaque ligne numérotée, à partir de 1 ou du numéro donné |
| `highlight` | Des plages de lignes, comme `'1,3-5'` | Ces lignes mises en évidence, comptées à partir de 1 dans le bloc |
| `annotations` | `true` ou `false` | Les `[!code ++]`, `[!code --]` et `[!code highlight]` des commentaires lus et retirés |

Un hachage vide écrit les lignes sans numéro ni marque ; des `LineOptions` construites en PHP sont prises telles
quelles. Un langage que le coloriseur ne connaît pas, ou aucun, donne les lignes échappées, sans couleurs, numérotées et
marquées tout de même. Une clé que le filtre ne lit pas, une valeur du mauvais type ou des plages illisibles lèvent une
exception qui nomme ce qui ne va pas : les options sont écrites par l'auteur du gabarit, pas par ses lecteurs.
