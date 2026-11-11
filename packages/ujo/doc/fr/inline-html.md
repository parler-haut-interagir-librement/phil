<!-- phil-dev source-sha256=3c713fa0612e0e37be80d7bd206152d31d09a1fa1cf81168ff49ac5e8c04a82f -->
# HTML en ligne

UJO (Universal JSON Output) garde la mise en forme en ligne de ses textes en HTML : `Hello <b>world</b>`.
L'assainisseur propre à un éditeur par blocs s'exécute dans le navigateur du rédacteur, ce qu'une requête peut
contourner ; phil/ujo assainit donc de nouveau chaque valeur HTML sur le serveur, champ par champ, quand un document est
normalisé et encore quand son rendu est produit.

## L'assainisseur

`InlineSanitizer::sanitize($html, $profile)` lit le fragment jeton par jeton (texte, balises ouvrantes avec leurs
attributs, balises fermantes, commentaires) et ne réécrit que ce que le profil permet, sous une seule forme canonique :

- le texte est décodé, puis échappé de nouveau : `&eacute;` devient `é`, `<` devient `&lt;` ;
- un élément qu'aucun outil du profil n'écrit est déballé : son texte reste ;
- `script`, `style`, `iframe`, `object`, `embed`, `template`, `noscript`, `textarea`, `select`, `svg`, `math`, `title`,
  `head`, `frameset`, `frame`, `applet`, `audio`, `video`, `canvas`, `form` et `annotation-xml` sont retirés avec leur
  contenu, sauf si le profil les lit : `rich` et `mathml` lisent `math` ;
- un outil gardé seulement `within` d'autres éléments est déballé ailleurs : le `<rt>` d'un ruby hors d'un `<ruby>`, un
  élément de MathML hors d'un `<math>` ;
- les commentaires sont retirés ;
- les attributs qu'un outil ne nomme pas sont retirés, ceux qu'il nomme sont nettoyés, et un élément auquel il manque
  un attribut obligatoire est déballé ;
- les éléments sont fermés dans l'ordre : une balise fermante isolée est ignorée, un élément laissé ouvert est fermé,
  et un lien n'est jamais imbriqué dans un lien.

`InlineSanitizer::toText($html)` donne le texte décodé sans éléments, un `<br>` devenant un saut de ligne.

## Profils

| Profil | Garde |
|---|---|
| `rich` | les outils en ligne ci-dessous ; le profil de chaque valeur HTML des types |
| `plain` | rien d'autre que du texte |
| `mathml` | une formule de MathML Core : `<math>` et les éléments qu'il contient, le champ `mathml` d'un bloc `math` |
| `raw` | les outils en ligne, et les éléments de la structure d'une page : paragraphes, titres, listes, citations, code, tableaux avec `colspan` et `rowspan`, figures, images avec `src`, `alt`, `width` et `height`, listes de définitions, sections, détails ; aucune classe, aucun style ni aucun événement nulle part |

## Outils en ligne de `rich`

