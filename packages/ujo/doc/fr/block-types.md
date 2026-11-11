<!-- phil-dev source-sha256=c8de160e247265b02b967cde643d00ede6b0179a702e2f16a42012d137f6fdb9 -->
# Types de blocs

Chaque type de UJO (Universal JSON Output) est nommé au même niveau que ceux des outils d'Editor.js, en camel case,
sans préfixe. « Lit aussi » énumère les autres noms et les autres formes qu'un type accepte ; ils sont réécrits sous la
forme canonique. Les valeurs omises prennent la valeur par défaut indiquée, ou restent absentes quand il n'y en a pas.
Les valeurs HTML sont assainies avec le profil `rich`, sauf indication contraire ([inline-html.md](inline-html.md)) ;
les valeurs brutes sont gardées telles quelles et échappées au rendu.

## Outils officiels : `StandardTypes`

| Type | Données | Lit aussi |
|---|---|---|
| `paragraph` | `text` html | `alignment` dans les données, déplacé vers le réglage `alignment` |
| `header` | `text` html ; `level` de 1 à 6, 2 par défaut | `alignment` et `anchor` dans les données, déplacés vers leurs réglages |
| `list` | `style` `unordered`, `ordered` ou `checklist` ; `meta` `{start ≥ 0, counterType}` ; `items` `[{content html, meta {checked}, items […]}]` | la liste 1.x, avec des éléments en textes ; l'outil `checklist`, `{items: [{text, checked}]}` ; la liste de contrôle imbriquée, avec des éléments à `checked` |
| `quote` | `text` html ; `caption` html, `""` par défaut ; `alignment` `left` ou `center`, `left` par défaut | |
| `warning` | `title` html ; `message` html ; tous deux `""` par défaut | |
| `delimiter` | facultativement `style` `star`, `dash` ou `line` ; `lineWidth` de 1 à 100 ; `lineThickness` de 1 à 20 | |
| `code` | `code` brut ; facultativement `language`, jusqu'à 32 caractères parmi `A-Za-z0-9_+#.-` | `showlinenumbers` de CodeFlask, retiré |
| `raw` | `html`, assaini avec le profil `raw` | absent du registre par défaut |
| `table` | `withHeadings`, `stretched`, `false` par défaut ; `content`, des rangées de cellules, chaque rangée aussi large que la première ; dans la forme riche, une cellule est `{text html, colspan 1–100, rowspan 1–1 000, header, align left, center or right}`, une cellule qui s'étend sur plusieurs rangées couvre les colonnes sous elle, et le tableau a facultativement un `caption` html et `align`, l'alignement de chaque colonne, `""` pour aucun | |
| `image` | `file` `{url, …}`, qui garde ce que le serveur a ajouté ; `caption` html ; facultativement `alt`, brut, jusqu'à 1 000 caractères, le texte de remplacement quand il diffère de la légende ; `withBorder`, `withBackground`, `stretched`, `false` par défaut ; facultativement `unsplash` `{author, profileLink}` | le `url` de l'image simple, déplacé dans `file` |
| `linkTool` | `link`, `http` ou `https` ; `meta` `{title, site_name, description, image {url}}`, qui garde ce que le serveur a ajouté | `link` |
| `attaches` | `file` `{url, size, name, extension}`, qui garde ce que le serveur a ajouté ; `title` brut, `""` par défaut | |
| `embed` | `service` ; `source`, `http` ou `https` ; `embed`, `https` sur un hôte du service ; facultativement `width`, `height` de 1 à 10 000 ; `caption` html ; facultativement `poster`, `title` brut jusqu'à 200 caractères et `qrCode`, ce que le papier montre à la place du cadre | |
| `personality` | `name` brut ; `description` brut, `""` par défaut ; facultativement `link`, `http` ou `https`, et `photo` | |

