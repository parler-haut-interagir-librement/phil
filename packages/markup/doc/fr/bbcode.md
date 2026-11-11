<!-- phil-dev source-sha256=aa731b1d108116436fb347df8d42a139ff2ef8c804513f050f0a6d2984af3eb1 -->
# BBCode

`Reader\BBCode\BBCodeReader` lit du BBCode et en fait un document UJO, dont `phil/ujo` produit le rendu HTML. Il lit
les balises des forums, et des extensions écrites dans le même style qui donnent au BBCode toutes les fonctionnalités
du lecteur Markdown : notes de bas de page, listes de définitions, table des matières, attributs, encadrés, colonnes,
sauts de page, blocs de statistiques et les autres blocs des pages imprimées. [Formats](formats.md) place chaque
balise à côté de la syntaxe des autres formats.

```php
$reading = new BBCodeReader()->read("[h2]Rules[/h2]\n[quote=Ada]Roll [b]two[/b] dice.[/quote]");

$reading->document->blocks[0];   // a `header` block, level 2, its anchor `rules`
$reading->document->blocks[1];   // a `quote` block, its caption `Ada`
```

`new BBCodeReader(true)` garde le HTML brut ; `DefaultCompiler` le demande au seul niveau `all`. Un deuxième argument
remplace le normaliseur de `phil/ujo` qui vérifie le document.

## Balises

Une balise s'écrit `[name]`, `[name=option]`, `[name="option" key=value flag]` ou `[name key=value flag]`, son nom en
majuscules ou en minuscules. Une option sans guillemets s'étend jusqu'au premier ` key=`. Une valeur d'attribut qui
contient des espaces se met entre guillemets.

### Texte

| Balise | UJO |
|---|---|
| `[b]`, `[i]`, `[u]`, `[s]`, `[sup]`, `[sub]` | `<b>`, `<i>`, `<u>`, `<s>`, `<sup>`, `<sub>` |
| `[mark]`, `[highlight]` | `<mark>` |
| `[icode]code[/icode]` | `<code>`, son contenu tel qu'écrit |
| `[url]https://…[/url]`, `[url=https://…]text[/url]` | `<a href>` ; `www.…` prend `https://` ; un schéma que permet `phil/ujo` (`http`, `https`, `mailto`, `tel`, `sms`, `geo`, les messageries) ou un chemin qui commence par `/`, sinon le texte seul |
| `[email]a@b.ca[/email]`, `[email=a@b.ca]text[/email]` | un lien `mailto:` vers une adresse valide, sinon le texte seul |
| une adresse dans le texte, `https://…`, `www.…` | `<a href>`, sauf dans un lien |
| `[color=red]`, `[color=#ff0000]` | `<span class="text-color" data-color="red">`, une couleur hexadécimale sans son `#`, que l'apparence déclare ; toute autre valeur garde le texte seul |
| `[size=…]`, `[font=…]` | le texte seul : UJO n'a ni taille ni police |
| `[spoiler]text[/spoiler]` | `<span class="spoiler">` |
| `[footnote]text[/footnote]` | `<sup data-footnote>` là où elle est écrite, et un bloc `footnote` à la fin, numéroté dans l'ordre des références |
| `[nbsp]` | U+00A0 |
| `[ruby=かんじ]漢字[/ruby]` | un ruby, son option étant la lecture ; son contenu seul sans option |
| `[icon=fas_fa-dragon]`, `[icon=set:name]` | `<i data-icon="set:name">` |
| `[hspace=10px]` | `<span data-hspace>`, une espace horizontale |
| `[var=name]value[/var]`, `[var]name[/var]` | définit une variable n'importe où dans la source ; écrit sa valeur, ou la balise telle qu'écrite si aucune n'est définie |

Les couleurs, les divulgâcheurs (*spoilers*) et les icônes atteignent la page dans la mesure où le niveau les laisse
passer : `basic` et `forum` gardent leur texte.

### Blocs