| Outil | Lu à partir de | Écrit comme |
|---|---|---|
| gras | `b`, `strong` | `<b>` |
| icône | `i[data-icon]`, une référence `set:name` | `<i data-icon="fa:dragon">` |
| italique | `i`, `em` | `<i>` |
| souligné | `u` | `<u class="cdx-underline">` |
| barré | `s`, `strike`, `del` | `<s class="cdx-strikethrough">` |
| surligneur | `mark` | `<mark class="cdx-marker">` |
| code en ligne | `code`, `span.inline-code` | `<code class="inline-code">` |
| mention | `a[data-mention]`, une référence `kind:id`, `href` facultatif | `<a href data-mention="user:42">` |
| lien wiki | `a[data-ref]`, une référence, `href` facultatif | `<a href data-ref="page:7">` |
| lien | `a` avec un `href` en `http`, `https`, `mailto`, `tel`, `sms`, `geo`, `xmpp`, `matrix`, `viber` ou relatif (un `tel` ou un `sms` étant un numéro, un `geo` des coordonnées) ; `target="_blank"` ; `rel` parmi `nofollow`, `noopener`, `noreferrer`, `ugc`, `sponsored`, `external` | `<a href target rel>` |
| appel de note | `sup[data-footnote]` | `<sup data-footnote="fn1">` |
| exposant, indice | `sup`, `sub` | `<sup>`, `<sub>` |
| saut de ligne | `br` | `<br>` |
| couleur du texte | `span.text-color` avec `data-color`, un jeton de l'apparence | `<span class="text-color" data-color="accent">` |
| infobulle | `span.cdx-tooltip` avec `data-tooltip` | `<span class="cdx-tooltip" data-tooltip>` |
| annotation | `span.cdx-annotation` avec `data-title`, `data-text` | `<span class="cdx-annotation" data-title data-text>` |
| divulgâcheur | `span.spoiler` | `<span class="spoiler">` |
| espace horizontale | `span[data-hspace]`, une longueur CSS | `<span data-hspace="100px">` |
| abréviation | `abbr` avec son `title` | `<abbr title="Game master">` |
| langue | `span[lang]`, `span[dir]`, `bdi`, `bdo` ; `lang` une étiquette BCP 47, `dir` `ltr`, `rtl` ou `auto` ; au moins l'un des deux | `<span lang="en" dir="ltr">` |
| ruby | `ruby`, ses lectures `rt` et leur solution de repli `rp` à l'intérieur | `<ruby>漢字<rp>(</rp><rt>かんじ</rt><rp>)</rp></ruby>` |
| mathématiques en ligne | `math` avec `display`, et à l'intérieur les éléments de MathML Core (`mrow`, `mi`, `mn`, `mo`, `mtext`, `ms`, `mfrac`, `msqrt`, `mroot`, `msub`, `msup`, `msubsup`, `munder`, `mover`, `munderover`, `mmultiscripts`, `mprescripts`, `none`, `mtable`, `mtr`, `mtd`, `mspace`, `mpadded`, `mphantom`, `mstyle`, `merror`, `semantics`, `annotation`), avec leurs propres attributs, mots-clés et longueurs seulement | `<math><mi>x</mi></math>` |

Les couleurs libres (`<font color>`, `style="color:…"`) sont déballées : une couleur est un jeton que définit
l'apparence.

## Ajouter un profil ou un outil

```php
$profile = InlineProfile::rich()
    ->without('marker')
    ->with(new InlineTool('keyboard', 'kbd', ['kbd']));

$sanitizer = new InlineSanitizer(['rich' => $profile, 'caption' => InlineProfile::plain()]);
```

Un `InlineTool` a un nom, la balise qu'il écrit, les motifs qu'il lit (`span`, `span.class`, `a[attribute]`), une
classe facultative qu'il écrit, les attributs qu'il garde avec un `AttributeValue` chacun (`Link`, `Media`, `Token`,
`Reference`, `Text`, `Target`, `Rel`, `Length`, `Number`), et les attributs qu'il exige. Un profil essaie ses outils
dans l'ordre : un motif plus étroit passe avant un motif plus large. Donnez l'assainisseur à la fois à `Normalizer` et
à `HtmlRenderer`.

## Pourquoi un assainisseur propre

`phil/markup` purifie des documents entiers à un niveau (`basic`, `forum`, `all`) par `symfony/html-sanitizer`. UJO a
besoin de moins et de plus : une règle par champ plutôt que par document, les classes et les attributs de données des
outils en ligne d'Editor.js gardés exactement, et une forme canonique pour qu'un document stocké soit égal à lui-même.
De plus, les lecteurs de `phil/markup` convertissent d'autres formats en UJO : phil/ujo ne peut donc pas en dépendre.
Une application qui veut une seconde passe peut donner le HTML produit au purificateur de `phil/markup` au niveau
`all` ; avec les profils par défaut, il ne retire rien.
