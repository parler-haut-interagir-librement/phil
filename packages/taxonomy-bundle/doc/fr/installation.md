<!-- phil-dev source-sha256=99ac6b7a2680aeb04b3e0d03e3ce6b7ba4cc2661dda4c82d68aa0c7847fe1ebc -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare le bundle |
| `ext-intl`, `ext-mbstring` | toute version | La langue de la requête ; les noms comptés en caractères |
| `doctrine/orm` | ^3.7 | Les tables |
| `symfony/form`, `symfony/options-resolver` | ^8.2 | Les types de formulaire |
| `phil/slug` | ^0.1 | Les *slugs* des noms d'étiquettes et les clés de catégorie |
| `phil/resource` | ^0.1 | `ResourceReference`, la ressource à laquelle une étiquette ou une catégorie est attribuée |
| `symfony/uid` | ^8.2 | Les identifiants |
| `symfony/dependency-injection`, `symfony/config` | ^8.2 | La classe du bundle et sa configuration |

```sh
composer require phil/taxonomy-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\TaxonomyBundle\PhilTaxonomyBundle::class => ['all' => true],
];
```

```yaml
# config/packages/phil_taxonomy.yaml
phil_taxonomy:
    fallback_locale: en   # the language of a name when there is none in the language asked
    slugger: ~            # the service id of the slugger of names and keys; ~ for the default one
```

Avec DoctrineBundle, le bundle associe ses entités ; les migrations de l'application créent quatre tables :

| Table | Colonnes |
|---|---|
| `phil_taxonomy_tag` | `id`, `name` (100), `slug` (128, unique) |
| `phil_taxonomy_category` | `id`, `category_key` (128, unique), `parent` (suppression restreinte), `path` (420, indexé), `position` |
| `phil_taxonomy_category_translation` | `id`, `category` (suppression en cascade), `locale` (16), `name` (150), `description` ; unique sur la catégorie et la locale |
| `phil_taxonomy_assignment` | `id`, `term_type` (`tag` ou `category`), `term_id`, `resource_kind` (64), `resource_id` (255) ; unique sur les quatre, indexé sur la ressource |

## Collation des clés

**Les clés se comparent octet par octet**, comme PHP les compare. Une étiquette, une catégorie ou une ressource se
trouve par sa clé telle qu'elle a été écrite : l'étiquette `Doc-A` n'est jamais trouvée pour `doc-a`, et les deux slugs
peuvent être pris ; les termes de la ressource `Doc-A` ne sont jamais listés pour `doc-a`.
`BinaryKeysListener` donne à ces champs la collation binaire quand l'ORM charge leurs métadonnées sur MySQL ou
MariaDB ; l'association elle-même reste la même sur toutes les plateformes.

| Table | Colonnes en `utf8mb4_bin` sur MySQL et MariaDB |
|---|---|
| `phil_taxonomy_tag` | `id`, `slug` |
| `phil_taxonomy_category` | `id`, `category_key`, `parent`, `path` |
| `phil_taxonomy_category_translation` | `id`, `category`, `locale` |
| `phil_taxonomy_assignment` | `id`, `term_type`, `term_id`, `resource_kind`, `resource_id` |

Sur MySQL et MariaDB, ces colonnes prennent le jeu de caractères `utf8mb4` et la collation `utf8mb4_bin`, selon la
plateforme de la connexion : la collation par défaut de MySQL 8, `utf8mb4_0900_ai_ci`, ne distingue ni la casse ni les
accents. Les collations par défaut de PostgreSQL sont déterministes, égales seulement quand les octets le sont, et
SQLite compare les octets : leurs colonnes gardent la valeur par défaut.

### Mise à niveau depuis 0.1

Les tables d'une installation 0.1 sur MySQL ou MariaDB gardaient la collation de la connexion.
`doctrine:migrations:diff` écrit la migration qui amène les colonnes ci-dessus à `utf8mb4_bin` ; exécutez-la avec
`doctrine:migrations:migrate`. Sur PostgreSQL et SQLite, le diff est vide.

Les colonnes de jointure `parent` et `category` sont converties avec les identifiants qu'elles référencent, et MySQL refuse de
changer un côté d'une clé étrangère avant l'autre. Mettez `SET FOREIGN_KEY_CHECKS = 0` au début du `up()` de la
migration et `SET FOREIGN_KEY_CHECKS = 1` à sa fin ; les clés étrangères restent, et sont vérifiées de nouveau une fois
les deux côtés convertis :

```php
public function up(Schema $schema): void
{
    $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
    // The statements doctrine:migrations:diff wrote.
    $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
}
```

Le changement ne fait que distinguer davantage les valeurs : aucune ligne existante ne brise donc une clé unique.
Il sépare ce que la collation insensible à la casse réunissait : les lignes dont les clés ne diffèrent que par la
casse, qu'une recherche par l'une ou l'autre graphie retournait ensemble, se trouvent chacune par leur propre graphie
dès la migration, et une clé que l'application cherche dans une autre casse que celle où elle l'a écrite cesse de
correspondre. Avant de migrer, listez les clés écrites en plus d'une casse :

```sql
SELECT resource_kind, resource_id, COUNT(*) AS row_count FROM phil_taxonomy_assignment
GROUP BY resource_kind, resource_id HAVING COUNT(DISTINCT CAST(resource_id AS BINARY)) > 1;
```

Chaque ligne est une clé sous plusieurs graphies. Gardez-les distinctes, ou ramenez les lignes à la graphie
qu'utilise l'application avec un `UPDATE` avant la migration. Une clé unique ne peut contenir deux telles graphies sous
l'ancienne collation, donc les colonnes d'une clé unique n'ont pas besoin de vérification.
