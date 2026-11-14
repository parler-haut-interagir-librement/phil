<!-- phil-dev source-sha256=5b70d0fb3d0bf4519e257f1e4aba34e46176dff61603fc57125ea42fa9294fa3 -->
# Les règles

Une règle est un document JsonLogic : une constante, une liste ou un objet contenant une opération,
`{"operator": [operands]}`. Une opération dont les opérandes ne forment pas une liste prend la valeur comme seul
opérande : `{"!": true}` se lit `{"!": [true]}`. La bibliothèque lit un sous-ensemble strict de JsonLogic et refuse
tout le reste à la lecture de la règle, sans jamais l'ignorer.

## Les opérateurs

| Opérateur | Opérandes | Valeur |
|---|---|---|
| `var` | un nom, ou un nom et une valeur par défaut | La valeur de la réponse sous ce nom ; la valeur par défaut, sinon `null`, quand il n'en a pas |
| `missing` | 1 nom ou plus | Les noms sans valeur — absents, `null` ou `""` —, en liste |
| `missing_some` | un nombre, puis une liste de noms | `[]` dès que ce nombre de noms ont une valeur, sinon les noms qui n'en ont pas |
| `==`, `!=` | 2 | Égaux ou non, les types convertis comme `==` de JavaScript les convertit |
| `===`, `!==` | 2 | Égaux et du même type, ou non |
| `<`, `<=` | 2, ou 3 | Avant (ou égal) ; avec trois opérandes, celui du milieu entre les bornes |
| `>`, `>=` | 2 | Après (ou égal) |
| `!` | 1 | Si l'opérande est faux |
| `!!` | 1 | Si l'opérande est vrai |
| `and` | 1 ou plus | Le premier opérande faux, sinon le dernier |
| `or` | 1 ou plus | Le premier opérande vrai, sinon le dernier |
| `if` | une condition et une valeur, répétées, puis une valeur facultative | La valeur de la première condition vraie, sinon la dernière valeur, sinon `null` |
| `in` | une aiguille et une liste ou un texte | Si la liste contient un élément strictement égal à l'aiguille, ou si le texte contient l'aiguille écrite en texte |
| `+`, `*` | 1 ou plus | La somme, le produit |
| `-` | 1 ou 2 | La différence, ou l'opposé d'un opérande |
| `/`, `%` | 2 | Le quotient, le reste du signe du premier opérande |
| `min`, `max` | 1 ou plus | Le plus petit, le plus grand |

Un nom est composé de 1 à 8 segments de lettres, de chiffres et de `_`, joints par des points, le premier commençant par
une lettre ou `_`, chacun d'au plus 64 caractères. Un nom pointé lit dans une table, `guest.name`, ou une liste,
`days.0`.

## Les valeurs

Une valeur est `null`, `true` ou `false`, un nombre, un texte ou une liste de valeurs. Un nombre est un double, comme
en JavaScript : un nombre entier jusqu'à 2^53 est gardé comme entier, un plus grand comme le double le plus proche, `-0`
comme `0`. Dans la réponse, un objet, une table au bout d'un nom, un nombre non fini ou toute autre chose se lit comme
`null`.

`null`, `false`, `0`, `""` et `[]` sont faux ; toute autre valeur est vraie, `"0"` et `[0]` compris.

## Les conversions

Les comparaisons convertissent leurs opérandes comme JavaScript, avec une exception : un texte est lu comme un nombre
en notation décimale seulement.

- **Un texte comme nombre.** Les espaces ASCII autour sont ignorés ; puis un signe facultatif, des chiffres avec un
  point décimal facultatif, et un exposant facultatif : `" 12 "`, `"-.5"`, `"1e3"`. Un texte vide, ou
  fait d'espaces seulement, est `0`. Tout autre texte — `"0x10"`, `"Infinity"`, `"12px"`, `"1_000"` — n'est pas un
  nombre, et n'est ni égal, ni avant, ni après quoi que ce soit.
- **Les autres valeurs comme nombres.** `null` et `false` sont `0`, `true` est `1`, une liste est lue comme son texte.
- **Une valeur comme texte.** `null`, `true`, `false` ; un nombre dans sa forme la plus courte, en notation à
  exposant à partir de 10^21 et en dessous de 10^-6 (`1e+21`, `1e-7`) ; une liste comme ses éléments joints par des
  virgules, les éléments `null` laissés vides.
- **`==`.** `null` n'égale que `null`. Un booléen est comparé comme son nombre ; un nombre et un texte, comme des
  nombres ; une liste et un scalaire, comme le texte de la liste ; deux listes ne sont jamais égales.
- **`<` et les autres ordres.** Deux textes sont ordonnés par leurs unités de code UTF-16, comme JavaScript les trie,
  de sorte qu'un jour écrit `YYYY-MM-DD` s'ordonne dans le temps ; toute autre paire est comparée comme des nombres.
- **Arithmétique.** Chaque opérande est lu comme un nombre. Un résultat qui n'est pas un nombre fini — un texte qui
  n'est pas un nombre, une division ou un reste par zéro, un dépassement — vaut `null`.

Ces règles sont les mêmes en PHP et dans le navigateur ; [Tests](testing.md#les-cas-communs) fait passer une suite de
cas par les deux, et aussi les cas de test que publie JsonLogic, chaque différence étant listée avec sa raison
([Tests](testing.md#les-cas-que-jsonlogic-publie)).

## Les limites

`JsonLogicReader` prend un `Limits` :

| Limite | Valeur par défaut | |
|---|---|---|
| `maxBytes` | 65 536 | Octets du JSON |
| `maxDepth` | 32 | Opérations imbriquées les unes dans les autres |
| `maxNodes` | 1 000 | Constantes, variables, listes et opérations |
| `maxListItems` | 500 | Éléments d'une liste, noms d'un `missing` |
| `maxTextLength` | 10 000 | Caractères d'un texte, qui est de l'UTF-8 valide |

Dans ces limites, une règle est lue, vérifiée et évaluée en un temps proportionnel à sa taille : aucun opérateur ne
boucle, et chaque nœud est évalué une fois au plus.

## Ce qui est refusé

- Tout opérateur hors du sous-ensemble, dont `cat`, `substr`, `merge`, `map`, `filter`, `reduce`, `all`, `none`,
  `some`, `log`, `method` et `?:` ; un objet de plusieurs clés, ou d'aucune.
- `{"var": ""}`, qui lirait toute la réponse, une variable nommée par un nombre, un nom qui ne respecte pas le motif
  ci-dessus, un `var` de plus de deux opérandes.
- Une opération au mauvais nombre d'opérandes ; des noms de `missing` qui ne sont pas des textes ; un nombre de
  `missing_some` qui n'est pas un entier de 0 au nombre de noms.
- Un nombre non fini ; une règle hors des limites ; du JSON invalide.

Une règle refusée lance `Phil\FormLogic\Exception\InvalidLogic`, dont le message nomme le premier problème.
