<!-- phil-dev source-sha256=7883bfddfd933f1dcb49b14a6e5a198f3579d18ede8da85105a837885fe7d833 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

## Configurés par l'application

| Type | Ce qu'il règle |
|---|---|
| `Limits` | Les octets, la profondeur, les parties, les champs d'en-tête et les paramètres lus dans un message |
| `Part::fieldGroups($groups, $fields)` | Combien de groupes de champs d'un rapport sont lus, et combien de champs dans chacun |
| `Headers::mailboxes($name, $limit)` | Combien de boîtes sont lues dans les champs d'un nom |

## Construits par l'application

`Part::create()` construit une partie à partir de valeurs déjà décodées — en-têtes, type, paramètres, corps, parties
enfants, message contenu — pour un test, ou pour du code qui lit des parties sans message brut. `Headers` et
`HeaderValue` se construisent de la même façon à partir de leurs valeurs.

## Sur quoi on peut compter

- `read()` ne lance jamais d'exception sur un message, et tout texte qu'il donne est de l'UTF-8 valide.
- `MimeReader` ne garde aucun état entre les appels : une instance sert chaque requête et chaque worker.
- L'arbre d'une partie et ses valeurs sont immuables ; décoder deux fois un corps donne les mêmes octets.
- `Part::read()` est le constructeur propre au lecteur, pas un point d'extension.