| Balise | UJO |
|---|---|
| du texte, une ligne vide entre les paragraphes | `paragraph` ; une autre nouvelle ligne est un saut de ligne |
| `[h1]`…`[h6]` | `header`, son ancre générée, ou `id` |
| `[quote]`, `[quote=Ada]`, `[quote="Ada Lovelace"]` | `quote`, l'auteur comme légende |
| `[code]`, `[code=php]` | `code`, son langage ; le contenu est gardé tel qu'écrit |
| `[hr]` | `delimiter` |
| `[list]`, `[*]` | `list`, non ordonnée ; les blocs d'un élément se replient dans son contenu, une liste qu'il contient devient ses éléments imbriqués |
| `[list=1]`, `[list=3]`, `[list=a]`, `[list=A]`, `[list=i]`, `[list=I]` | `list`, ordonnée, à partir du nombre donné, ou comptée en lettres ou en chiffres romains |
| `[list=check]`, `[*=x]` | `list`, style `checklist`, les éléments `[*=x]` cochés |
| `[table]`, `[tr]`, `[td]`, `[th]` | `table`, ses lignes allongées jusqu'à la plus longue, 64 cellules au plus ; une première ligne faite seulement de `[th]` donne ses en-têtes, un `[th]` ailleurs une cellule d'en-tête ; `[td colspan=2 rowspan=2 align=center]` gardé dans la forme riche |
| `[dl]`, `[dt]`, `[dd]` | `definitionList` |
| `[img]https://…[/img]`, `[img=text]`, `[img alt="…"]` | `image`, le texte comme texte de remplacement, sans légende : le BBCode n'en a pas ; un paragraphe est coupé autour ; une adresse qui n'est pas celle d'une image garde la balise telle qu'écrite ; dans un lien, son texte |
| `[center]`, `[left]`, `[right]`, `[justify]`, `[align=center]` | l'ajustement `alignment` sur chaque bloc à l'intérieur |
| `[spoiler=Title]`, ou un `[spoiler]` qui contient des blocs | `toggle`, fermé |
| `[toc]`, `[toc=Contents depth=4 wide]` | `toc`, son titre, sa profondeur, sur toute la largeur des colonnes |
| `[metadata]YAML[/metadata]`, `[style]CSS[/style]` | `Reading::$metadata`, le style sous `style` |
| `[html]…[/html]` | `raw` quand le HTML brut est gardé, son texte échappé sinon |

### Attributs

