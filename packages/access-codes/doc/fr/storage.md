<!-- phil-dev source-sha256=3271b6d2681c67c66a40521138309c00e028f63f1cc5f0cd1c6583059d0e4ebb -->
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