Une adresse est en `http`, en `https` ou relative, jamais relative au protocole, sauf si le tableau dit autre chose.
Les services d'intégration sont ceux de l'outil d'intégration d'Editor.js, chacun avec les hôtes de ses cadres ;
`EmbedServices::with()` en ajoute un, et `StandardTypes::all($services)` ou `StandardTypes::embed($services)` prend le
résultat.

## Outils communautaires : `CommunityTypes`

| Type | Données | Lit aussi |
|---|---|---|
| `alert` | `type` `info`, `primary`, `secondary`, `success`, `warning`, `danger`, `light` ou `dark`, `info` par défaut ; `align` `left`, `center` ou `right`, `left` par défaut ; `text` html | `message`, comme `text` |
| `toggle` | `text` html ; `open`, `false` par défaut ; `blocks` | le bloc bascule, `{text, status, fk, items}`, et le bloc repliable `accordion`, `{title, settings {blockCount, defaultExpanded}}` : les `items` ou les `blockCount` blocs qui les suivent sont repliés à l'intérieur |
| `columns` | `cols`, de 1 à 6 `{blocks}` | les colonnes calumk, chacune un document entier `{time, blocks, version}` |
| `math` | `latex` brut, jusqu'à 20 000 caractères, ou `mathml`, un élément `<math>` de MathML Core gardé comme le permet le profil `mathml`, ou les deux ; facultativement `rendered` | le bloc LaTeX `Math`, `{math}` |
| `mermaid` | `code` brut, jusqu'à 20 000 caractères ; `caption` html ; facultativement `rendered` | |
| `chart` | une configuration Chart.js, gardée telle quelle ; `rendered` vérifié s'il est présent | |
| `button` | `link` ; `text` brut | `AnyButton` |
| `gallery` | `files`, au moins un `{url, caption}`, qui garde ce que le serveur a ajouté ; facultativement `source` ; `style` `fit` ou `slider`, `fit` par défaut | le carrousel, une liste de `{url, caption}`, comme un `slider` |

