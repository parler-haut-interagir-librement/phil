<!-- phil-dev source-sha256=7a4c7c1e7b3b8b90a95d4c9faa09a24f64d418aaa7159d15962b15cf72a3ffd7 -->
# Markdown

`Reader\Markdown\MarkdownReader` lit du Markdown et en fait un document UJO. Il ne lance jamais d'exception : une
construction mal formée reste du texte. Le document est normalisé par `phil/ujo` avant d'être retourné ; son HTML en
ligne est donc dans la forme canonique des outils en ligne : `<b>`, `<i>`, `<s class="cdx-strikethrough">`,
`<code class="inline-code">`.

```php
$reader = new MarkdownReader();                  // raw HTML escaped
$reader = new MarkdownReader(rawHtml: true);     // raw HTML kept, for a purifier at the level `all`
$reading = $reader->read($source);               // Reading: document and metadata
```

Un deuxième argument remplace le `Normalizer` ; celui par défaut enregistre chaque type de `phil/ujo` et `raw`. Un
troisième donne un `MarkdownDialect`, la syntaxe qu'un dialecte ajoute ou lit autrement : voir
[Points d'extension](extension-points.md#dialectes-de-markdown).

## Blocs

| Markdown | UJO |
|---|---|
| Un paragraphe | `paragraph` ; coupé autour des images qu'il contient, chacune un `image` |
| `# Heading`, `Heading` au-dessus de `===` ou `---` | `header`, niveau 1 à 6, avec un ajustement `anchor` |
| `- item`, `+ item`, `* item`, `1. item`, `1) item` | `list`, `unordered` ou `ordered` avec son `start` ; les listes imbriquées sont des éléments imbriqués |
| `- [ ] task`, `- [x] done`, sur chaque élément | `list`, style `checklist`, chaque élément `checked` |
| `> quote` | `quote`, ses blocs repliés dans son texte |
| Clôture ```` ``` ```` ou `~~~`, indentation de quatre espaces | `code`, le premier mot de la clôture comme `language` |
| `***`, `---`, `___` | `delimiter` |
| Un tableau de GitHub | `table`, `withHeadings` ; l'alignement des colonnes n'est pas gardé |
| `Term` puis `: Definition` | `definitionList` ; des listes consécutives n'en font qu'une |
| `[^label]: text` | un `footnote` à la fin du document, numéroté selon la première référence |
| `[label]: address "title"` | une référence pour les liens et les images, son titre servant de légende à une image ; aucun bloc |
| `[TOC]` seul sur sa ligne | `toc` |
| `::: note Title`, `::: warning`, `::: info`, `::: wide`, `::: {.class #id}` … `:::` | `note`, `warning`, `alert`, `wide`, `container` (voir plus bas) |
| `{{note …}}` et les autres blocs `{{ }}`, un segment seul sur sa ligne | voir [Blocs entre accolades](#blocs-entre-accolades) |
| `\page`, `\pagebreak`, `\column`, `\columnbreak` | `pageBreak`, `columnBreak` ; voir [Pages et colonnes](#pages-et-colonnes) |
| Bloc HTML | `raw`, ou un `paragraph` de texte échappé |
| Préambule, du YAML entre deux lignes `---` au début | `Reading::$metadata` |

Un conteneur (une citation, un élément de liste, une note de bas de page) ne contient que du contenu en ligne en UJO :
ses paragraphes sont joints par des sauts de ligne, un bloc de code devient du code en ligne, un titre son texte. Les
conteneurs s'imbriquent sur cinq niveaux ; plus profond, leurs marqueurs sont du texte.

L'ancre d'un titre est le `{#id}` écrit après lui, ou un *slug* de son texte comme le produit GitHub (en minuscules,
sans les accents des lettres latines, les autres suites de caractères changées en trait d'union), unique dans le
document : un deuxième titre au même texte prend `-1`.

## Éléments en ligne

| Markdown | UJO |
|---|---|
| `*i*`, `_i_`, `**b**`, `__b__` | `<i>`, `<b>`, appariés comme CommonMark les apparie |
| `~s~`, `~~s~~` | `<s>` |
| `==text==` | `<mark>` |
| `{漢字\|かんじ}` | un ruby : la base et sa lecture, chacune jusqu'à 100 caractères sans accolade ni barre |
| `` `code` `` | `<code>` |
| `[text](address "title")`, `[text][label]`, `[text][]`, `[label]` | `<a href>` ; le titre est laissé de côté |
| `<https://…>`, `<name@example.com>`, `https://…`, `www.…` | `<a href>` ; les adresses `www.` prennent `https://` |
| `![alt](address "caption")`, `![alt][label]` | un bloc `image` dans un paragraphe, son texte comme texte de remplacement et son titre (`"…"`, `'…'` ou `(…)`) comme légende, sans légende s'il n'a pas de titre ; `<a href>` ailleurs ; dans un lien, son texte de remplacement, ou sa légende à défaut |
| `[^label]` | `<sup data-footnote="fn-1"><a href="#footnote-fn-1">1</a></sup>` |
| `\*`, `&copy;`, `&#65;` | le caractère |
| Deux espaces ou `\` à la fin d'une ligne | `<br>` |
| HTML en ligne | gardé pour l'assainisseur de `phil/ujo`, ou échappé |

## Attributs

Des accolades après un titre, `# Title {#id .class}`, ou seules sur la ligne juste sous un bloc, `{.class #id}` (ou,
dans la grammaire des blocs `{{ }}`, `{class,#id,property:value}`), s'appliquent à ce bloc :

| Attribut | UJO |
|---|---|
| `#id` | l'ajustement `anchor` |
| `text-align:left`, `center`, `right`, `justify` | l'ajustement `alignment` |
| `position`, `top`, `right`, `bottom`, `left`, `width`, `height`, `z-index` ; les classes `wrapLeft` et `wrapRight` | l'ajustement `layout` |
| toute autre classe | un `container` autour du bloc, pour une apparence qui déclare la classe |
| `key=value`, autres propriétés | laissés de côté |

Des accolades juste après une image, `![a](a.png){width:200px,wrapLeft}`, s'appliquent à l'image.

## HTML brut

Le HTML brut est gardé ou échappé, selon la construction du lecteur. `DefaultCompiler` le garde pour le niveau `all`,
où écrit un rédacteur et où l'assainisseur garde les éléments sûrs, et l'échappe en dessous, où il ne serait que
retiré : le `<script>` d'un membre se lit comme du texte au lieu de disparaître. Un bloc HTML gardé est un bloc `raw`,
assaini par `phil/ujo` puis assaini au niveau voulu ; un commentaire HTML seul est retiré. Le HTML en ligne est assaini
avec les outils en ligne de `phil/ujo`.

## Blocs `:::`

`::: name Title` ouvre un bloc fermé par une ligne `:::` ; les blocs s'imbriquent. Le nom est une classe, associée
comme les classes des blocs `{{ }}` : `note` et `descriptive` prennent le titre, ou le titre de section par lequel ils
commencent ; `warning` produit un `warning` ; `info`, `success`, `danger`, `primary`, `secondary`, `light` et `dark`
un `alert` de ce type ; `wide` un bloc `wide` ; toute autre classe un `container`. `::: {.class #id}` prend plutôt des
attributs.

## Blocs entre accolades

Une ligne `{{tags` ouvre un bloc, une ligne `}}` le ferme, et les blocs s'imbriquent. Les étiquettes sont séparées par
des virgules : un mot est une classe, `#id` l'identifiant, `property:value` un style. La première classe que connaît
UJO décide du type :

| Classe | UJO |
|---|---|
| `note`, `descriptive` | `note`, `descriptive` ; le titre de section par lequel il commence est son titre |
| `warning` ; `info`, `success`, `danger`, `primary`, `secondary`, `light`, `dark` | `warning` ; un `alert` de ce type |
| `monster` (`frame`, `wide`) | `statblock` (voir plus bas) |
| `statblock` avec `spell` ou `item` | `statblock` de ce genre |
| `toc` (`wide`, `tocDepthH4` à `tocDepthH6`) | `toc`, son titre étant le titre de section ; la liste écrite est retirée, la table étant générée |
| `quote` avec une ligne `{{attribution …}}` | `quote`, l'attribution comme légende |
| `artist` | `credit`, le titre de section comme titre |
| `classTable` (`frame`, `decoration`) | ses tableaux avec l'ajustement `variant` |
| `spellList` | `columnFlow` de deux colonnes |
| `frontCover`, `insideCover`, `partCover`, `backCover` | `pageStyle` avec cette variante, avant les blocs |
| `wide` | `wide` ; avec une autre classe, autour de ce bloc |
| Toute autre | `container` avec ses classes, pour une apparence qui les déclare |

L'identifiant devient l'ajustement `anchor`, et `text-align`, `position`, les décalages et la taille deviennent les
ajustements des [attributs](#attributs). Des accolades sur la ligne sous n'importe quel bloc,
`{color:purple,text-align:center}`, s'appliquent de la même façon ; une propriété CSS pour laquelle UJO n'a pas de
place est laissée de côté.

Un segment seul sur sa ligne, `{{tags content}}`, peut être un bloc de la page ; tout autre reste du texte :

| Segment | UJO |
|---|---|
| `{{pageNumber 1}}` | `pageNumber`, `fixed`, valeur `1` |
| `{{pageNumber,auto}}`, `{{pageNumber}}` | `pageNumber`, `auto` |
| `{{skipCounting}}`, `{{resetCounting}}` | `pageNumber`, `skip` ou `reset` |
| `{{footnote PART 1 \| TITLE}}` | `runningFooter` : le libellé au pied des pages, pas une note de bas de page |
| `{{watermark text}}`, `{{banner text}}` | `watermark`, `banner` |
| `{{frontCover}}` et les autres couvertures | `pageStyle` |
| `{{watercolor8,background-color:#BBAD82}}` | `decoration`, `stain`, variante 8, sa couleur |

Les segments s'imbriquent sur 8 niveaux dans leur ligne.

## Pages et colonnes

| Markdown | UJO |
|---|---|
| `\page`, `\pagebreak` | `pageBreak` |
| `\page {frontCover,class}` | `pageBreak`, puis `pageStyle` avec la couverture comme `variant` et les classes |
| `\column`, `\columnbreak` | `columnBreak` |

## Blocs de statistiques

```markdown
{{monster,frame
## Gibbering Duck
*Small beast, chaotic gossipy*
___
**Armor Class**
: 15 (chain mail, shield)

___
|  STR  |  DEX  |
|:-----:|:-----:|
|10 (+0)|14 (+2)|
___
***Pack Tactics.*** These guys work together.
### Actions
***Bite.*** *Melee Weapon Attack:* +4 to hit.
}}
```

Le premier titre est le `name`, une ligne en italique juste après lui le `subtitle`. Un filet `___` ou un titre commence
une section, le titre étant son `title`. Les définitions sont les `properties` de la section, le libellé sans son
deux-points ; un tableau de deux lignes dont la deuxième contient des valeurs (`10 (+0)`, `14`) donne ses `abilities`,
chacune un libellé, une valeur et un modificateur ; tout autre bloc reste dans les `blocks` de la section. `frame` et
`wide` activent les indicateurs du même nom.
