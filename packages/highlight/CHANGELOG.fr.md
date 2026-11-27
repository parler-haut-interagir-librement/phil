<!-- phil-dev source-sha256=2bce3a296283aa4d5a92209ed73f9f8f267e01596f003c9ba462b676f9104004 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/highlight` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-17

Documentation seulement.

### Modifié

- La description de `composer.json`, affichée sur Packagist, dit ce que fait le paquet.
- Le README, en anglais et en français, dit ce que fait le paquet, sans la formulation de ses premières ébauches.
- `CONTRIBUTING.md` dit ce que fait le paquet et comment y travailler, sans la formulation de ses premières ébauches.
- La page d'installation dit ce que fait le paquet sans cette formulation.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Token` et `TokenType` : un morceau de la source et ce qu'il est, 26 types dont la classe HTML est `hl-` suivie de
  leur valeur.
- `Language`, l'interface d'un analyseur lexical qui ne lance jamais d'exception et ne perd jamais un octet.
- `Code`, l'analyseur lexical d'un langage de programmation décrit par une `Syntax` — mots, commentaires, `Quote`,
  préfixes, sigils, annotations, symboles, macros, lignes de préprocesseur, expressions régulières —, et `Definitions`,
  les syntaxes de PHP, JavaScript, TypeScript, JSON, Python, Ruby, Go, Rust, Java, C, C++, C#, SQL, du shell et des
  expressions Twig.
- `Markup` (HTML avec ses scripts et ses styles, XML), `StyleSheet` (CSS, SCSS), `Yaml`, `Ini`, `Dockerfile`, `Diff`,
  `Markdown`, `Twig` et `Php` (code et balisage mêlés).
- `HtmlFormatter`, les jetons en HTML échappé dans des `<span class="hl-…">`, d'un coup ou ligne par ligne avec
  `formatLines()`.
- `LineOptions`, `LineRanges` et `LineMark` : un bloc ligne par ligne, chaque ligne dans un `<span class="hl-line">`,
  numérotée à partir d'un premier numéro, les lignes de plages comme `1,3-5` surlignées, et les annotations
  `[!code ++]`, `[!code --]` et `[!code highlight]` des commentaires, avec un nombre de lignes après `:`, lues et
  retirées.
- `Highlighter` : chaque langage fourni par nom et par alias quelle que soit la casse, des langages ajoutés ou
  remplacés, les jetons, le HTML, ligne par ligne avec des `LineOptions`, et `highlight()` dans la forme que prend un
  moteur de rendu de code.
- `HighlightException`, implémentée par `UnknownLanguage`, `InvalidLanguage` et `InvalidLineRanges`.
- Les thèmes `themes/light.css`, `themes/dark.css` et `themes/auto.css`, les lignes marquées signalées par une teinte,
  une barre et un signe.
- Les cibles de fuzzing `line-ranges` et `line-annotations`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