`rendered`, de `StoredMarkup::property()`, est le dessin qu'un serveur garde à côté de la source : `{"markup": svg or
mathml, "key": text}`, le balisage d'au plus 1 Mio, la clé de 1 à 256 lettres, chiffres et `._~-`. Le normaliseur ne
vérifie que sa forme : le balisage n'est écrit dans une page qu'une fois que `HtmlRenderer::withStoredMarkup()` a
prouvé sa clé ; voir [Rendu HTML](html-rendering.md#dessins-stockés). `phil/svg-blocks` l'écrit.

## Lecteurs : `MediaTypes`

| Type | Données |
|---|---|
| `video` | `sources`, de 1 à 8 `{url, type}` dans l'ordre où un lecteur les essaie, `type` un type de média comme `video/webm; codecs="vp9"` ; `tracks`, jusqu'à 16 `{url, kind, language, label, default}`, `kind` `captions`, `subtitles`, `descriptions`, `chapters` ou `metadata`, `subtitles` par défaut, `language` une étiquette BCP 47 ; `controls`, `true` par défaut ; `loop`, `muted`, `false` par défaut ; facultativement `poster`, `width`, `height` de 1 à 10 000 et `title` ; `caption` html ; facultativement `qrCode` |
| `audio` | la même chose, sans `poster`, `width` ni `height` |

Une source peut être un fichier de la médiathèque, par son adresse. Sur papier, un lecteur montre son affiche, son
titre et l'adresse de sa première source, et un code QR de cette adresse quand `qrCode` est vrai.

L'outil `layout` d'`editorjs-layout` n'est pas lu : ses conteneurs portent du CSS libre, qu'aucun type ici n'accepte.
Un site qui en a besoin enregistre son propre type.

## Types de page et de jeu : `LayoutTypes`

| Type | Données |
|---|---|
| `pageBreak`, `columnBreak` | aucune |
| `pageStyle` | facultativement `variant` `frontCover`, `insideCover`, `partCover` ou `backCover` ; `classes`, des noms, `[]` par défaut |
| `wide` | `blocks`, qui s'étendent sur toutes les colonnes de la page |
| `columnFlow` | `count` de 2 à 6, 2 par défaut ; `blocks`, qui s'écoulent en colonnes |
| `spacer` | `lines` de 1 à 50, 1 par défaut |
| `pageNumber` | `mode` `auto`, `fixed`, `skip` ou `reset`, `auto` par défaut ; facultativement `value`, jusqu'à 16 caractères |
| `runningFooter` | `text` html : le libellé au pied des pages qui suivent |
| `footnote` | `id`, un nom ; `text` html : la note à laquelle renvoie `<sup data-footnote="id">` |
| `toc` | `title` brut ; `depth` de 1 à 6, 3 par défaut ; `wide`, `false` par défaut : générée à partir des titres au rendu ; au plus dix par document |
| `index` | `title` brut ; `columns` de 1 à 6, 2 par défaut ; `entries` `[{term, pages, children […]}]` |
| `abbreviations` | `title` brut : générée au rendu, chaque `<abbr title>` du document une fois, avec le premier sens qui lui est donné, en ordre alphabétique ; au plus dix par document |
| `watermark` | `text` brut, jusqu'à 100 caractères |
| `note`, `descriptive` | `title` html, `""` par défaut ; `blocks` : un encadré, un texte à lire à voix haute |
| `statblock` | `kind` `monster`, `spell`, `item` ou `other` ; `name` ; `subtitle` html ; `frame`, `wide` ; `sections` `[{title, properties [{label, value html}], abilities [{label, score, modifier}], blocks}]` ; facultativement `record`, les données de jeu auxquelles il est lié, ou `null` |
| `definitionList` | `items`, au moins un `{term html, definitions [html, …]}` avec au moins une définition |
| `credit` | `title` brut ; `text` html |
| `banner` | `text` brut, jusqu'à 100 caractères |
| `decoration` | `kind` `stain` ou `ornament` ; `variant` de 1 à 99 ; facultativement `color` `#rrggbb` |
| `qrCode` | `data`, de 1 à 2 048 caractères ; facultativement `rendered` |
| `container` | `classes`, au moins un nom que l'apparence déclare ; `blocks` |

Un bloc de statistiques ne contient aucun système de jeu : ses libellés et ses valeurs sont ceux du rédacteur.

## Réglages : `Tunes`

| Réglage | Valeur | Lit aussi |
|---|---|---|
| `alignment` | `{alignment: left, center, right or justify}` | `alignmentTune` |
| `anchor` | `{anchor}`, une lettre puis jusqu'à 63 caractères parmi `A-Za-z0-9_-` | `anchorTune` |
| `textVariant` | `""`, `call-out`, `citation` ou `details` | |
| `notice` | `{style: info, warning or spoiler, caption}` | `noticeTune` |
| `indent` | `{indentLevel: 0–8}` | `indentTune` |
| `layout` | `{position: absolute or relative, top, right, bottom, left, width, height, wrap: left or right, zIndex: -10–100}`, des longueurs comme `20px`, `-3cm`, `1.5em`, `50%`, `0` ou `auto` | |
| `mask` | `{shape: center, edge or corner, variant 1–99, offsetX, offsetY: percentages, rotation: -360–360}` | |
| `toc` | `{include}` : si un titre entre dans la table des matières | |
| `variant` | `{name, frame, decoration}` : une variante que l'apparence déclare | |
| `language` | `{language, direction}` : une étiquette BCP 47 et, facultativement, `ltr`, `rtl` ou `auto`, pour un bloc dans une autre langue que celle du document | |
| `data` | jusqu'à 32 textes d'au plus 1 000 caractères, sous des noms `[a-z][a-z0-9-]*` d'au plus 64 caractères : les valeurs `data-*` du bloc, qu'un site lie à ses propres champs | |
| `note` | `{text}`, jusqu'à 10 000 caractères : la note du rédacteur sur le bloc, jamais publiée | |
