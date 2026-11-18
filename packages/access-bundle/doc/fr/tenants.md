<!-- phil-dev source-sha256=988ee6118626720a7fbe98524478ae731fe1e38dd3847b499580f469301b2715 -->
# Locataires

Dans une application multilocataire, chaque locataire a son propre espace `main`, ses propres membres et ses propres
éléments, souvent sous les mêmes identifiants que ceux d'un autre locataire. Le bundle conserve les tuples de chaque
locataire dans une partition à part, pour qu'un administrateur, un membre, un partage ou un groupe d'un locataire
n'accorde jamais rien dans un autre.

## La partition

| `phil_access.partition` | Les tuples sont |
|---|---|
| `auto`, la valeur par défaut | Partitionnés par locataire quand `PhilTenancyBundle` est activé ; dans une seule partition autrement. |
| `tenant` | Partitionnés par le locataire actif de `phil/tenancy-bundle`. Le bundle doit être activé. |
| `single` | Dans une seule partition, quels que soient les bundles activés. |

Avec le bundle de locataires, `Infrastructure\Tenancy\TenancyTupleScope` lit le locataire que contient son
`TenantContext` : celui que la requête a résolu, que porte l'estampille du message, ou que la ligne de commande donne
avec `--tenant`. Chaque vérification, liste, partage, adhésion et purge agit alors dans ce locataire seulement. Une
autre couche de locataires crée un alias de `Phil\ResourcePermissions\Contract\TupleScope` vers sa propre portée ; voir
[les partitions de `phil/resource-permissions`](https://github.com/parler-haut-interagir-librement/resource-permissions/blob/main/doc/fr/partitions.md).

Une référence de locataire est le nom de la partition tel quel : de 1 à 128 octets. Une référence plus longue est
refusée à la lecture ou à l'écriture des tuples.

## Hors de tout locataire

Une fois partitionnés par locataire, les tuples ne sont ni lus ni écrits hors d'un locataire : le magasin lance
`UnscopedTuples`. Une vérification échoue alors plutôt que de répondre pour aucun locataire en particulier, et un voter
qui la rencontre laisse la requête échouer. Donnez son locataire à chaque commande et à chaque *handler* de message :

```sh
bin/console phil:access:purge --tenant=retail
```

`phil:access:purge` sans locataire le dit et échoue, sans rien supprimer.

## Purger tous les locataires

`--all-tenants` purge en une seule exécution les tuples expirés de chaque locataire, la forme que prend une purge
planifiée :

```sh
bin/console phil:access:purge --all-tenants
```

La commande lit les partitions qui contiennent des tuples depuis `Application\Contract\TuplePartitions`, puis, pour
chacune à tour de rôle, entre dans le locataire de ce nom par le `TenantContext` de `phil/tenancy-bundle`, purge sa
partition et en sort. Chaque instruction nomme toujours une seule partition : aucune purge ne s'exécute hors d'un
locataire ni sur plusieurs locataires. On sort du locataire après sa purge, qu'elle ait réussi ou échoué, y compris
quand l'activation échoue à l'entrée, afin qu'un échec n'emporte jamais le filtre Doctrine, la connexion ou l'espace de
noms de cache d'un locataire dans le suivant.

Un tableau rend compte de chaque locataire : les tuples expirés supprimés, et `purged`, ou `failed:` suivi du message de
l'erreur, imprimé comme du texte. Un locataire qui échoue n'arrête pas les autres ; la commande se termine alors avec
`1`, après avoir purgé tous les locataires qu'elle a pu. Une partition dont le nom n'est pas une référence de locataire
valide est signalée comme un échec et jamais ouverte. Sans aucune partition qui contienne des tuples, la commande le dit
et réussit.

| La commande | Réponse |
|---|---|
| Les tuples ne sont pas partitionnés par locataire : `phil/tenancy-bundle` désactivé, ou `partition: single` | Refusée, sortie `1` : l'option exige les partitions des locataires |
| `--all-tenants` avec `--tenant` | Refusée, sortie `1` : donnez l'un ou l'autre |
| Aucun service `TuplePartitions` : les tuples en mémoire, ou dans un magasin propre à l'application | Refusée, sortie `1` : créez un alias du port vers un service qui liste les partitions |

Avec le magasin DBAL, `Infrastructure\Dbal\DbalTuplePartitions` les liste : un seul `SELECT DISTINCT` sur la colonne
`scope` de la table des tuples, qui ouvre sa clé primaire, sans la partition par défaut. Il ne lit que les noms, jamais
un tuple. Sur une grande table, PostgreSQL peut lire tout l'index de cette clé pour cela, là où MySQL le parcourt par
sauts ; à une exécution par jour, c'est la partie peu coûteuse de la purge. Une application dont les locataires sont
connus ailleurs — un registre de locataires — crée un alias du port vers un service à elle qui retourne les partitions,
par nom, chacune une fois.

## Mise à niveau depuis 0.1

La table des tuples de `phil/resource-permissions` 0.2 a une colonne `scope`, en tête de sa clé et de ses index. Avec
l'ORM, `doctrine:migrations:diff` écrit la migration, grâce à l'écouteur de schéma du bundle ; les lignes déjà là
passent à la partition par défaut, celle que lit `partition: single`. Une application multilocataire fait ensuite
passer les lignes de chaque locataire à la partition du locataire avant de mettre `partition` à `tenant`, ou laisse
`auto` le faire en activant le bundle de locataires. Les étapes, et les instructions sans l'ORM, se trouvent dans
[Mise à niveau depuis 0.1](https://github.com/parler-haut-interagir-librement/resource-permissions/blob/main/doc/fr/partitions.md#mise-à-niveau-depuis-01).
