<!-- phil-dev source-sha256=17d2001111fa184416dfe0308aa86e6dde3956a53f5665d66b64b5afc5afd4b9 -->
# Points d'extension

La bibliothèque est du PHP simple et ne déclare aucun service. Ce qu'une application ajoute, ce sont des genres
d'adresse et les hôtes de ses propres services.

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

## Hôtes de service

`Phil\Url\ServiceHosts` — les hôtes sur lesquels `ServiceUrl` lit une adresse, tirés de la configuration de
l'application. Voir [Adresses de service](service-addresses.md).

| Point | Comment |
|---|---|
| Quels hôtes sont des services | Les arguments de `new ServiceHosts(...)` : des noms sans domaine, des noms d'une zone privée, des adresses IP ; 256 au plus |
| D'où vient la liste | Un paramètre ou une variable d'environnement, jamais un formulaire ni une requête |
| La partager | Une seule instance pour l'application : elle est immuable et ne garde rien entre les requêtes |
