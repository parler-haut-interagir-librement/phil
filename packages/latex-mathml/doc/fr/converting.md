<!-- phil-dev source-sha256=3cbe5b8adbe0e30cbc752725f2dae6bf9ebb00accbc0802a62aeb21b164520e5 -->
# Conversion

```php
new LatexToMathMl(int $maxLength = 20_000, int $maxDepth = 64, int $maxExpansions = 1_000)
    ->convert(string $latex, bool $display = true): string
```

Le résultat est un seul élément `<math>`, avec `display="block"` pour une formule sur sa propre ligne. Il contient un
`<semantics>` dont le premier enfant est la formule et dont l'`<annotation encoding="application/x-tex">` est le LaTeX.
Les délimiteurs `$…$`, `$$…$$`, `\(…\)` et `\[…\]` autour de toute la formule sont laissés de côté ; les espaces ne
comptent pas hors de `\text` ; `%` commence un commentaire jusqu'à la fin de la ligne.

## Sortie

Seuls des éléments de MathML Core sont produits (`mi`, `mn`, `mo`, `mtext`, `mspace`, `mrow`, `msub`, `msup`,
`msubsup`, `munder`, `mover`, `munderover`, `mmultiscripts`, `mprescripts`, `mfrac`, `msqrt`, `mroot`, `mstyle`,
`mtable`, `mtr`, `mtd`, `semantics`, `annotation`), avec les attributs qu'écrit le convertisseur, et chaque texte est
échappé ; le seul élément HTML est le `<a>` d'un lien, dans `<mtext>`, dont l'adresse, en `http`, `https` ou `mailto`,
est vérifiée par `phil/url`. Les couleurs sont validées avant d'être écrites en `mathcolor`. La sortie peut être écrite
sans risque dans une page.

Les boîtes et les filets des environnements sont dessinés par des attributs `style`. Une politique de sécurité de
contenu qui interdit les styles en ligne sans `'unsafe-inline'` dans `style-src-attr` les laisse sans dessin ; la
formule elle-même est dessinée.

Les lettres grasses, ajourées, de ronde, gothiques, sans empattement et à chasse fixe sont écrites avec les caractères
des Mathematical Alphanumeric Symbols d'Unicode, que MathML Core dessine à la place de `mathvariant` ; `\mathrm` écrit
`mathvariant="normal"`.

## Limites

| Limite | Valeur par défaut |
|---|---|
| Longueur d'une formule | 20 000 octets |
| Profondeur des groupes, arguments, environnements et macros | 64 |
| Cellules d'un environnement | 2 500 |
| Utilisations de macros développées | 1 000 |
| Jetons de la formule une fois ses macros développées | autant que ses octets, 20 000 |

Les macros sont développées en premier, dans ces limites, pour qu'une macro qui double son argument à chaque niveau
ne puisse pas allonger une formule courte. La formule développée est ensuite lue une seule fois, chaque jeton une fois :
le travail et la sortie y sont linéaires.

## Refus

`InvalidLatex` refuse une formule avec un message pour son auteur et l'octet où le problème a été trouvé, dans
`$offset` : une commande hors de l'ensemble pris en charge, un groupe ou un environnement resté ouvert, un environnement
fermé sous un autre nom, deux exposants sur un même atome, une commande sans son argument, `&` ou `\\` hors d'un
environnement, un `#` ou un `$` dans la formule, un texte qui n'est pas en UTF-8, une macro utilisée dans sa propre expansion, une couleur ou une adresse qui n'en est
pas une, deux `\tag` sur une formule ou une ligne, un caractère que la notation chimique ne lit pas, et une formule qui
dépasse une limite. Un problème dans l'expansion d'une macro est signalé à l'utilisation de la macro.

## Où les formules sont dessinées

Les navigateurs dessinent MathML Core : Chromium depuis la version 109, Firefox et Safari depuis plus longtemps. Les
lecteurs EPUB 3 dessinent MathML. WeasyPrint ne dessine pas MathML : un PDF produit par WeasyPrint a besoin de la
formule sous une autre forme, comme une image ; un PDF produit par Chromium, comme celui de Gotenberg, la dessine.
