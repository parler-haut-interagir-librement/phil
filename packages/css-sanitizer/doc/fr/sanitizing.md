<!-- phil-dev source-sha256=02f9e1b22141e4c967449dae393f9a68b1430b8013cfd2308184787928e96c59 -->
# Assainir

## Feuilles de style et attributs `style`

`CssSanitizer::sanitize()` lit une feuille de style : le contenu d'un élément `<style>` ou d'un fichier `.css`. Ses
règles sont des règles de style et des règles @.

`CssSanitizer::sanitizeDeclarations()` lit les déclarations d'un attribut `style`, sans accolades. Les règles @ qui s'y
trouvent sont retirées.

```php
$sheet = $sanitizer->sanitize('h1 { color: red; position: fixed }');
$sheet->css;        // "h1 {\n  color: red;\n}\n"

$inline = $sanitizer->sanitizeDeclarations('color: red; position: fixed');
$inline->css;       // 'color: red'
```

Les deux renvoient un `Sanitized`. Aucune ne lève d'exception sur ce qu'elle lit : tout ce que la politique ne permet
pas est retiré et listé.

## Le résultat

| Membre | Ce qu'il contient |
|---|---|
| `css` | Le CSS à écrire dans la page : deux espaces d'indentation par niveau pour une feuille de style, `name: value; …` pour des déclarations |
| `removals` | Les `Removal`, dans l'ordre de la source, jusqu'à `Sanitized::MAX_REMOVALS` (100) |
| `omittedRemovals` | Les retraits au-delà des 100 premiers, comptés mais non listés |
| `isClean()` | Indique si rien n'a été retiré |
| `sheet` | Les règles gardées d'une feuille de style : `Sheet\StyleSheet` de `Sheet\ImportRule`, de `Sheet\StyleRule` et de `Sheet\GroupRule` |
| `declarations` | Les `Syntax\Declaration` gardées d'un attribut `style` |

Une règle restée sans déclarations est écartée sans retrait propre : ses déclarations ont déjà été listées. Il en va
de même d'une règle de groupe restée vide.

## Les retraits

Une source d'un mégaoctet peut contenir des centaines de milliers de règles refusées : les 100 premiers retraits sont
listés, et `omittedRemovals` compte les autres, pour que ni le résultat ni un formulaire qui l'affiche ne grossissent
avec eux.

Chaque `Removal` a une `reason`, un `subject` et une `line`. Le sujet est le nom de la propriété, le sélecteur,
`@import`, le nom de la fonction avec `()` ou l'URL, coupé à 80 points de code. La ligne est celle de la source où il
commence.

| `RemovalReason` | Ce qui est retiré |
|---|---|
| `Import` | Une règle `@import` que la politique ne permet pas, ou qui n'ouvre pas la feuille de style |
| `AtRule` | Une règle @ que la politique ne permet pas, ou qui ne peut pas se trouver là (autre que `@media` et `@supports` dans une règle de style, dans une boîte de marge) |
| `Prelude` | La règle @ entière dont la requête média, la condition ou le sélecteur de page n'est pas permis |
| `Selector` | Un sélecteur d'une liste ; la règle garde les autres |
| `Property` | Une déclaration dont la politique ne permet pas la propriété, ou une propriété personnalisée quand elles ne sont pas permises |
| `DeniedProperty` | Une déclaration de `behavior`, `-ms-behavior` ou `-moz-binding`, toujours |
| `Function` | Une déclaration qui contient une fonction que la politique ne permet pas |
| `Url` | Une déclaration qui contient une URL refusée par le réécrivain ; dans `@font-face`, cette source seulement ; une règle `@import` |
| `DangerousUrl` | Une déclaration qui contient une URL `javascript:`, `vbscript:`, `livescript:` ou `mocha:` |
| `Value` | Une déclaration dont la valeur contient un jeton qu'aucune valeur permise ne contient, ou qui est vide |
| `Important` | L'indicateur `!important` d'une déclaration, quand la politique ne le garde pas ; la déclaration reste |
| `Syntax` | Ce que CSS écarte lui-même : une déclaration sans deux-points, du texte qui n'est ni une déclaration ni une règle @, une règle coupée avant son bloc, une règle @ sans le bloc qu'il lui faut |
| `FontSource` | Une source `@font-face` d'une forme inutilisable ; une règle `@font-face` restée sans famille ou sans source |
| `TooLong` | La source entière, plus longue que la limite de la politique |
| `TooDeep` | La source entière, dont les blocs et les fonctions s'imbriquent plus profondément que la limite de la politique |
| `Nesting` | Une règle imbriquée dans une règle de style au-delà des niveaux d'imbrication de la politique, avec ce qu'elle contient |

`RemovalReason::describe()` donne quelques mots pour un message : `an @import rule`, `a property that runs code`.

## Ce qu'une valeur peut contenir

Des noms, des nombres, des dimensions, des pourcentages, des chaînes, des dièses, des virgules, les délimiteurs
`/ + - *`, des parenthèses et des crochets, et les fonctions que la politique permet, dont les arguments sont vérifiés de
la même façon. Un deux-points, un point-virgule, un mot-clé @, un bloc `{}`, tout autre délimiteur, une chaîne invalide ou
une URL invalide font refuser la déclaration. `url()`, avec ou sans guillemets, et chaque image d'`image-set()` passent
par le réécrivain ([URL](urls.md)). `var()` est permis seulement avec les propriétés personnalisées.

`attr()` prend un nom d'attribut et des noms de types. Le type `url` est permis seulement quand `attr()` est le premier
argument de `target-counter()`, `target-counters()` ou `target-text()`. Là, il nomme l'élément vers lequel pointe un
lien, et ne charge rien.

```css
a::after { content: " (page " target-counter(attr(href url), page) ")" }   /* kept */
a::after { content: attr(href url) }                                       /* removed */
```

## La sortie

La sortie est écrite par le [sérialiseur](syntax.md) : les noms sont échappés, les chaînes prennent des guillemets
doubles, les URL s'écrivent `url("…")`, les blancs deviennent une seule espace. Elle ne contient jamais `</` suivi
d'une lettre, pour ne pas pouvoir fermer l'élément `<style>` dans lequel elle est écrite. Dans un attribut `style`,
échappez-la comme toute valeur d'attribut ; Twig le fait par défaut.
