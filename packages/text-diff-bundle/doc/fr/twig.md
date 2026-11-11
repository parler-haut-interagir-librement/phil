<!-- phil-dev source-sha256=5251d394fe00ea8833fab15bdc0aae8a7bb3876f13d74a5b797ecdec93f3c580 -->
# Twig

Les deux filtres retournent du HTML marqué comme sûr, avec `<ins>` autour de ce qui a été inséré et `<del>` autour de
ce qui a été supprimé. Les éléments ne portent aucun attribut ; la [mise en forme](styling.md) les colore à l'intérieur
d'un élément doté de la classe `text-diff`, ou sous le contrôleur `comparison`.

## text_diff

```twig
{{ old|text_diff(new) }}              {# by the configured tokenizer #}
{{ old|text_diff(new, 'lines') }}     {# by words, lines, paragraphs or html #}
```

`null` d'un côté ou de l'autre vaut un texte vide. Un nom de découpeur autre que les quatre lève une
`InvalidArgumentException`.

**Le texte brut** — `words`, `lines`, `paragraphs` — est toujours échappé, marques comprises. Les sauts de ligne sont
conservés : affichez la différence dans un élément mis en forme avec `white-space: pre-wrap`, ou dans un `<pre>` :

```twig
<pre class="text-diff">{{ before.summary|text_diff(after.summary, 'lines') }}</pre>
```

**Le HTML** — `html` — est comparé par mots sans jamais couper une balise, et le résultat garde la structure du
nouveau HTML. Puisque ses balises sont écrites telles quelles, le filtre ne lit comme balisage que ce à quoi il peut
faire confiance :

| La valeur | Lue comme |
|---|---|
| Un `Twig\Markup` : la sortie d'une macro, un bloc capturé par `{% set %}…{% endset %}`, une valeur que l'application a enveloppée | Du balisage, tel quel |
| Toute autre valeur, quand `html_sanitizer` est configuré | Du balisage, une fois nettoyé par l'assainisseur |
| Toute autre valeur, sans assainisseur | Du texte : échappé d'abord, pour que ses balises soient affichées et jamais exécutées |

```twig
{% set before %}{{ revision.html|raw }}{% endset %}
{{ before|text_diff(current.html, 'html') }}
```

Ici, `before` est un `Markup` et il est digne de confiance ; `current.html` passe par l'assainisseur, ou est échappé.
Le filtre `raw` seul ne marque rien à l'exécution : il indique seulement à Twig de ne pas échapper ce qu'il imprime.

## text_diff_document

```twig
{{ previous.body|text_diff_document(current.body) }}
```

Chaque côté est un `Phil\Ujo\Document` ou son JSON. Les documents sont lus par le service `Ujo` de `phil/ujo-bundle`,
et chaque bloc est rendu par son `HtmlRenderer` — types de blocs, couleurs et filtres de l'application compris — ; sans
ce bundle, par les valeurs par défaut de `phil/ujo`. Les blocs sont appariés par identifiant à chaque niveau :

```html
<div class="text-diff-block text-diff-changed"><p class="ujo-paragraph">Hello <ins>big </ins>world</p></div>
<div class="text-diff-block text-diff-added"><ins><figure class="ujo-quote">…</figure></ins></div>
```

Chaque bloc de premier niveau est un `<div class="text-diff-block text-diff-<kind>">`, la sorte étant `unchanged`,
`changed`, `added`, `removed` ou `moved`. Le HTML rendu est celui du moteur de rendu, assaini comme il l'est toujours :
la comparaison lui fait donc confiance.
