<!-- phil-dev source-sha256=24ee3c8697f10230d80741ac6333340ebc74626000e05bcc24164b27f38f936d -->
# Exceptions

Chaque exception de la bibliothèque implémente `Phil\Markup\Exception\MarkupException`.

| Exception | Étend | Lancée quand |
|---|---|---|
| `InputTooLarge` | `InvalidArgumentException` | Une source est plus longue que le plafond du lecteur ou de `DefaultCompiler` à qui on la donne (`maxBytes`, 1 Mio par défaut, 4 Mio pour UJO), ou un HTML plus long que le plafond du purificateur (`maxBytes`, 4 Mio par défaut) ; `$bytes` et `$limit` donnent les deux longueurs |
| `InputTooComplex` | `InvalidArgumentException` | Une source Markdown compte plus de lignes que le budget de son lecteur (`maxLines`, 100 000 par défaut) ; `$budget` nomme le budget, `lines`, et `$limit` sa valeur |
| `InvalidSource` | `InvalidArgumentException` | Une source UJO n'est pas du JSON, enfreint la structure d'un document, ou contient un bloc d'un type inconnu ou dont les données enfreignent son type |
| `MissingExtension` | `LogicException` | `AmmoniaPurifier::defaultPolicies()` est appelé là où l'extension `ammonia` de PHP n'est pas chargée ; `$extension` la nomme |
| `UnknownFormat` | `InvalidArgumentException` | On demande à un compilateur un format qu'aucun lecteur ne connaît ; ou un module complémentaire donne à `DefaultCompiler` un nom qui est déjà un format, ou qui n'est pas fait de 1 à 16 lettres minuscules, chiffres et traits d'union commençant par une lettre |

Le message nomme le format et ce qui ne va pas, par exemple `The ujo source cannot be read: …`, suivi du message de
`phil/ujo`, gardé comme exception précédente. `UnknownFormat` nomme le format et les formats que connaît le compilateur,
`The format "textile" is not known; use one of: markdown, html, …`, et les garde dans `$known`.

Les lecteurs des formats qu'écrivent les personnes (Markdown, BBCode, les wikis) ne lancent jamais d'exception à cause de
ce que dit une source : tout texte est un document, et une construction mal formée est du texte.
`DefaultCompiler::compile()` et `compileFor()` lancent `InvalidSource` pour le seul format `ujo` ; le HTML et le texte
brut n'en lancent jamais non plus.

Chaque lecteur, et `DefaultCompiler` dans tous les formats, refuse par `InputTooLarge` une source plus longue que son
plafond, `The input is 1048577 bytes long, above the limit of 1048576 bytes.`, avant d'en lire un octet : une source
n'est jamais coupée pour y tenir. Voir [Le plafond d'une source](analyser.md#le-plafond-dune-source). Chaque purificateur refuse de la même façon un HTML plus
long que son plafond, avant qu'un assainisseur le voie : voir
[Le plafond d'un HTML purifié](reference.md#le-plafond-dun-html-purifie). Le lecteur Markdown refuse par
`InputTooComplex`, `The input holds more than 100000 lines.`, une source qui compte plus de lignes que son budget,
avant de les construire : voir [Le budget de lignes de Markdown](analyser.md#le-budget-de-lignes-de-markdown).
