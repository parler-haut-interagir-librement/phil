<!-- phil-dev source-sha256=b6170c644f306b8bc75aa92eb5f741eebcdfd77c37217df21267256c2ea75f05 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/ujo` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.4-alpha - 2026-11-20

Un fragment HTML en ligne qui contient trop de balises, ou les imbrique trop profondément, est refusé dès que son budget
est épuisé. Une version corrective et non mineure, comme pour 0.1.3-alpha : aucun champ que le normaliseur accepte, 100
000 caractères au plus, ne contient plus de balises que le budget par défaut.

### Ajouté

- `Exception\InputTooComplex`, lancée par `InlineSanitizer::sanitize()` et `toText()` pour un fragment qui contient plus
  de balises et de commentaires que `maxTokens`, et par `sanitize()` pour un fragment qui imbrique ses éléments plus
  profondément que `maxDepth` — et donc par le `Normalizer`, le `HtmlRenderer` et `PlainText` — ; `$budget` nomme le
  budget, `tokens` ou `depth`, et `$limit` sa valeur.
- `maxTokens` et `maxDepth` sur `InlineSanitizer`, gardés par `withProfile()` ; `InlineSanitizer::DEFAULT_MAX_TOKENS`,
  100 000, et `InlineSanitizer::DEFAULT_MAX_DEPTH`, 512.
- La section [Les budgets d'un fragment](doc/fr/inline-html.md#les-budgets-dun-fragment).

### Modifié

- L'assainisseur en ligne trouve les balises d'un fragment une à la fois au lieu de toutes à la fois : il ne garde que
  les éléments encore ouverts, et s'arrête à la première balise au-delà de son budget.
- Le banc d'essai lit des paragraphes hostiles dans les budgets par défaut : des balises en ligne imbriquées sur 512
  niveaux, et 5 000 à la suite.

## 0.1.3-alpha - 2026-11-18

Un fragment HTML en ligne de plus de 4 Mio, lu auparavant, est refusé par défaut. Une version corrective et non mineure
: seul un document construit en PHP peut porter un tel fragment, puisque `JsonDecoder` refuse déjà un document JSON de
plus de 4 Mio.

### Ajouté

- `Exception\InputTooLarge`, lancée par `InlineSanitizer::sanitize()` et `toText()` pour un fragment plus long que le
  plafond de l'assainisseur, avant sa lecture — et donc par le `Normalizer`, le `HtmlRenderer` et `PlainText`, qui
  lisent le HTML en ligne par lui — ; `$bytes` et `$limit` donnent les deux longueurs, et `check()` refuse une entrée
  au-dessus d'un plafond.
- `maxBytes`, le plafond d'un fragment en octets, sur `InlineSanitizer`, gardé par `withProfile()` ;
  `InlineSanitizer::DEFAULT_MAX_BYTES`, 4 Mio, le plafond de `JsonDecoder`.
- Le second argument de `PlainText`, l'`InlineSanitizer` avec lequel il lit le texte du HTML, gardé par `with()`.
- La cible de fuzzing `ujo-inline` : l'assainisseur sur des entrées que personne n'a écrites, sous un plafond de 1 Kio.
- La section [Le plafond d'un fragment](doc/fr/inline-html.md#le-plafond-dun-fragment).

### Modifié

- L'assainisseur en ligne, et chaque chemin qui lit du HTML en ligne par lui — un document construit en PHP et converti
  en texte ou rendu sans être décodé compris —, refuse un fragment de plus de 4 Mio à moins d'être configuré.

## 0.1.2-alpha - 2026-11-15

### Modifié

- Un élément de liste de contrôle est rendu avec une case à cocher désactivée, `checked` quand l'élément l'est, dans un
  `<label>` qui contient le texte de l'élément : l'état est annoncé par les lecteurs d'écran avec le texte de l'élément
  comme nom de la case, et pas seulement donné aux styles par `data-checked`. Une liste imbriquée reste après le
  libellé.
- `Rendering::tag()` écrit `input` sans contenu ni balise de fin.

## 0.1.1-alpha - 2026-11-14

### Corrigé

- Le profil `rich` garde `<strong>` et `<em>` à part de `<b>` et de `<i>`, au lieu de les écrire comme `<b>` et `<i>` :
  l'importance forte et l'emphase d'accentuation gardent leur sens pour les lecteurs d'écran et les moteurs de
  recherche. Chaque paire est deux outils qui partagent le nom `bold` ou `italic`, si bien qu'un profil sans `bold`
  laisse les deux de côté.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Document` et `Block`, des valeurs immuables d'un document UJO (Universal JSON Output) : `fromArray()` vérifie la
  structure, `toArray()` écrit la forme JSON, `walk()`, `find()` et `children()` atteignent les blocs imbriqués.
