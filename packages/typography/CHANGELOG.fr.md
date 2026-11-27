<!-- phil-dev source-sha256=9e4309672590bd0ee2c1742c726cc7a637d3384274d7aa69c8f5fe2d96bd9e51 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/typography` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.2.0-alpha - 2026-11-18

Un texte de plus de 1 Mio, corrigé auparavant, est refusé par défaut.

### Ajouté

- `Exception\InputTooLarge`, lancée par `Typographer::fix()`, `fixDocument()`, `fixText()` et `Typographers::fixHtml()`
  pour un texte plus long que le plafond, avant son analyse ; `$bytes` et `$limit` donnent les deux longueurs, et
  `check()` refuse une entrée au-dessus d'un plafond.
- `maxBytes`, le plafond d'un texte en octets, sur `Typographer`, `Typographer::for()` et `Typographers`, qui le donne à
  chaque typographe qu'il construit ; `Typographer::DEFAULT_MAX_BYTES`, 1 Mio.
- La section [Le plafond d'un texte](doc/fr/html.md#le-plafond-dun-texte).

### Modifié

- Chaque point d'entrée refuse un texte de plus de 1 Mio à moins d'être configuré.

## 0.1.1-alpha - 2026-11-17

Documentation seulement.

### Modifié

- La description de `composer.json`, affichée sur Packagist, dit ce que fait le paquet.
- Le README, en anglais et en français, dit ce que fait le paquet, sans la formulation de ses premières ébauches.
- `CONTRIBUTING.md` dit ce que fait le paquet et comment y travailler, sans la formulation de ses premières ébauches.
- `data/LICENSES` et la page sur la césure décrivent les motifs écrits pour cette bibliothèque sans cette formulation.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Locale`, une langue avec une écriture et une région facultatives, et ses candidates de la plus précise à la moins
  précise.
- `Run`, les nœuds de texte d'un bloc en parties, avec des parties verrouillées pour les éléments protégés, et `Fixer`,
  l'interface d'une règle.
- Les correcteurs `UnicodeNormalization`, `Ellipsis`, `Dimension`, `Dash` avec `DashStyle`, `SmartQuotes`, `CurlyQuote`,
  `NoSpaceBeforeComma`, `SpaceBeforePunctuation` avec `FrenchSpacing` pour la France et le Canada, `Trademark`, `Unit`,
  `Hyphen` et `WordBreak`, les espaces sans chasse entre les mots thaïs d'après le dictionnaire d'ICU ; les suites
  d'émojis laissées octet pour octet par chacun. `SmartQuotes` ouvre un guillemet après `>`, la fin d'une balise
  échappée en texte, et ferme un guillemet intérieur avant `<`.
- `QuoteStyle` et `QuoteStyles` : les guillemets de 66 langues et régions, d'après l'Unicode CLDR.
- `Patterns`, `Hyphenator` et `Dictionary` : l'algorithme de césure de Liang, jamais avant une marque combinante, avec
  les motifs hyph-utf8 de 69 langues dont la licence les permet, leurs exceptions et longueurs minimales, consignés dans
  `data/LICENSES`, et des motifs propres sous MIT pour le tchèque, le hongrois, l'arménien, l'indonésien, le letton, le
  macédonien et le roumain, générés par `bin/generate-patterns.php` d'après les règles orthographiques de chaque langue
  et testés sur des listes de mots courants ; l'arménien et le macédonien sont expérimentaux, servis seulement par un
  `Dictionary` construit avec eux (`Dictionary::EXPERIMENTAL`).
- `Typographer` : des fragments HTML, des documents entiers et du texte brut corrigés par `Dom\HTMLDocument`, le texte
  seulement, bloc par bloc ; `pre`, `code`, `kbd`, `script`, `style` et `textarea` protégés par défaut, la liste
  configurable ; `<` et `>` échappés dans les valeurs d'attributs, comme la norme HTML actuelle les sérialise.
- `Preset`, les correcteurs de chaque locale par nom et dans l'ordre, et `Typographers`, un typographe par locale,
  configuré par noms de correcteurs, ceux de 64 locales gardés au plus ; `Typographers::fixHtml()`, chaque passage dont
  le `lang` nomme une autre langue corrigé par le typographe de cette langue.
- `TypographyException`, implémentée par `InvalidLocale`, `UnsupportedLanguage` et `UnknownFixer`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
