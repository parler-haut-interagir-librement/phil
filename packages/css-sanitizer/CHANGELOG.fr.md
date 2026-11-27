<!-- phil-dev source-sha256=862624ff339936dad8704b7f02111b96004b33b96b5f2bc12cc1b6b1611dfdf6 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/css-sanitizer` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.2-alpha - 2026-11-24

Documentation et tests seulement.

### Ajouté

- Des tests de régression : des adresses cachées par une référence de caractère complétée par des zéros, des
  échappements CSS, un échappement NUL, un schéma spécial sans son autorité, un `image-set()` qui mêle un fichier et un
  script ; des noms échappés lus comme le navigateur les lit ; un réécriveur qui lance une exception ; des sorties
  assainies de nouveau, inchangées.
- La documentation d'un réécriveur qui lance une exception, et des échappements et des références de caractères dans une
  URL.

## 0.1.1-alpha - 2026-11-15

### Ajouté

- Des catalogues des messages de `SafeCss` en ICU MessageFormat (`translations/validators+intl-icu.<locale>.php`), que
  l'application donne à son traducteur : en anglais, `moreMessage`, dont le pluriel suit `{{ count }}` ; en français,
  chaque message par défaut, ce qui a été retiré nommé d'après `{{ reason }}`. Les messages par défaut, qui sont les
  clés de traduction, ne changent pas.

### Modifié

- `SafeCss` : les paramètres `{{ line }}` et `{{ count }}` de ses violations sont des entiers.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `CssSanitizer` : `sanitize()` pour les feuilles de style et `sanitizeDeclarations()` pour les attributs `style`, qui
  retournent `Sanitized` — le CSS, les règles gardées comme `Sheet\StyleSheet`, `Sheet\ImportRule`, `Sheet\StyleRule` et
  `Sheet\GroupRule`, les déclarations gardées — et chaque `Removal` avec sa `RemovalReason`, son sujet et sa ligne, les
  `Sanitized::MAX_REMOVALS` (100) premiers listés et les autres comptés dans `omittedRemovals`.
- `Policy` : des listes d'autorisation de propriétés, de fonctions, de pseudo-classes, de pseudo-éléments, d'attributs,
  de types de médias et de règles @ avec des valeurs par défaut pour l'écran et l'impression, un sélecteur de portée,
  `!important` et les propriétés personnalisées, des limites de longueur, de profondeur et de niveaux d'imbrication ;
  les propriétés et les fonctions qui exécutent du code ou chargent une ressource ne peuvent jamais être permises
  (`InvalidPolicy`).
- `UrlRewriter` et `UrlKind`, avec `Url\RefuseUrls`, `Url\AllowedFiles` et `Url\CallbackRewriter` : chaque URL — images,
  polices, les feuilles de style d'`@import` — réécrite ou refusée, les fragments gardés sans le demander,
  `javascript:`, `vbscript:`, `livescript:` et `mocha:` refusés avant et après le réécriveur.
- Les sélecteurs vérifiés d'après la grammaire de Selectors Level 4 — sélecteurs de type, universel, de classe, d'ID et
  d'attribut, pseudo-classes avec leurs arguments, pseudo-éléments, combinateurs — et les noms de la politique ; les
  sélecteurs imbriqués relatifs à leur parent, avec `&` refusé dans `:not()`, `:is()` et `:where()` pour qu'il ne puisse
  pas sortir de la portée.
- Les valeurs vérifiées jeton par jeton : les fonctions permises avec leurs arguments, `url()` et chaque image
  d'`image-set()` par le réécriveur, `type()` à côté d'elles, `attr()` avec le type `url` seulement dans
  `target-counter()`, `target-counters()` et `target-text()`.
- Les règles @ : `@media` avec les types de médias de la politique et des caractéristiques qui ne chargent rien,
  `@supports` sans URL, `@page` avec ses sélecteurs de page, ses descripteurs et ses seize boîtes de marge, `@font-face`
  avec ses descripteurs et ses sources locales ou réécrites ; `@import` au début d'une feuille, quand la politique le
  permet, réécrit vers une feuille de style permise avec ses conditions `supports()` et de média ; toute autre règle @
  refusée.
- CSS Nesting : les règles de style, `@media` et `@supports` imbriqués dans une règle de style, lus comme le contenu
  d'un bloc, leurs niveaux bornés par la politique (`RemovalReason::Nesting`), les déclarations écrites avant les règles
  imbriquées.
- `Syntax\Tokenizer`, l'analyseur lexical de CSS Syntax Level 3 écrit d'après la spécification : l'entrée prétraitée et
  rendue en UTF-8 valide, chaque jeton avec ses échappements résolus, les commentaires retirés, les mauvaises chaînes et
  les mauvaises URL, la ligne de chaque jeton.
- `Syntax\Parser`, l'analyseur de CSS Syntax Level 3 : les valeurs de composants, les blocs simples et les fonctions,
  les listes de règles et de déclarations, le contenu d'un bloc comme le lit CSS Nesting, `!important`, ce que la
  spécification laisse tomber gardé comme `Malformed`, l'imbrication bornée par `NestingTooDeep` ; `Token`,
  `SimpleBlock`, `FunctionValue`, `QualifiedRule`, `AtRule`, `Declaration` et `Stylesheet`.
- `Serializer` : les valeurs de composants, les déclarations et les feuilles de style assainies récrites en CSS, les
  identifiants et les chaînes échappés comme le fait CSSOM, les URL comme `url("…")`, un commentaire vide là où deux
  jetons se joindraient, `<` échappé ou tenu à l'écart de `/` pour que la sortie ne commence jamais une balise de fin.
- `CssSanitizerException`, implémentée par `InvalidPolicy` et `NestingTooDeep`.
- `Validator\SafeCss` et `Validator\SafeCssValidator`, une contrainte pour `symfony/validator` : chaque retrait une
  violation, et les retraits au-delà des 100 premiers une de plus, `moreMessage`, avec leur nombre.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