Chaque balise de bloc prend `id` (l'ajustement `anchor`), `align` (l'ajustement `alignment`), `width`, `height` et
`wrap=left` ou `wrap=right` (l'ajustement `layout`), et `class`, une liste de classes de l'apparence qui enveloppe le
bloc dans un `container` là où l'imbrication le permet, comme le font les attributs du lecteur Markdown :

```text
[h2 id=intro class="lead wide" align=center]Introduction[/h2]
[img=Map width=300px wrap=right]https://example.com/map.png[/img]
```

### Pages et livres de jeu

| Balise | UJO |
|---|---|
| `[pagebreak]`, `[pagebreak=frontCover]`, `[pagebreak=myPage]` | `pageBreak`, et le `pageStyle` de la page qu'il ouvre : sa couverture, ses classes |
| `[columnbreak]` | `columnBreak` |
| `[columns][column]…[column]…[/columns]` | `columns`, six au plus, les blocs des autres joints à la sixième |
| `[columns=3]…[/columns]` | `columnFlow`, des blocs qui s'écoulent sur 2 à 6 colonnes |
| `[wide]`, ou l'indicateur `wide` sur un encadré | `wide`, sur toute la largeur des colonnes |
| `[note=Title]`, `[descriptive]` | `note`, `descriptive` ; sans titre, un premier titre à l'intérieur sert de titre |
| `[warning=Title]` | `warning` |
| `[alert=danger]` | `alert`, des genres `info` (par défaut), `success`, `danger`, `primary`, `secondary`, `light`, `dark` |
| `[statblock]`, `[statblock=spell]`, `[statblock=item]`, `[monster]`, indicateurs `frame` et `wide` | `statblock` : le premier titre est son nom, `[hr]` ou un titre une nouvelle section, `[dl]` ses propriétés, un tableau de deux lignes de valeurs (`10 (+0)`) ses caractéristiques ; `name` et `subtitle` peuvent être donnés comme attributs |
| `[credit=Title]`, `[artist]` | `credit` |
| `[classtable frame decoration]` | ses tableaux marqués de la variante `classTable` |
| `[block=class,class]` | n'importe quel genre des blocs `{{ }}` du [Markdown](markdown.md#blocs-entre-accolades) (`note`, `monster`, `frontCover`, `spellList`…) ou un `container` des classes de l'apparence |
| `[spacer]`, `[spacer=3]` | `spacer`, de 1 à 50 lignes |
| `[pagenumber]`, `[pagenumber=12]`, `[pagenumber=skip]`, `[pagenumber=reset]` | `pageNumber` : compté, écrit, exclu du compte, compte recommencé |
| `[footer]text[/footer]` | `runningFooter` |
| `[watermark]`, `[banner]` | `watermark`, `banner` |
| `[decoration=12 color=#aabbcc kind=ornament]` | `decoration` |

## Une source mal formée

Tout texte est un document : le lecteur ne lance jamais d'exception.

- Une balise que le lecteur ne connaît pas, ou écrite sur deux lignes, reste telle qu'écrite.
- Une balise laissée ouverte est fermée à la fin de la source. Une balise fermante ferme la balise ouverte la plus
  proche de son nom et les balises ouvertes à l'intérieur ; une balise fermante que rien n'a ouverte reste telle
  qu'écrite.
- Un élément, une ligne, une cellule, une colonne, un terme ou une définition ferme celui de son genre laissé ouvert
  dans le même parent : `[list][*]a[*]b[/list]`. Hors de son parent, il reste tel qu'écrit.
- Le texte et les balises entre les éléments d'une liste, les lignes d'un tableau, les cellules d'une ligne ou les
  colonnes de `[columns]` forment un élément, une ligne, une cellule ou une colonne à part ; le texte vide entre eux
  est laissé de côté.
- Une balise en ligne qui contient un bloc est laissée de côté, son contenu gardé ; un `[spoiler]` qui contient des
  blocs devient un `toggle`.
- Un titre, une citation, un élément de liste, une cellule, un terme, une définition et une note de bas de page
  replient les blocs qu'ils contiennent dans leur contenu en ligne ; un bloc sans contenu en ligne (un encadré, des
  colonnes) y est laissé de côté, ses paragraphes gardés.
- Les balises imbriquées sur plus de 32 niveaux restent telles qu'écrites. Les encadrés et les autres conteneurs de
  blocs s'imbriquent aussi profondément que le permet UJO ; un conteneur plus profond est laissé de côté, ses blocs
  gardés.
- Le contenu de `[code]`, `[icode]`, `[img]`, `[html]`, `[var]`, `[metadata]` et `[style]` s'étend jusqu'à sa balise
  fermante, en majuscules ou en minuscules, ou jusqu'à la fin de la source.

## Temps linéaire

Un membre qui écrit un message peut écrire une source hostile. Le lecteur prend un temps proportionnel à la longueur de
la source, quelle que soit sa forme :

- le curseur de [l'analyseur](analyser.md) lit chaque octet une seule fois ; un crochet qui n'ouvre aucune balise est
  du texte, et la recherche de son `]` s'arrête au `[` suivant ;
- le lecteur garde, pour chaque nom de balise, les positions où ses balises sont ouvertes : une balise fermante trouve
  sa balise ouvrante, ou apprend qu'il n'y en a pas, sans parcourir la pile, et chaque balise est ouverte et fermée une
  seule fois ;
- la recherche de la balise fermante de `[code]` et des autres consomme ce qu'elle dépasse ;
- les titres au même texte, les notes de bas de page et les images prennent chacun un temps constant.

`BBCodeReaderTest` lit des sources de 100 Ko écrites pour être lentes, et `BBCodeBench` les lit à 25, 50 et 100 Ko :
le temps double avec la taille.
