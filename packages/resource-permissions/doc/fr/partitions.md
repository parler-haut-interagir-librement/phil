<!-- phil-dev source-sha256=4570b6a2a439aa08d71d27cbcec8e6d9219bdb46237a6871c147bab2c7fb9507 -->
# Partitions

Un magasin conserve ses tuples dans des partitions : une par locataire d'une application multilocataire, ou la
partition par défaut seule. Chaque appel d'un magasin lit, écrit, oublie et purge dans une seule partition, celle que
nomme à ce moment sa `Contract\TupleScope`, et n'en voit jamais une autre. Deux locataires peuvent alors utiliser les
mêmes identifiants — `space:main`, `doc:42`, `member:ann` — sans que le tuple de l'un accorde jamais rien sur la
ressource de même nom de l'autre.

## La portée

```php
interface TupleScope
{
    public function partition(): string;
}
```

| Portée | Partition |
|---|---|
| `SinglePartition` | `Partition::DEFAULT`, le nom vide : chaque tuple dans une seule partition. Un magasin construit sans portée l'utilise. |
| `FixedPartition` | Le nom avec lequel elle a été construite : un test, une importation, une tâche qui s'exécute pour un seul locataire. |
| La vôtre | Le locataire de la requête, du message ou de la commande, lu à chaque appel. |

`phil/access-bundle` en fournit une sur le locataire actif de `phil/tenancy-bundle`. Toute autre couche de locataires
n'en demande que quelques lignes :

```php
use Phil\ResourcePermissions\Contract\TupleScope;
use Phil\ResourcePermissions\Exception\UnscopedTuples;

final readonly class AppTenantScope implements TupleScope
{
    public function __construct(private AppTenants $tenants)
    {
    }

    public function partition(): string
    {
        return $this->tenants->current()?->id ?? throw UnscopedTuples::because('No tenant is active.');
    }
}

$store = new DbalTupleStore($connection, $clock, scope: new AppTenantScope($tenants));
```

Un magasin interroge la portée à chaque appel, avant toute instruction : un service construit une seule fois sert donc
chaque locataire d'un *worker* de longue durée. Gardez la portée sans état propre : lisez le locataire là où la
requête, le message ou la commande l'ont mis.

## L'échec par fermeture

Dans une application multilocataire, un appel fait hors de tout locataire ne doit pas retomber sur une partition : la
portée lance `Exception\UnscopedTuples`, et le magasin ne lit ni n'écrit rien. Une vérification échoue alors au lieu de
répondre, une écriture laisse la table telle quelle, et `purgeExpired()` ne supprime rien. Donnez son locataire à une
commande avant qu'elle touche aux tuples, et à un *handler* de message le locataire de son message.

## La règle d'un nom

Une partition est `Partition::DEFAULT` (vide), ou de 1 à 128 octets d'UTF-8 sans caractère de contrôle. Les noms se
comparent octet par octet : `Tenant-A` n'est pas `tenant-a`. Un magasin refuse avec `InvalidRelation` un nom contraire à
la règle. Les 128 octets gardent la clé primaire du magasin en base de données dans la limite de MySQL ; une couche de
locataires dont les références peuvent être plus longues les ramène à un nom stable plus court, comme une empreinte.

## Dans la base de données

La table a une colonne `scope`, en tête de la clé primaire et de chaque index, et chaque instruction la nomme. Sa
valeur par défaut est le nom vide : une ligne écrite sans elle appartient donc à la partition par défaut. Voir
[Stockage](storage.md).

## Mise à niveau depuis 0.1

La version 0.1 conservait les tuples sans partitions. La table de 0.2 ajoute la colonne `scope`, fait commencer la clé
primaire et les deux index par elle, et garde les noms de types et de relations en ASCII sur MySQL et MariaDB pour que
la clé reste dans les 3 072 octets. Chaque ligne déjà là passe à la partition par défaut, par la valeur par défaut de
la colonne.

1. Arrêtez les écrivains de la table, ou faites la mise à niveau dans une fenêtre de maintenance : sur MySQL et
   MariaDB, la clé primaire est supprimée puis ajoutée de nouveau, dans des instructions qui ne forment pas une seule
   transaction.
2. Mettez la table à la nouvelle définition :
   - avec l'ORM et `Dbal\TupleSchemaListener`, `doctrine:migrations:diff` écrit la migration ;
   - sinon, `DbalTupleStore::schemaUpdate()` donne les instructions, pour une migration à vous :

   ```php
   foreach ($store->schemaUpdate() as $statement) {
       $connection->executeStatement($statement);
   }
   ```

   `schemaUpdate()` crée la table quand elle manque, la modifie quand elle est plus ancienne, et ne donne rien quand
   elle est à jour.
3. Une application à un seul locataire a fini : sa portée est `SinglePartition`, qui lit la partition par défaut.
4. Une application multilocataire fait passer les lignes de chaque locataire de la partition par défaut à celle du
   locataire, avec les identifiants qu'elle sait appartenir à ce locataire — par exemple, `UPDATE phil_relation_tuple
   SET scope = 'retail' WHERE scope = '' AND object_id LIKE 'retail-%'` —, puis donne à ses magasins une portée sur le
   locataire. Une ligne laissée dans la partition par défaut n'est lue par personne dès que chaque appel nomme un
   locataire.
