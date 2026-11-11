<!-- phil-dev source-sha256=16de8ed0b89eb99d117caf9d6742e4f2242f3eb1bb9da16c552261aa02b62b53 -->
# Documents UJO

Quand `phil/ujo-bundle` est installé et que `ujo.enabled` est vrai (la valeur par défaut), le bundle décore le service
`Phil\Ujo\Html\HtmlRenderer` : `withCodeHighlighter()` donne le coloriseur à ses blocs de code. Tout ce dont le rendu
passe par ce service est coloré : `Ujo::toHtml()`, le filtre et la fonction `ujo_html`, les formats de
`phil/markup-bundle` lus en UJO, et le code qui injecte le moteur de rendu.

```twig
{{ article.body|ujo_html }}
{# <pre class="ujo-code"><code class="language-php"><span class="hl-variable">$a</span> … </code></pre> #}
```

Le moteur de rendu de `phil/ujo` ne garde le HTML coloré que s'il ne contient rien d'autre que le texte du code,
échappé, à l'intérieur d'éléments `<span class="…">` ; tout autre HTML retombe sur le code échappé, et le document reste
donc purifié par construction. Un bloc sans langage, ou avec un langage que le coloriseur ne connaît pas, est écrit
comme du texte échappé avec sa classe `language-…`, pour que le contrôleur de coloration de `phil/ux-bundle` l'essaie
dans le navigateur ; un bloc déjà coloré sur le serveur y est laissé tel quel.

La décoration se compose avec les autres sur le même service, comme la passe typographique de `phil/typography-bundle`,
dont le typographe ne modifie jamais le texte de `pre` et de `code`.

## Par un purificateur

Le `SanitizerPurifier` de `phil/markup` garde les classes `hl-…` sur `span` aux niveaux qui gardent les `span`, et la
classe `language-…` sur `code` à tous les niveaux : du HTML purifié au niveau `all` garde ses couleurs, au niveau
`forum` son texte.

## Le désactiver

```yaml
phil_highlight:
    ujo: false
```

Les blocs de code sont alors du texte échappé avec leur classe `language-…`, tels que `phil/ujo` les écrit. Sans
`phil/ujo-bundle`, le coloriseur de code est retiré à la compilation du conteneur, et rien n'est décoré.
