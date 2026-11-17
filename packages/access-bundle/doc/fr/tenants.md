<!-- phil-dev source-sha256=d073f9f0fb3afc7a222dbf1bef3db914e9334a7909e4da3407b1ad66456fae98 -->
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

`phil:access:purge` sans locataire le dit et échoue, sans rien supprimer. Une purge planifiée s'exécute une fois par
locataire.

## Mise à niveau depuis 0.1

La table des tuples de `phil/resource-permissions` 0.2 a une colonne `scope`, en tête de sa clé et de ses index. Avec
l'ORM, `doctrine:migrations:diff` écrit la migration, grâce à l'écouteur de schéma du bundle ; les lignes déjà là
passent à la partition par défaut, celle que lit `partition: single`. Une application multilocataire fait ensuite
passer les lignes de chaque locataire à la partition du locataire avant de mettre `partition` à `tenant`, ou laisse
`auto` le faire en activant le bundle de locataires. Les étapes, et les instructions sans l'ORM, se trouvent dans
[Mise à niveau depuis 0.1](https://github.com/parler-haut-interagir-librement/resource-permissions/blob/main/doc/fr/partitions.md#mise-à-niveau-depuis-01).
