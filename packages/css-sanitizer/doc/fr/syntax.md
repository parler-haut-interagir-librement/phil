<!-- phil-dev source-sha256=43db9ad346c4d115a4ce0875bb1eacc754352fb4c61ffdfc2d9f9206d7ad0852 -->
# Syntaxe

L'analyseur lexical, l'analyseur et le sérialiseur sont publics et peuvent servir sans l'assainisseur : pour lire du
CSS comme le fait un navigateur, pour en vérifier la structure ou pour le réécrire.

## Analyseur lexical

`Syntax\Tokenizer::tokenize()` suit la section 4 de CSS Syntax Level 3. L'entrée est prétraitée — CR LF, CR et FF
deviennent LF, NULL devient U+FFFD — et les octets qui ne sont pas de l'UTF-8 deviennent U+FFFD. Il renvoie une liste
de `Token`, dont le dernier est de type `Eof`. Les commentaires sont écartés.

```php
use Phil\CssSanitizer\Syntax\Tokenizer;

foreach (new Tokenizer()->tokenize('a { width: 10px }') as $token) {
    echo $token->type->value, ' ';
}
// ident whitespace { whitespace ident colon whitespace dimension whitespace } EOF
```

Un `Token` a un `type` (`TokenType`) et une `value` aux échappements résolus : le nom d'un identifiant, d'une fonction,
d'un mot-clé @ ou d'un dièse, le texte d'une chaîne ou d'une URL, le caractère d'un délimiteur, la représentation d'un
nombre telle qu'elle est écrite. Il a aussi l'`unit` d'une dimension, `isId` pour un dièse qui pourrait être un
identifiant, `isInteger` pour un nombre, et sa `sourceLine`.

## Analyseur

`Syntax\Parser` suit la section 5 :

| Méthode | Renvoie |
|---|---|
| `parseStylesheet($css)` | Un `Stylesheet` de `QualifiedRule`, `AtRule` et `Malformed` |
| `parseDeclarationList($css)` | Des `Declaration`, `AtRule` et `Malformed`, comme dans un attribut `style` |
| `parseComponentValues($css)` | Des valeurs de composants : `Token`, `SimpleBlock`, `FunctionValue` |
| `rules($values, $topLevel)` | Les règles de valeurs de composants, comme celles d'un bloc |
| `declarations($values)` | Les déclarations de valeurs de composants, comme celles d'un bloc |
| `blockContents($values)` | Des `Declaration`, `AtRule`, `QualifiedRule` et `Malformed`, comme CSS Nesting lit le bloc d'une règle de style |

Le bloc d'une règle garde ses valeurs de composants. Qu'elles contiennent des règles ou des déclarations dépend de la
règle : l'appelant les lit donc avec `rules()`, `declarations()` ou `blockContents()`. `blockContents()` lit un élément
qui commence par un nom comme une déclaration, sauf s'il n'a pas de deux-points, ou s'il a un bloc `{}` à côté d'autres
valeurs dans une propriété qui n'est pas personnalisée. Il lit alors une règle imbriquée, dont le prélude se termine à
son bloc ou, comme erreur d'analyse, à un point-virgule. Il lit un bloc en un temps linéaire en sa longueur. Ce que la
spécification écarte comme erreur d'analyse — une déclaration sans son deux-points, une règle coupée par la fin de la
source — est gardé sous forme de `Malformed`, avec son contenu et sa ligne.

`new Parser(maxDepth: 64)` borne l'imbrication des blocs et des fonctions ; au-delà, il lève `NestingTooDeep`. La
méthode statique `Parser::trim()` retire les blancs aux extrémités de valeurs de composants, et
`Parser::splitAtCommas()` les coupe aux virgules de leur niveau supérieur.

## Sérialiseur

`Serializer` réécrit le CSS :

| Méthode | Écrit |
|---|---|
| `componentValues($values)` | Des valeurs de composants |
| `declarations($declarations)` | `name: value; …`, comme les contient un attribut `style` |
| `styleSheet($sheet)` | Un `Sheet\StyleSheet` : ses règles `@import`, puis une règle après l'autre, deux espaces d'indentation par niveau, les déclarations d'une règle avant ses règles imbriquées |
| `Serializer::identifier($name)` | Un nom, échappé comme CSSOM sérialise les identifiants |
| `Serializer::string($text)` | Une chaîne entre guillemets doubles |

Les nombres gardent la représentation avec laquelle ils ont été écrits. Un commentaire vide s'insère entre deux jetons
qui se reliraient comme d'autres jetons — un identifiant et un nombre, un nombre et un identifiant qui ressemble à une
unité —, comme l'exige la section 9. L'exception : là où un nombre signé ne peut pas se joindre à ce qui le précède,
pour que `U+0000-00FF` reste tel qu'il est écrit. Relire la sortie donne les mêmes valeurs. Un `<` dans un nom ou une
chaîne s'écrit `\3c `, et un délimiteur `<` est tenu à l'écart d'un `/` qui le suit : la sortie ne commence jamais une
balise de fin.
