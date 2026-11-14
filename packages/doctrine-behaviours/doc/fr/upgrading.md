<!-- phil-dev source-sha256=d9a1171098b491e64bdbd34cd12d215616784910f9f97002d400e1e475cbb52a -->
# Mises à niveau

## De 0.1 à 0.2

### Les instants en UTC

Les colonnes qui marquent un instant ont le type DBAL `datetime_utc_immutable` au lieu de `datetime_immutable` :

| Trait ou entité | Colonnes |
|---|---|
| `TimestampableEntity` | `created_at`, `updated_at` |
| `SoftDeletableEntity` | `deleted_at` |
| `DeactivatableEntity` | `deactivated_at` |
| `PublishableEntity` | `published_at`, `unpublished_at` |
| `Version\RecordVersion` | `frozen_at` de `phil_record_version` |

**Le schéma ne change pas.** Les deux types déclarent la même colonne sur chaque plateforme — `DATETIME` sur MySQL,
MariaDB et SQLite, `TIMESTAMP(0) WITHOUT TIME ZONE` sur PostgreSQL —, et DBAL 4 ne garde aucun commentaire de type : donc
`doctrine:migrations:diff` n'écrit aucune migration et une table existante continue de fonctionner telle quelle.

**Ce que contient une colonne est maintenant lu comme de l'UTC.** Avec `datetime_immutable`, DBAL écrivait l'heure
murale du `DateTimeImmutable` qu'on lui donnait, sans son fuseau, et la relisait dans le fuseau horaire par défaut de
PHP. Avec `datetime_utc_immutable`, il écrit l'instant en UTC et le relit en UTC, quels que soient les fuseaux.

- Un horodatage que les écouteurs ont écrit en 0.1 était déjà en UTC : ces lignes n'ont besoin de rien. Une application
  dont le fuseau horaire par défaut de PHP n'est pas l'UTC les lisait décalées de son écart en 0.1, et les lit
  correctement en 0.2.
- Un instant que l'application s'est donné elle-même — `stampCreated()`, `stampUpdated()`, `markDeleted()`,
  `stampDeactivated()`, `markPublished()` ou `markUnpublished()` appelés avec un `DateTimeImmutable` qui n'était pas en
  UTC — a été écrit dans son propre fuseau. Ces lignes sont lues en 0.2 comme si elles étaient en UTC, décalées de
  l'écart de ce fuseau. Convertissez-les une fois, dans la migration de la mise à niveau, depuis le fuseau où elles ont
  été écrites :

```sql
-- PostgreSQL
UPDATE article SET created_at = (created_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC'
WHERE created_at < '2026-11-01';

-- MySQL and MariaDB, with the time zone tables loaded
UPDATE article SET created_at = CONVERT_TZ(created_at, 'America/Toronto', 'UTC')
WHERE created_at < '2026-11-01';
```

La condition est celle de l'application : elle seule sait quelles lignes un import ou une commande a écrites avec des
dates données. Une ligne convertie deux fois est fausse du double de l'écart : la migration s'exécute donc une fois et
reste dans l'historique des migrations.

**Un horodatage garde en mémoire le fuseau horaire de l'horloge.** Les écouteurs donnent à l'entité l'instant de
l'horloge tel quel, et DBAL le convertit en l'écrivant. `createdAt()` juste après le vidage est
`2026-11-04T08:00:00-05:00` pour une horloge à Toronto, et `2026-11-04T13:00:00+00:00` une fois relu : le même instant.
Le code qui formatait un horodatage sans fuseau horaire, en s'attendant à de l'UTC, le convertit avec `setTimezone()` ;
une comparaison de deux instants emploie `<`, `>` ou leurs horodatages, jamais `==`.

Une entité qui redéfinit une de ces colonnes avec un `datetime_immutable` à elle, par `AttributeOverride` ou par son
propre mappage, garde l'ancien type pour cette colonne : donnez-lui aussi `datetime_utc_immutable`.

### Prérequis

`doctrine/collections` 3.1 ou plus récent, où `Tree` trie par le `\SortDirection` natif, et `symfony/polyfill-php86`, qui
apporte `\SortDirection` à PHP 8.5.
