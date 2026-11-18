<!-- phil-dev source-sha256=bf84cc9f0a26a63bd5c954eda136fedb57a577822c34738aba33670c3518429c -->
# Clés et stockage

## L'empreinte à clé

`CodeHasher` transforme un code en ce que conserve le magasin : le HMAC-SHA-256 de sa forme canonique sous la clé de
l'application, écrit `$hmac-sha256$v=1$` suivi de 64 chiffres hexadécimaux minuscules, soit 81 caractères. L'hexadécimal
se compare de la même façon sous une collation insensible à la casse, celle par défaut de MySQL. Une copie de la table
n'ouvre rien sans la clé.

```php
use Phil\AccessCodes\CodeHasher;

$hasher = new CodeHasher($activeKey, $previousKey);
$hasher->hash('7KQ2M9XDH4TBH');         // under the active key
$hasher->candidates('7KQ2M9XDH4TBH');   // under every key, the active one first
$hasher->matches($stored, '7KQ2M9XDH4TBH');
```

## Faire tourner la clé

Les clés forment une liste ordonnée : la première calcule l'empreinte des codes des nouveaux lots, et chaque clé
retrouve un code. Pour faire tourner la clé, mettez la nouvelle en premier et gardez l'ancienne après elle ; les lots
créés sous l'ancienne clé continuent de fonctionner. Une fois que tous les lots créés sous une clé ont expiré ou ont
été révoqués, retirez cette clé. Chaque clé fait au moins 32 octets, huit clés au plus, aucune donnée deux fois.

Un code est recherché par son empreinte sous chaque clé à la fois. La recherche passe par un index de base de données,
ou une table de hachage en mémoire, dont la durée ne dit rien d'un code puisque l'empreinte est à clé ; `matches()`
compare chaque clé avec `hash_equals()`.

## Les magasins

| Magasin | Pour |
|---|---|
| `InMemoryCodeStore` | Les tests et les scripts qui créent et échangent dans une même exécution |
| `Dbal\DbalCodeStore` | Deux tables d'une connexion Doctrine DBAL sur PostgreSQL, MySQL, MariaDB ou SQLite |

### Les tables

```php
use Phil\AccessCodes\Dbal\DbalCodeStore;

$store = new DbalCodeStore($connection);   // phil_access_code_batch and phil_access_code
$store = new DbalCodeStore($connection, 'shop_code_batch', 'shop_code');
```

`configureSchema(Schema $schema): Schema` ajoute les tables à un schéma qui ne les a pas : appelez-la depuis une
migration Doctrine, ou depuis un écouteur de l'événement `postGenerateSchema` de l'ORM pour que
`doctrine:migrations:diff` les écrive.

| Table | Colonnes |
|---|---|
| `phil_access_code_batch` | `id` (clé), `name`, `size`, `created_at`, `expires_at`, `revoked_at` |
| `phil_access_code` | `hash` (clé), `batch_id` (indexée), `expires_at`, `redeemed_at`, `redeemed_by`, `revoked_at` |

Les instants sont des horodatages Unix. L'expiration est copiée dans la ligne de chaque code, pour qu'un échange lise une
seule ligne. Un lot est ajouté en une seule transaction, ses codes insérés 500 lignes par instruction ; une révocation
marque le lot et ses codes non échangés en une seule transaction. Un nom de table est vérifié avant tout SQL : jusqu'à
63 lettres minuscules, chiffres ou `_`, sans commencer par un chiffre.

`redeemed_by` contient l'identifiant stable de la personne qui échange, jamais une adresse. Effacer les données d'un
membre, c'est supprimer ou vider ses lignes selon cet identifiant.

### Collation des clés

**Les clés se comparent octet par octet**, comme PHP les compare. Un identifiant de lot peut mêler les casses : `Spring`
et `spring` sont deux lots, et révoquer ou compter l'un ne touche jamais les codes de l'autre. Les empreintes sont en
hexadécimal minuscule, qui se compare de la même façon sous toute collation ; elles prennent tout de même la collation
binaire, pour que la clé de la table ne dépende jamais du serveur.

| Table | Colonnes en `utf8mb4_bin` sur MySQL et MariaDB |
|---|---|
| `phil_access_code_batch` | `id` |
| `phil_access_code` | `hash`, `batch_id` |

Sur MySQL et MariaDB, ces colonnes prennent le jeu de caractères `utf8mb4` et la collation `utf8mb4_bin`, selon la
plateforme de la connexion : la collation par défaut de MySQL 8, `utf8mb4_0900_ai_ci`, ignore la casse et les accents.
Les collations par défaut de PostgreSQL sont déterministes, égales seulement quand les octets le sont, et SQLite compare
les octets : leurs colonnes gardent la valeur par défaut.

#### Mise à niveau depuis 0.1

Les tables d'une installation 0.1 sur MySQL ou MariaDB gardaient la collation de la connexion.
`doctrine:migrations:diff` écrit la migration qui amène les colonnes ci-dessus à `utf8mb4_bin` ; exécutez-la avec
`doctrine:migrations:migrate`. Le changement ne fait que distinguer davantage les valeurs : aucune ligne existante ne
brise une clé. Les lignes qu'une collation insensible à la casse avait déjà fusionnées restent fusionnées ; les valeurs
écrites à partir de la migration sont gardées distinctes. Sur PostgreSQL et SQLite, le diff est vide.