- `JsonDecoder`, une lecture stricte sous une limite de taille et de profondeur, et `JsonEncoder`, une écriture qui
  garde comme objets les objets vides de `data`, `tunes`, `meta`, `file` et `settings`.
- `Schema` : `Shape`, `Property` et les champs `Html`, `Plain`, `Integer`, `Boolean`, `Choice`, `Link`, `ListOf`,
  `Blocks`, `Lazy`, `Dictionary` (des textes nommés, bornés en nombre et en longueur) et `TextOrShape` (un texte ou un
  objet), chacun refusant une valeur avec son chemin ; `Property::filled()`, une valeur remplie pour la publication ;
  `Sized`, les champs dont les valeurs ont une taille — les caractères qu'un lecteur voit, les éléments, les blocs, les
  entrées —, et `Bounded`, un champ dimensionné dans des bornes pour la publication.
- `BlockType`, avec ses hooks `fold`, `upgrade`, `check` et `publication` et son `maxPerDocument`, le plus de blocs du
  type qu'un document peut contenir, et `BlockTypeRegistry`, par nom ou par alias ; le registre par défaut construit une
  fois et partagé.
- `StandardTypes` : chaque outil officiel d'Editor.js — paragraphe, titre, liste avec éléments imbriqués et listes de
  contrôle, citation, avertissement, séparateur, code, brut, tableau, image, outil de lien, pièces jointes, intégration
  et personnalité —, la lecture de la liste 1.x, de l'outil de liste de contrôle, de la liste de contrôle imbriquée, de
  l'image simple, de l'alignement et des ancres enregistrés dans les données, du langage d'un bloc de code, et du texte
  de remplacement propre à une image, `alt`, à part de sa légende ; la forme riche d'un tableau — des cellules
  `{text, colspan, rowspan, header, align}`, une `caption`, l'alignement de chaque colonne — à côté de sa grille de
  textes, `TableGrid` disposant ses fusions comme le fait un navigateur ; le `poster`, le `title` et le `qrCode` d'une
  intégration pour le papier.
- `EmbedServices`, les services qu'une intégration peut encadrer et les hôtes de leurs cadres.
- `CommunityTypes` : alerte, bascule, colonnes, mathématiques — en LaTeX, en MathML Core, ou les deux —, mermaid,
  graphique, bouton et galerie, qui replient les blocs plats de bascule et repliables, et lisent les colonnes de calumk,
  LaTeX, `AnyButton` et le carrousel.
- `MediaTypes` : `video` et `audio`, leurs sources dans l'ordre, leurs pistes de texte avec leur langue, leur affiche et
  leurs options, et leur solution de repli pour le papier.
- `LayoutTypes` : sauts de page et de colonne, styles de page, `wide`, flots de colonnes, espaceurs, numéros de page,
  pieds de page courants, notes de bas de page, tables des matières et listes d'abréviations générées au rendu, dix de
  chaque par document, index, filigranes, notes, texte à lire à voix haute, blocs de statistiques, listes de
  définitions, crédits, bannières, décorations, codes QR et conteneurs.
- `StoredMarkup` : la propriété facultative `rendered` de `math`, `mermaid`, `chart` et `qrCode`, `{markup, key}`, le
  dessin écrit une fois sur le serveur, 1 Mio au plus, sa clé de 1 à 256 caractères sûrs dans une URL.
- `TuneType`, `TuneRegistry` (celui par défaut construit une fois et partagé) et `Tunes` : alignement, ancre, variante
  de texte, avis, retrait, disposition, masque, table des matières et variante, avec les noms de leurs réglages
  Editor.js ; `language` (une étiquette BCP 47 et une direction), `data` (les valeurs `data-*` d'un bloc, rendues
  seulement sur demande) et `note` (une note de la personne qui écrit, jamais rendue).
