<!-- phil-dev source-sha256=21f58db35340c89e8135462912e268bd03cab022e5369206aa73081719a2be83 -->
# Points d'extension

La bibliothèque est du PHP simple et ne déclare aucun service. Ce qu'une application ajoute, ce sont des genres
d'adresse.

## `UrlType`

`Phil\Url\UrlType` : un genre d'adresse est un cas d'une enum qui l'implémente. Voir [Genres d'adresse](kinds.md).

| Méthode | Contrat |
|---|---|
| `accepts(Url $url): bool` | Si l'adresse, déjà normalisée, est de ce genre ; lit son hôte et son chemin par `Url`, sans jamais faire de requête vers elle |

Une enum propre à l'application (le registre des entreprises d'une région, le portail de son gouvernement) déclare ses
cas les plus précis en premier : la détection prend le premier cas qui accepte.

## Détection

| Point | Comment |
|---|---|
| Les genres acceptés | Les `EnumSet` donnés à `UrlTypes::detect()` et à `UrlTypes::type()` : les genres propres à l'application, `KnownUrlType`, ou quelques cas de l'un ou l'autre |
| Le genre qui l'emporte | L'ordre des ensembles : ceux de l'application avant `KnownUrlType`, pour que ses genres l'emportent sur `Website` et sur la lecture de `/@name` par `Mastodon` |
| Refuser une adresse d'aucun genre énuméré | Omettez le fourre-tout `Website` |

Un genre est gardé par sa classe d'enum et son cas partout où une adresse est stockée avec lui : renommer ou retirer un
cas que les données nomment encore est une migration des données.
