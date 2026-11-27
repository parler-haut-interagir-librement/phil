<!-- phil-dev source-sha256=42b871a46293c866e2ab1285b9ecc40545ac47653de45f471d054ac401a8da89 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/svg-sanitizer` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.3-alpha.

Tests seulement.

### Ajouté

- Un test différentiel HTML5 : chaque sortie analysée de nouveau par `Dom\HTMLDocument`, l'analyseur HTML5 de PHP, dans
  un `div`, après un paragraphe et dans un lien, sous la politique par défaut, avec des éléments `<style>`, avec les
  animations et avec tout ce qui est facultatif, ne contient aucun script, aucun élément HTML, aucun attribut `on…` et
  aucune adresse `javascript:`, pour les charges des contournements connus des assainisseurs SVG et celles des avis de
  Symfony UX Icons du 2026-11-24 ; la documentation de la menace.

## 0.1.2-alpha - 2026-11-24

Documentation et tests seulement.

### Ajouté

- Des tests de régression : une animation qui réécrit un lien, par `set` ou `animate`, `href` ou `xlink:href`, en
  capitales ou avec une espace ; des références de caractères complétées par des zéros, des tabulations et des sauts de
  ligne dans un schéma ; des images d'un schéma spécial sans son autorité ; une sortie assainie de nouveau, qui ne perd
  rien.
- La documentation d'une sortie assainie de nouveau, et de son préfixe d'`id` écrit une fois de plus.

## 0.1.1-alpha - 2026-11-15

### Ajouté

- Des catalogues des messages de `SafeSvg` en ICU MessageFormat (`translations/validators+intl-icu.<locale>.php`), que
  l'application donne à son traducteur : en anglais, `moreMessage`, dont le pluriel suit `{{ count }}` ; en français,
  chaque message par défaut, ce qui a été retiré nommé d'après `{{ reason }}`. Les messages par défaut, qui sont les
  clés de traduction, ne changent pas.

### Modifié

- `SafeSvg` : les paramètres `{{ line }}` et `{{ count }}` de ses violations sont des entiers.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `SvgSanitizer` : `sanitize()` lit une image comme XML sans réseau, ni type de document ni entités, et récrit, dans
  l'espace de noms SVG et sans déclaration XML, les éléments et les attributs que garde la politique, leur texte, et
  rien d'autre ; retourne `Sanitized` — le SVG, vide quand l'image est refusée en entier — et chaque `Removal` avec sa
  `RemovalReason`, son sujet et sa ligne, les `Sanitized::MAX_REMOVALS` (100) premiers listés et les autres comptés dans
  `omittedRemovals`. Une image n'est lue qu'en UTF-8 : celle qui a un octet nul, comme en ont l'UTF-16 et l'UTF-32, ou
  des octets qui ne sont pas de l'UTF-8 est refusée comme malformée.
- `Policy` : des listes d'autorisation d'éléments et d'attributs pour les dessins statiques — structure, formes, texte,
  serveurs de peinture, détourage, masques, marqueurs, filtres, images matricielles —, l'animation SMIL par
  `withAnimations()`, les schémas des liens, les images matricielles dans des adresses `data:`, la politique CSS de
  l'image, des limites de taille, de profondeur, de nombre d'éléments et d'attributs par élément (`maxAttributes`, 256
  par défaut, comptés en une passe avant l'analyse de l'image, refusée en entier comme `TooManyAttributes`) ; sûre en
  ligne par défaut : `<style>` laissé de côté à moins que `withStyleElements(true)`, `new Policy(styleElements: true)`
  ou `withElements('style')` le permette, et chaque `id`, avec chaque fragment de `href`, de `xlink:href` et d'`url()`
  qui y pointe, écrit après le préfixe de `withIdPrefix()`, `svg-` par défaut, `null` pour les garder tels qu'écrits ;
  les éléments qui exécutent du code ou intègrent un document (`script`, `foreignObject`, `iframe`, `object`, `embed`…),
  les gestionnaires d'événements, `xml:base` et les liens vers `javascript:`, `data:` et leurs semblables ne peuvent
  jamais être permis (`InvalidPolicy`).
- Les références vérifiées : `href` et `xlink:href` vers un fragment de l'image ; une image PNG, JPEG, GIF, WebP ou AVIF
  dans une adresse `data:` sur `<image>` et `<feImage>` ; un lien d'un schéma permis sur `<a>`, les espaces et les
  caractères de contrôle de son schéma ignorés comme les navigateurs les ignorent.
- Le CSS par `phil/css-sanitizer` : les attributs `style` comme déclarations, les éléments `<style>` permis comme
  feuilles de style contenues dans l'image — chaque règle placée sous un id écrit d'après le contenu de l'image (le
  préfixe, `drawing-` et seize chiffres hexadécimaux de son SHA-256), qui remplace l'`id` de la racine, chaque référence
  à celle-ci réécrite, pour que deux images d'une même page dont les racines partagent un `id` ne partagent jamais une
  règle, un sélecteur qui commence déjà par cet `id` gardé tel que l'image l'a écrit quand il n'atteint que la racine et
  ce qu'elle contient, les id des sélecteurs préfixés, `@import`, `@page`, `@font-face` et chaque URL sauf un fragment
  retirés —, et les attributs de présentation et les listes de transformations qui contiennent plus que des noms, des
  nombres et des couleurs comme valeur de leur propriété, chaque `url()` sauf un fragment refusée.
- Les animations qui visent une référence, un style ou un gestionnaire d'événements, et les valeurs d'animation qui
  contiennent une fonction ou un schéma, retirées.
- Les instructions de traitement retirées et signalées ; les commentaires et les types de documents laissés de côté.
- `SvgSanitizerException`, implémentée par `InvalidPolicy`.
- `Validator\SafeSvg`, une contrainte de `symfony/validator`, et `SafeSvgValidator` : chaque retrait de l'assainisseur
  une violation, `NOT_ALLOWED_ERROR`, et une image refusée en entier une violation, `REFUSED_ERROR`, avec la raison, le
  sujet et la ligne comme paramètres ; les retraits au-delà des 100 premiers une violation de plus, `moreMessage`, avec
  leur nombre ; `null` et la chaîne vide valides ; la politique celle de l'assainisseur avec lequel le validateur est
  construit.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