- `Normalizer`, qui vérifie un document en forme canonique : types et réglages par nom canonique, enfants plats repliés,
  formes plus anciennes mises à niveau, valeurs par défaut écrites, HTML assaini, identifiants uniques, imbrication
  limitée, blocs limités en nombre — 10 000 par défaut — et champs HTML en longueur — 100 000 caractères, 50 000 pour le
  profil `raw` — ; un document qu'il a retourné est retourné de nouveau tel quel, non vérifié une seconde fois à moins
  qu'on le demande à un niveau auquel il n'a pas été vérifié.
- `Validation`, les deux niveaux auxquels un document est vérifié : `Shape`, par défaut, pour chaque enregistrement, un
  brouillon compris, et `Publication`, pour la publication, la planification et la révision, qui demande aussi remplies
  les valeurs que les types exigent et les bornes qu'ils fixent ; les types standard exigent remplis un titre, une
  citation, un code et le nom d'une personne, les éléments d'une liste et les rangées d'un tableau.
- `InlineSanitizer`, `InlineProfile`, `InlineTool`, `AttributeValue`, `LanguageTag` et `SafeUrl` : le HTML en ligne
  assaini en temps linéaire en sa longueur, quelle que soit la profondeur de ses éléments imbriqués, avec les profils
  `rich`, `plain`, `mathml` et `raw` et chaque outil en ligne d'Editor.js et de la communauté, abréviations, passages
  dans une autre langue (`<span lang dir>`, `<bdi>` et `<bdo>` lus comme un seul), ruby et formules en MathML Core
  compris ; un outil lit ses motifs une fois et nomme ses balises, `InlineTool::tags()`, par lesquelles un profil trouve
  les outils d'un élément, peut n'être gardé que `within` d'autres éléments et peut avoir besoin d'`anyOf` ses attributs
  ; les liens du web, `mailto`, `tel`, `sms`, `geo` et les messageries XMPP, Matrix et Viber, chaque schéma avec sa
  propre forme.
- `HtmlRenderer`, `Rendering` — avec `remember()`, une valeur calculée une fois par rendu, par laquelle une table des
  matières et une liste d'abréviations lisent le document une fois —, `BlockRenderer` et les moteurs de rendu par défaut
  `TextRenderer`, `CodeRenderer`, `ListRenderer`, `MediaRenderer` et `LayoutRenderer` : un moteur de rendu par type,
  remplaçable, avec les réglages écrits comme identifiants, classes et styles, et chaque valeur HTML assainie de nouveau
  ; les blocs de code avec leur classe `language-…`, colorés par un surligneur donné à `withCodeHighlighter()` dont le
  HTML n'est gardé que s'il est le code échappé dans des `span` ; le `alt` d'une image le sien, ou le texte de sa
  légende sans lui ; `StoredMarkupRenderer`, ajouté par `withStoredMarkup()`, qui écrit le SVG ou le MathML stocké d'un
  bloc une fois qu'une fermeture a prouvé sa clé et se rabat sinon sur le moteur de rendu propre au bloc ; `HtmlFilter`,
  des dernières passes sur le HTML rendu ajoutées par `withFilter()` ; `lang` et `dir` d'après le réglage `language`, et
  le réglage `data` comme attributs `data-*` une fois que `withDataAttributes()` les demande ; les vidéos, les sons et
  les cadres suivis de leur solution de repli pour le papier, `.ujo-print-fallback`.
- `PlainText`, une conversion en texte par type, remplaçable ; une image donne sa légende, ou son `alt` sans elle ;
  `passages()`, le texte par langue, comme le disent les réglages `language` des blocs.
- `Ujo`, la bibliothèque en un objet : `decode()` et `normalize()` à un niveau de validation, `encode()`, `toHtml()` et
  `toText()`.
- `UjoException`, implémentée par `InvalidJson`, `InvalidDocument`, `UnknownBlockType` et `InvalidConfiguration`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README, avec les points d'extension.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
