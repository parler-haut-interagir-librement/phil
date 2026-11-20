<!-- phil-dev source-sha256=d3c2358dcd596ee30d61957a38417cb1a99c3d2b089b25a1f58d4986580edc66 -->
# Mises à niveau

## De 0.2 à 0.3

### Les champs personnels des versions

`phil_record_version` a une colonne `redacted_at`, nullable, de type `datetime_utc_immutable` : l'instant où un
effacement a retiré les champs personnels d'une version. `doctrine:migrations:diff` écrit la migration qui l'ajoute.

`Versions` laisse de côté par défaut les champs que nomme `PersonalFields`, avec `PersonalFieldPolicy::Exclude` ; sans
`PersonalFields`, il garde chaque champ, comme avant. Les versions écrites avant contiennent encore ces champs en clair :
retirez-les une fois, après la migration, avec `VersionEraser::stripPersonalFields()`, ou avec la commande du bundle
`phil:doctrine-behaviours:strip-versions` :

```php
$eraser = new VersionEraser($registry, $personalFields, $clock);
$eraser->stripPersonalFields(dryRun: true);   // how many versions hold some
$eraser->stripPersonalFields();               // taken out; a sealed value stays
```

L'enregistrement peut encore revenir à ces versions, avec ses propres valeurs pour les champs retirés. Une application
qui a effacé des personnes avant cette version exécute `VersionEraser::redact()` pour chacune d'elles, si leurs versions
leur sont encore liées par `owner_id` ou `created_by` ; une fois l'auteur ou le propriétaire remplacé,
`stripPersonalFields()` est ce qui atteint ces versions.

### Les outils d'effacement

`BlameEraser` et `OwnerEraser` prennent un gestionnaire d'entités ou un `ManagerRegistry` ; avec le registre, ils
joignent chaque gestionnaire d'entités. Ils écrivent chaque connexion dans une transaction, évincent les régions du
cache de second niveau des classes qu'ils changent et détachent les entités gérées qui gardent l'ancienne valeur : faites
un `flush` avant un effacement, puisque ce qu'une telle entité avait en attente est abandonné.

### Collation des clés

`BinaryKeysListener` est dans la bibliothèque : une application qui l'utilise sans son bundle l'enregistre sur
`loadClassMetadata`, comme le montre [Intégration](integration.md#collation-des-clés). Sur MySQL et MariaDB,
`doctrine:migrations:diff` écrit alors la migration qui amène les clés à `utf8mb4_bin` ; sur PostgreSQL et SQLite, le
diff est vide. Les colonnes de jointure vers un identifiant daté prennent sa collation et sont converties avec lui, et
MySQL refuse de changer un côté d'une clé étrangère avant l'autre : mettez `SET FOREIGN_KEY_CHECKS = 0` au début du
`up()` de la migration et `SET FOREIGN_KEY_CHECKS = 1` à sa fin.

Le changement ne fait que rendre les valeurs plus distinctes : aucune ligne existante ne brise donc une clé unique, mais
les lignes dont les clés ne diffèrent que par la casse, qu'une recherche par l'une ou l'autre graphie rendait ensemble,
sont dès lors trouvées chacune par sa propre graphie. Avant de migrer, listez les propriétaires et les auteurs écrits
sous plus d'une casse, pour chaque table qui les prend :

```sql
SELECT owner_id, COUNT(*) AS row_count FROM notice
GROUP BY owner_id HAVING COUNT(DISTINCT CAST(owner_id AS BINARY)) > 1;
```

Gardez-les distincts, ou ramenez les lignes à la graphie qu'emploie l'application avec un `UPDATE` avant la migration.

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
