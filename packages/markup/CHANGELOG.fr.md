<!-- phil-dev source-sha256=825106d67d276e33335256d82b7666ebc77d39cff3fbfe1b532d53379f3d800c -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/markup` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.3.2-alpha.

Développement seulement.

### Modifié

- Les prérequis de développement demandent la dernière mineure de chaque dépendance (`doctrine/orm` 3.8, `doctrine/dbal`
  4.6).

## 0.3.1-alpha - 2026-11-20

Une source Markdown de plus de 100 000 lignes, lue auparavant, est refusée par défaut. Une version corrective et non
mineure : une source de 1 Mio au plus, le plafond par défaut, ne contient autant de lignes que si la plupart sont vides.

### Ajouté

- `Exception\InputTooComplex`, lancée par `MarkdownReader` pour une source qui contient plus de lignes que `maxLines`,
  comptées avant que la liste de ses lignes soit construite, quelle que soit leur fin ; `$budget` nomme le budget,
  `lines`, et `$limit` sa valeur.
- `maxLines` sur `MarkdownReader` ; `MarkdownReader::DEFAULT_MAX_LINES`, 100 000.
- La section [Le budget de lignes de Markdown](doc/fr/analyser.md#le-budget-de-lignes-de-markdown).
- Des entrées du corpus des purificateurs pour les références de caractères complétées par des zéros et les schémas
  spéciaux sans leur autorité, et un test des images limitées à certains hôtes dans chaque adresse : `srcset` sur `img`
  et `source`, un schéma sans `//`, une adresse sans son schéma, un hôte qui ne fait que commencer comme un hôte permis.

### Modifié

- La cible de fuzzing `markup-markdown` lit sous un budget de 64 lignes et vérifie qu'une source est refusée exactement
  quand elle en contient plus ; le germe `many-lines.md` mêle les trois fins de ligne.

## 0.3.0-alpha - 2026-11-18

Une source de plus de 1 Mio, ou une source UJO de plus de 4 Mio, lue auparavant, est refusée par défaut, de même qu'un
HTML de plus de 4 Mio donné à un purificateur, et un commentaire HTML seul en Markdown est une note au lieu d'un texte.

### Ajouté

- `Exception\InputTooLarge`, lancée par chaque lecteur et par `Adapter\DefaultCompiler` pour une source plus longue que
  son plafond, avant qu'un seul de ses octets soit lu ; `$bytes` et `$limit` donnent les deux longueurs, et `check()`
  refuse une entrée au-dessus d'un plafond.
- `maxBytes`, le plafond d'une source en octets, sur `MarkdownReader`, `BBCodeReader`, `MediaWikiReader`,
  `DokuWikiReader`, `CreoleReader`, `HtmlReader`, `TextReader`, `UjoReader` et `DefaultCompiler`, qui le donne aux
  lecteurs que retourne `reader()` et place derrière lui le lecteur du format d'un add-on ;
  `MarkupReader::DEFAULT_MAX_BYTES`, 1 Mio.
- `UjoReader::DEFAULT_MAX_BYTES`, le plafond d'une source UJO, celui du `JsonDecoder` de `phil/ujo` : 4 Mio.
  `ujoMaxBytes` sur `DefaultCompiler`, le plafond d'une source UJO compilée ou lue, donné à son lecteur d'UJO et au
  décodeur derrière lui.
- `maxBytes`, le plafond d'un HTML purifié en octets, sur `SanitizerPurifier` et ses `defaultPolicies()`,
  `AmmoniaPurifier::defaultPolicies()` et `Purifiers::defaultPolicies()` : un HTML plus long est refusé en entier par
  `InputTooLarge` avant qu'un assainisseur le voie ; `HtmlPurifier::DEFAULT_MAX_BYTES`, 4 Mio. La cible de fuzzing
  `markup-purifier` purifie sous un plafond de 2 Kio et vérifie qu'une entrée plus longue est refusée.
- Le lecteur Markdown garde un bloc HTML fait seulement de commentaires comme le réglage `note` du bloc qui le suit, ou
  du dernier bloc quand aucun ne suit, comme le lecteur HTML garde les commentaires d'une page : les notes de l'orateur
  d'une présentation Marp sont lues telles qu'écrites.
