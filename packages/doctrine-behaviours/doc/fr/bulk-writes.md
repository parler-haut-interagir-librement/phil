<!-- phil-dev source-sha256=a48ed5998d183a5bc20ebe39298410fbf619fe7e6881c4378155ec794cf9ed20 -->
# Écritures en bloc

Les comportements sont des écouteurs de Doctrine : ils voient ce qui passe par l'unité de travail d'un gestionnaire
d'entités sur lequel ils sont enregistrés, et rien d'autre. Une écriture qui la contourne ne garde aucun horodatage,
aucun auteur, aucun slug, aucune version, et n'est jamais supprimée en douceur ; un effacement qui la contourne doit
atteindre lui-même chaque lieu de stockage.

## Ce qu'aucun écouteur ne voit

| Écriture | Ce que font les comportements |
|---|---|
| Un `UPDATE` ou un `DELETE` en DQL | Rien : `updated_at` et `updated_by` gardent leurs valeurs, un `DELETE` supprime les lignes pour de bon, supprimables en douceur ou non, et aucune version n'est créée |
| Une instruction DBAL, `executeStatement()`, `insert()`, `update()`, `delete()` | Rien, pour la même raison |
| Une migration | Rien : les lignes qu'elle écrit ou supprime restent comme elle les laisse |
| Un gestionnaire d'entités construit hors du conteneur, ou un second gestionnaire d'entités | Seuls les écouteurs enregistrés sur ce gestionnaire s'exécutent ; un gestionnaire qui n'en a aucun écrit comme le fait DBAL |
| Un déclencheur de base de données, une clé étrangère `ON DELETE CASCADE` ou `SET NULL` | Rien : la base change les lignes dans le dos de Doctrine |

Un `DELETE` en DQL d'entités supprimables en douceur retire donc leurs lignes, filtre ou non, et un `UPDATE` en DQL d'un
enregistrement `Versionable` figé le change sur place, son instantané sans nouvelle version. Chargez et écrivez les
entités quand les comportements doivent s'appliquer ; écrivez en bloc quand ils ne le doivent pas, et gardez à la main
ce qu'ils auraient écrit.

`BlameEraser`, `OwnerEraser` et `VersionEraser` écrivent en bloc exprès : un effacement n'est pas une modification des
entités, et aucun écouteur n'horodate les lignes qu'ils changent. Avec un `ManagerRegistry`, ils joignent chaque
gestionnaire d'entités, chaque table une fois par connexion, si bien qu'aucun second gestionnaire n'est oublié ; un
gestionnaire que le registre ne donne pas, ou un lieu de stockage qui n'est la table d'aucune classe mappée, est à
l'application d'effacer.

## `postFlush` n'est pas après la validation

`postFlush` s'exécute une fois que l'unité de travail a écrit ses instructions, dans la transaction d'un
`wrapInTransaction()` ou d'un `beginTransaction()` englobant s'il y en a un. Un écouteur qui y envoie un message,
indexe un document ou appelle un service agit sur des lignes qui peuvent encore être annulées, et un échec après lui
laisse le monde extérieur en avance sur la base de données. Le travail qui doit suivre la validation passe par une boîte
d'envoi écrite dans la même transaction, ou par un message de Messenger envoyé une fois la transaction terminée.
`TreeListener` n'emploie `postFlush` que pour oublier les déplacements du `flush`.

## L'auteur copie un nom

`BlameListener` écrit dans `updated_by` le nom que donne le résolveur à chaque modification, et dans `created_by` à
chaque insertion. Avec Symfony Security, ce nom est `getUserIdentifier()` sauf indication contraire du bundle, souvent
une adresse courriel. Un effacement qui charge et écrit des entités, un outil d'effacement propre à l'application par
exemple, écrit de nouveau ce nom après que `BlameEraser` l'a remplacé : exécutez-le là où personne n'est authentifié, un
worker ou une commande de console, ou dans `BlameSuspension::during()` — voir
[Horodatage et auteur](timestamps-and-blame.md#lauteur-pendant-un-effacement).

## Le slug copie sa source

`SlugListener` fait un slug une seule fois, à partir de `slugSource()`, et le garde. Un slug tiré du nom d'une personne,
`ada-lovelace`, est un renseignement personnel qui survit à un changement du nom, et à l'effacement du nom : le slug est
dans l'URL, dans les journaux et dans les liens que d'autres gardent. Tirez le slug de l'enregistrement d'une personne
de quelque chose qui ne nomme personne, un identifiant ou un pseudonyme choisi, ou donnez à l'enregistrement un nouveau
slug avec `assignSlug()` quand son nom est effacé.

## La suppression douce garde la ligne

Une première `remove()` d'une entité `SoftDeletable` garde toute sa ligne, avec `deleted_at` renseigné : un effacement
qui supprime une telle entité n'a rien effacé. `SoftDeletes::purge()` supprime la ligne — voir
[Suppression douce](soft-delete.md).
