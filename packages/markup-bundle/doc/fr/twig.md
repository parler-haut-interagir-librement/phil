<!-- phil-dev source-sha256=64171ff736e53febff891c03d28ad8959bb99cdcd6aec44e92c9378104955afc -->
# Twig

| Filtre | Arguments | Donne |
|---|---|---|
| `markup_html` | le format (`default_format` par défaut), le niveau (`default_level` par défaut) | la source compilée et purifiée |
| `markup_purify` | le niveau (`default_level` par défaut) | du HTML stocké, ou le HTML d'un `MarkedUpText`, purifié de nouveau |
| `markup_ujo` | le format, le niveau, avec les mêmes valeurs par défaut | le JSON UJO de la source |

```twig
{{ comment.source|markup_html('text', 'basic') }}
{{ message.source|markup_html('bbcode', 'forum') }}
{{ article.body|markup_purify('all') }}
<div data-blocks="{{ draft.source|markup_ujo(draft.format.value) }}"></div>
```

`markup_html` et `markup_purify` sont sûrs pour le HTML : Twig n'échappe pas ce qu'ils donnent. `markup_ujo` donne du
JSON, que Twig échappe comme tout texte, pour un attribut ou un script. Un `null` donne une chaîne vide, ou un document
sans blocs pour `markup_ujo`.

Le niveau demandé est abaissé au plafond du format que fixe la [configuration](configuration.md) dans `levels` : avec
`bbcode: forum`, `markup_html('bbcode', 'all')` purifie à `forum`. `markup_purify` applique le plafond du format d'un
`MarkedUpText`, activé ou non, et aucun plafond à du HTML simple, dont il ne connaît pas le format. Un format non activé
lance `UnavailableFormat`, un niveau inconnu une `ValueError`, une source UJO invalide `InvalidSource` : voir
[Exceptions](exceptions.md).

## Ce que coûte un rendu

`markup_html` compile la source et purifie le résultat à chaque rendu, et ne met rien en cache : environ 4 ms pour dix
paragraphes de Markdown, de sorte qu'un fil de 50 messages passe quelque 200 ms dans le filtre. `markup_purify` ne fait
que purifier, soit environ sept fois moins pour le même message Markdown. `markup_ujo` lit la source et écrit son JSON,
soit environ les deux tiers de `markup_html`. Une page qui liste de nombreux textes compile chacun une seule fois, quand
il est écrit : stockez sur l'entité le `MarkedUpText` que donne `Markup::text()` (un objet intégrable qui réunit sa
source, son format et son HTML), et affichez-le avec `markup_purify`, qui le purifie de nouveau au niveau demandé, de
sorte qu'une politique plus stricte s'applique encore à ce qui a été stocké. Gardez `markup_html` pour un aperçu, ou un
texte affiché une seule fois, et `markup_ujo` pour le moment où un texte est ouvert dans un éditeur par blocs.