- `testMarkdownIsReadInLinearTime()`, et les formes d'éléments HTML frères laissés de côté ou dont le `data-*` n'est pas
  gardé, dans `WikiLinearityTest` ; 100 Ko de courts paragraphes Markdown et d'éléments HTML frères laissés de côté dans
  `ReadersBench` ; des germes de fuzzing d'une présentation Marp et de pertes de frères.
- La section [Le plafond d'une source](doc/fr/analyser.md#le-plafond-dune-source), la section [Le plafond d'un HTML
  purifié](doc/fr/reference.md#le-plafond-dun-html-purifié) et la section
  [Commentaires](doc/fr/markdown.md#commentaires) de la page sur Markdown.

### Modifié

- Chaque lecteur, `DefaultCompiler` dans chaque format — HTML et texte brut compris —, et donc `Markup`, refusent une
  source de plus de 1 Mio à moins d'être configurés ; une source UJO, de plus de 4 Mio.
- `SanitizerPurifier`, `AmmoniaPurifier`, et donc `Markup`, refusent un HTML de plus de 4 Mio à moins d'être configurés,
  alors qu'ils purifiaient auparavant n'importe quelle longueur.
- Un commentaire HTML seul en Markdown n'est plus échappé en texte sous le niveau `all`, ni retiré au niveau `all` :
  c'est la note d'un bloc.

### Corrigé

- Le lecteur Markdown ajoute chaque bloc sur place : un document de nombreux blocs, de nombreuses listes de définitions
  qui se suivent, un élément de liste qui contient de nombreuses listes et une section d'un bloc de statistiques qui
  contient de nombreux blocs prenaient un temps qui croissait avec le carré de leur nombre.
- Le lecteur HTML compte une seule fois les enfants d'un élément pour les chemins de ses pertes : une page de nombreux
  éléments frères laissés de côté prenait un temps qui croissait avec le carré de leur nombre.
- `ReadersBench` lit ses sources écrites pour être lentes sans les limites d'un document stocké, que leurs paragraphes
  de 100 Ko dépassaient.

## 0.2.1-alpha - 2026-11-15

### Ajouté

- `Adapter\AmmoniaPurifier` : les politiques de `SanitizerPurifier::defaultPolicies()` sur l'extension `ammonia` de PHP,
  pour PHP 8.2 à 8.5, environ deux fois plus rapide que l'assainisseur dès 12 Ko et avec le septième de sa mémoire sur
  100 Ko ; les mêmes éléments gardés à chaque niveau, `style` et les adresses que l'extension ne vérifie pas retirés,
  les classes filtrées comme les filtre `CodeClasses`. Un HTML court, un HTML de plus de 10 000 balises, celui qui
  contient `<noscript`, celui qui n'est pas de l'UTF-8 valide, et les images d'hôtes limités vont à l'assainisseur.
  `isAvailable()` dit si l'extension est chargée ; `ext-ammonia` dans `suggest`.
- `Adapter\Purifiers::defaultPolicies()` : `AmmoniaPurifier` là où l'extension est chargée, `SanitizerPurifier` sinon,
  ou toujours l'assainisseur.
- `SanitizerPurifier::configurations()`, la configuration de chaque niveau d'où sont construites les politiques par
  défaut ; `CodeClasses::keep()`, les classes gardées d'un attribut `class`.
- `Exception\MissingExtension`, lancée par un adaptateur dont l'extension n'est pas chargée.
- `PurifierCorpusTest`, du HTML hostile donné aux deux purificateurs à chaque niveau ; la cible de fuzzing
  `markup-purifier` ; le banc d'essai `PurifierBench`, la purification seule ; la page du worker purifie avec
  `Purifiers`.
- La page [Accélérateurs](doc/fr/accelerators.md).
- `Adapter\CheckboxText`, le filtre que `DefaultCompiler` exécute sous le niveau `all` : la case à cocher d'un élément
  de liste de contrôle écrite en texte, `☑` cochée et `☐` non, pour que l'état se lise encore là où aucun `input` n'est
  gardé.
- `LossReason::MisplacedCheckbox`, une case à cocher dans un élément de liste qui n'est pas à son début ;
  `LossReason::PartialChecklist`, une case à cocher au début d'un élément dans une liste dont certains éléments ne
  disent aucun état ; `LossReason::TableInListItem`, un tableau dans un élément de liste, pour lequel UJO n'a pas de
  place.
- La section [Listes de contrôle](doc/fr/reference.md#listes-de-contrôle) de la référence : ce que chaque niveau garde
  d'une liste de contrôle.

### Modifié

- Le gras et l'italique en Markdown — `**b**`, `__b__`, `*i*`, `_i_` — sont `<strong>` et `<em>`, comme les écrit
  CommonMark, `***x***` étant `<em><strong>x</strong></em>` ; de même pour `'''bold'''` et `''italic''` en MediaWiki, et
  `**bold**` et `//italic//` en DokuWiki et en Creole. Les `[b]` et `[i]` de BBCode, et les balises `<b>` et `<i>` du
  HTML, restent `<b>` et `<i>`.
- Le niveau `all` garde la case à cocher d'un élément de liste de contrôle : `SanitizerPurifier` récrit chaque `input`
  comme `<input type="checkbox" disabled>`, `checked` quand il l'avait, et retire un input de tout autre type ou sans
  type. Sous `all`, `label` est déballé, son texte gardé.
- `AmmoniaPurifier` donne à l'assainisseur un HTML qui contient `<input`.
- Le lecteur HTML prend l'état d'un élément de liste de contrôle de la case à cocher à son début seulement — un enfant
  du `<li>`, ou le premier enfant d'un `<label>` ou d'un `<p>` par lequel il commence —, et retire cette case du contenu
  de l'élément.
- `phil/ujo` 0.1.2 ou plus récent est exigé, pour les cases à cocher accessibles de ses listes de contrôle.

## 0.2.0-alpha - 2026-11-14

### Corrigé

- Le lecteur HTML, et les balises HTML lues dans le wikitexte, gardent `<strong>` et `<em>` comme `<strong>` et `<em>`
  en UJO, au lieu de `<b>` et `<i>`.

### Modifié

- Le niveau `all` suit les éléments sûrs de l'HtmlSanitizer de Symfony 8.2.0-BETA2 : `link`, `meta`, `plaintext`,
  `html`, `body` et l'attribut `referrerpolicy` ne sont plus gardés.
- `phil/ujo` 0.1.1 ou plus récent est exigé, pour que `<strong>` et `<em>` soient gardés du lecteur jusqu'au HTML rendu.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `MarkupFormat` (`markdown`, `html`, `bbcode`, `mediawiki`, `dokuwiki`, `creole`, `text`, `ujo`), `nameOf()` le nom
  d'un cas ou d'un format qu'apporte un add-on, et `Level` (`basic`, `forum`, `all`).
- `MarkedUpText` : une source, le nom de son format et son HTML purifié, un objet intégrable de Doctrine ;
  `doctrine/dbal` et `doctrine/orm` facultatifs, dans `suggest`, seule une application qui le stocke en ayant besoin.
- `Exception\MarkupException` ; `Exception\InvalidSource`, lancée pour une source UJO qui n'est pas valide ;
  `Exception\UnknownFormat`, pour un format qu'aucun lecteur ne connaît et un nom qu'un add-on ne peut pas prendre.
- `MarkupCompiler` et `HtmlPurifier`, les deux ports, un format donné comme `MarkupFormat` ou par son nom ;
  `LevelAwareCompiler`, un compilateur à qui l'on dit le niveau auquel son HTML sera purifié ; `Markup`, qui compile —
  au niveau, pour un compilateur qui connaît les niveaux — puis purifie.
- `Lexer\Cursor`, l'analyseur que partagent tous les lecteurs : un curseur qui analyse vers l'avant sans expressions
  régulières — `comesNext()`, `consume()`, `consumeWhile()`, `consumeUntil()`, `consumeThrough()` —, et recule quand une
  règle ne correspond pas ; `Lexer\Slugger`, les ancres uniques des titres d'un document, en temps constant par titre.
- `Reader\MarkupReader` et `Reader\Reading` : une source lue en un document UJO vérifié de `phil/ujo`, avec ses
  métadonnées et ses pertes, `Reader\Loss` et `Reader\LossReason` — l'élément non gardé, son chemin et pourquoi — ; les
  lecteurs à qui aucun normaliseur n'est donné en partagent un, construit une fois, qui accepte les blocs `raw`.
- `Contract\ExtractedElementStore`, le port où un lecteur stocke, comme fichiers, les éléments qu'il extrait : un dessin
  SVG en ligne.
- `Reader\Markdown\MarkdownReader`, qui lit en deux passes, les blocs puis le contenu en ligne par une règle par jeton :
  CommonMark — titres, paragraphes, citations, listes, code, filets, liens et images, références, emphase, échappements,
  entités, HTML —, le texte d'une image son texte de remplacement et son titre, écrit après son adresse ou dans sa
  référence, sa légende ; les extensions de GitHub — tableaux, texte barré, liens automatiques, listes de tâches — ; les
  notes de bas de page ; les attributs `{.class #id}` et `{class,#id,property:value}` comme réglages ou comme conteneur
  ; les listes de définitions ; les ancres des titres ; `[TOC]` ; `==highlight==` ; le ruby, `{漢字|かんじ}` ; l'en-tête YAML
  ; les blocs `:::` ; le HTML brut gardé pour `all` ou échappé en deçà ; les pages et les colonnes, les blocs `{{ }}` et
  les portions seules sur leur ligne associés aux types UJO du même rôle — notes encadrées, texte à lire à voix haute,
  blocs de statistiques, tables des matières, citations, crédits, tableaux de classes, listes de sorts, couvertures,
  numéros de page, pieds de page courants, filigranes, bannières, décorations. N'importe quel texte est un document, lu
  en temps linéaire : l'emphase est appariée sur une liste chaînée de nœuds et un paragraphe de nombreuses lignes est
  construit sur place. Un `Reader\Markdown\Contract\MarkdownDialect` — `BaseDialect` à étendre — ajoute ses règles en
  ligne (`Rule\InlineRule`, `Rule\DelimiterRule('^')` pour les exposants) et ses règles de blocs (`Contract\BlockRule`)
  après celles intégrées, désactive une syntaxe intégrée (`MarkdownSyntax`), et lit ses propres métadonnées, ses lignes
  `[label]: value`, ses marques de définition et ses marques de paragraphe ; ce qu'il garde pendant une lecture va dans
  `Context::$extra`.
- `Reader\BBCode\BBCodeReader`, sur le même analyseur, en temps linéaire quelle que soit l'imbrication des balises,
  laissées ouvertes ou fermées sans avoir été ouvertes : les balises des forums — gras, italique, souligné, barré,
  exposants, surlignage, code en ligne, liens, courriels, images avec leur texte de remplacement et leur disposition,
  citations avec leur auteur, code avec son langage, listes ordonnées, numérotées ou à cocher, tableaux avec `colspan`,
  `rowspan` et `align`, titres, couleurs, alignement, divulgâcheurs, `[ruby=reading]` — et des extensions dans leur
  style pour chaque fonctionnalité du lecteur Markdown — `[footnote]`, `[dl]`, `[toc]`, les attributs `id`, `class`,
  `align`, `width`, `height`, `wrap`, `[note]`, `[descriptive]`, `[warning]`, `[alert]`, `[wide]`, `[block]`,
  `[columns]` et `[column]`, `[pagebreak]`, `[columnbreak]`, `[statblock]`, `[credit]`, `[classtable]`, `[spacer]`,
  `[pagenumber]`, `[footer]`, `[watermark]`, `[banner]`, `[decoration]`, `[nbsp]`, `[icon]`, `[hspace]`, `[var]`,
  `[metadata]`, `[style]` — ; le HTML brut dans `[html]` gardé pour `all` ou échappé en deçà.
- `Reader\MediaWiki\MediaWikiReader`, `Reader\DokuWiki\DokuWikiReader` et `Reader\Creole\CreoleReader`, bâtis sur un
  lecteur que partagent les trois wikis, `Reader\Wiki`, en temps linéaire quelle que soit la façon dont les marques, les
  liens, les modèles et les conteneurs sont laissés ouverts, croisés ou imbriqués : la syntaxe native de chaque wiki —
  titres, emphase, liens vers des pages et vers d'autres sites, images et fichiers placés et dimensionnés, avec leur
  légende — et le `alt=` de MediaWiki à part, le texte de Creole comme texte de remplacement —, listes, listes de
  tâches, définitions, retraits, tableaux — la légende, les fusions et l'alignement de MediaWiki, les fusions `||` et
  `:::` de DokuWiki et son alignement par les espaces —, texte préformaté, texte gardé tel qu'écrit, sauts de ligne,
  filets ; les entités, les mots magiques, les commentaires et les modèles de MediaWiki, les notes de bas de page
  `((…))` de DokuWiki, ses médias placés par leurs espaces et ses macros `~~…~~`, Creole 1.0 et ses ajouts — et des
  balises d'extension qu'ils partagent, `<note>` en MediaWiki et en DokuWiki et `<<note>>` en Creole, pour chaque
  fonctionnalité du lecteur Markdown : notes de bas de page, `<ref name>` cité de nouveau, divulgâcheurs, couleurs,
  icônes, espaces, variables, notes, texte à lire à voix haute, avertissements, alertes et le module de notes de
  DokuWiki, citations avec leur auteur, bascules, blocs sur toutes les colonnes, classes de l'apparence, colonnes côte à
  côte ou en flot, blocs de statistiques, crédits, tableaux de classes, alignement, pieds de page courants, filigranes,
  bannières, tables des matières, sauts de page et de colonne, styles de page, espaceurs, numéros de page, décorations,
  code avec son langage, métadonnées et style, les attributs `id`, `class`, `align`, `width`, `height`, `wrap` ; le HTML
  brut dans `<html>` gardé pour `all` ou échappé en deçà. `Reader\Wiki\WikiLinks` dit où se trouvent les pages et les
  médias que nomme un texte de wiki.
- `Reader\Html\HtmlReader`, du HTML lu en UJO par l'analyseur HTML5 de PHP : paragraphes, titres, listes et listes de
  contrôle, citations, figures, images — leur `alt` le texte de remplacement, leur `<figcaption>` ou sinon leur `title`
  la légende —, code, filets, tableaux, listes de définitions, détails, alignement et les éléments en ligne que garde
  UJO ; le HTML que rend `phil/ujo`, ses classes `ujo-*` et ses notes de bas de page relus dans leurs blocs ; les styles
  qu'écrit un collage depuis un traitement de texte lus comme gras, italique, souligné et barré ; les cadres de services
  connus comme blocs `embed`, les vidéos et les sons avec leurs pistes, les formules en MathML, les liens de
  téléchargement seuls dans leur paragraphe comme fichiers joints, les abréviations, les langues comme réglages et
  `<span lang>`, le ruby, les tableaux avec leur légende, leurs fusions, leurs cellules d'en-tête et leurs alignements,
  les listes d'une cellule comme lignes avec leurs marques, les attributs `data-*` et les commentaires comme réglages
  `data` et `note`, les liens des schémas que permet `phil/ujo` et les ancres que nomme la page, les dessins SVG en
  ligne purifiés par `phil/svg-sanitizer` et stockés par un `ExtractedElementStore` ; les scripts, les styles et les
  formulaires laissés de côté ; ce qui n'est pas gardé listé dans les pertes de la lecture ; le titre, la langue et les
  `<meta>` nommées d'une page comme ses métadonnées ; `ext-dom` et `ext-libxml` exigées.
- `Reader\UjoReader` : le JSON UJO lu strictement par `phil/ujo`.
- `Reader\Text\TextReader` : du texte brut lu en UJO, un paragraphe pour chaque suite de lignes, les sauts de ligne
  gardés, chaque caractère échappé et les adresses changées en liens.
- `Adapter\DefaultCompiler` : Markdown, BBCode, MediaWiki, DokuWiki, Creole et UJO lus en UJO et rendus par `phil/ujo` ;
  le HTML tel qu'écrit ; le texte brut par `Adapter\PlainText`, les adresses changées en liens par `Adapter\Autolink` ;
  `reader()` donne le lecteur de chaque format en UJO, ceux du HTML et du texte brut compris ; les formats des add-ons,
  chacun un `Reader\Contract\ReaderProvider` donné par nom, lus et rendus de la même façon ; `formats()` le nom de
  chacun.
- `Adapter\SanitizerPurifier` : une politique de `symfony/html-sanitizer` par niveau, qui prend les hôtes d'où peuvent
  venir les images, puisqu'une image d'un autre site, que le navigateur de chaque lecteur appellerait, est du pistage
  aux niveaux `forum` et `all` ; chaque classe retirée sauf celles du code, gardées par `Adapter\CodeClasses` :
  `language-…` sur `code` et `hl-…` sur `span` ; les liens des schémas de `phil/ujo`, et MathML Core au niveau `all`.
- La documentation dans `doc/`, indexée par le README, avec la correspondance des fonctionnalités de chaque format.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
