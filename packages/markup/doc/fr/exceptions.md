<!-- phil-dev source-sha256=cda77e022a33eb229f4377e43952294d90fe65b5ae50cc8372f05abd7746b2f1 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Phil\Markup\Exception\MarkupException`.

| Exception | Étend | Lancée quand |
|---|---|---|
| `InvalidSource` | `InvalidArgumentException` | Une source UJO n'est pas du JSON, enfreint la structure d'un document, ou contient un bloc d'un type inconnu ou dont les données enfreignent son type |
| `MissingExtension` | `LogicException` | `AmmoniaPurifier::defaultPolicies()` est appelé là où l'extension `ammonia` de PHP n'est pas chargée ; `$extension` la nomme |
| `UnknownFormat` | `InvalidArgumentException` | On demande à un compilateur un format qu'aucun lecteur ne connaît ; ou un module complémentaire donne à `DefaultCompiler` un nom qui est déjà un format, ou qui n'est pas fait de 1 à 16 lettres minuscules, chiffres et traits d'union commençant par une lettre |

Le message nomme le format et ce qui ne va pas, par exemple `The ujo source cannot be read: …`, suivi du message de
`phil/ujo`, gardé comme exception précédente. `UnknownFormat` nomme le format et les formats que connaît le compilateur,
`The format "textile" is not known; use one of: markdown, html, …`, et les garde dans `$known`.

Les lecteurs des formats qu'écrivent les personnes (Markdown, BBCode, les wikis) ne lancent jamais d'exception : tout
texte est un document, et une construction mal formée est du texte. `DefaultCompiler::compile()` et `compileFor()`
lancent `InvalidSource` pour le seul format `ujo` ; le HTML et le texte brut n'en lancent jamais non plus.
