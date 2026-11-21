<!-- phil-dev source-sha256=c02468278d8b7d2da50c3f3fb59af210d0c70f090fe1d952f0623d3d8e0215c9 -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent avec `ext-sodium` et `ext-openssl` | Les classes en lecture seule, les constantes typées ; libsodium pour le KMS local `sodium://` et le base64 des valeurs, OpenSSL pour l'AES-256-GCM des enveloppes |
| `symfony/key-management` 8.2 | Le chiffrement par enveloppe par un KMS, celui de l'application ou un KMS local ; le composant est expérimental |
| `doctrine/orm` 3.7, `doctrine/collections` 3.1 | La table des secrets |
| `symfony/polyfill-php86` | L'énumération `SortDirection` sur PHP 8.5 |
| `symfony/config`, `symfony/dependency-injection` 8.2 | La configuration et les services |
| `psr/clock` | Le moment où les lignes et les événements sont écrits |
| `psr/log` | L'auditeur journaliseur facultatif, et les échecs de la révocation auprès du service |
| `phil/privacy-bundle` (facultatif) | L'exportation et l'effacement avec le compte |
| `symfony/console` (facultatif) | Les commandes |
| `symfony/lock` (facultatif) | Un seul processus à la fois actualise un jeton |

L'application fournit le service `clock` (le `ClockInterface` de Symfony), qu'enregistre le cadriciel complet, et
DoctrineBundle pour le gestionnaire d'entités ; avec `log_events`, le service `logger`. Avec `symfony/lock` et `framework.lock` configuré, les actualisations
s'exécutent sous la fabrique de verrous de FrameworkBundle.

Le composant KeyManagement de Symfony est expérimental dans Symfony 8.2 : son API peut changer dans une version
mineure, hors de la promesse de rétrocompatibilité de Symfony. Le bundle ne l'appelle que depuis un seul adaptateur, et
son format scellé nomme l'algorithme de chaque valeur : un changement du composant est donc un changement de cet
adaptateur.

## Installer

```sh
composer require phil/user-secrets-bundle
```

La recette Flex du bundle enregistre les deux bundles, écrit `config/packages/key_management.yaml` et
`config/packages/phil_user_secrets.yaml` comme le montre la section suivante, et ajoute à `.env` une variable
`USER_SECRETS_KEY_2026` vide, à remplir d'une clé maîtresse générée. Sans Symfony Flex, enregistrez les bundles :

```php
// config/bundles.php
return [
    // …
    Symfony\Component\KeyManagement\KeyManagementBundle::class => ['all' => true],
    Phil\UserSecretsBundle\PhilUserSecretsBundle::class => ['all' => true],
];
```

## Le KMS et sa clé maîtresse

Le bundle scelle par un client du composant KeyManagement de Symfony. Sans KMS à elle, une application déclare le client
`sodium://` local du composant : générez une clé maîtresse et conservez-la là où l'application conserve ses autres
secrets — une variable d'environnement définie
par l'hébergeur, ou le coffre de secrets de Symfony —, jamais dans le dépôt :

```sh
bin/console phil:user-secrets:generate-key
bin/console secrets:set USER_SECRETS_KEY_2026
```

```yaml
# config/packages/key_management.yaml
key_management:
    clients:
        local: 'sodium://?keys[k2026]=%env(USER_SECRETS_KEY_2026)%'

# config/packages/phil_user_secrets.yaml
phil_user_secrets:
    current_key: k2026
```

Pour sceller plutôt par le KMS de l'application — AWS, Azure, Google Cloud, Vault —, déclarez son client sous
`key_management.clients` et nommez une de ses clés maîtresses dans `current_key` ; avec plusieurs clients, nommez dans
`kms` celui qui scelle, comme `key_management.vault`.

Perdre une clé maîtresse fait perdre chaque secret qu'elle a scellé : sauvegardez-la à l'écart de la base de données.
[Chiffrement et rotation des clés](encryption.md) explique comment la remplacer.

## La table

Le bundle déclare lui-même le mappage de sa ligne pour Doctrine : aucune entrée `mappings` à écrire. Créez la table avec
les migrations de l'application :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

La table `phil_user_secret` a pour clé `owner` et `name` ; elle contient `service`, `kind`, `key_id`, `ciphertext`,
`format`, `expires_at`, `created_at`, `updated_at`, `last_used_at` et `revoked_at`.

### Les instants en UTC

Chaque instant que le bundle enregistre est écrit et relu en UTC, quel que soit le fuseau horaire du processus PHP —
`phil_user_secret` : `expires_at`, `created_at`, `updated_at`, `last_used_at`, `revoked_at`. Les colonnes sont associées au type `datetime_utc_immutable` et les requêtes qui les comparent passent le même
type : un serveur web, une commande de la console et un worker de Messenger qui tournent dans des fuseaux horaires
différents lisent le même instant, et une expiration ne peut pas se décaler de quelques heures. Le type SQL est celui de
`datetime_immutable` : le schéma ne change donc pas. Un instant relu est en UTC ; affichez-le dans le fuseau horaire du
lecteur, comme le fait le filtre `date` de Twig.

#### Migrer depuis 0.1

Une base écrite par 0.1 garde chaque instant dans le fuseau horaire du processus qui l'a écrit. Quand ce fuseau était
UTC, il n'y a rien à faire. Sinon, convertissez les lignes une seule fois, application arrêtée pour qu'aucune ligne ne
soit écrite sous les deux formes, en donnant le fuseau dans lequel les processus tournaient — `America/Toronto` ici :

```sql
-- MySQL and MariaDB: named zones need the time zone tables (mysql_tzinfo_to_sql).
UPDATE phil_user_secret SET
    expires_at = CONVERT_TZ(expires_at, 'America/Toronto', 'UTC'),
    created_at = CONVERT_TZ(created_at, 'America/Toronto', 'UTC'),
    updated_at = CONVERT_TZ(updated_at, 'America/Toronto', 'UTC'),
    last_used_at = CONVERT_TZ(last_used_at, 'America/Toronto', 'UTC'),
    revoked_at = CONVERT_TZ(revoked_at, 'America/Toronto', 'UTC');
-- PostgreSQL
UPDATE phil_user_secret SET
    expires_at = (expires_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC',
    created_at = (created_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC',
    updated_at = (updated_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC',
    last_used_at = (last_used_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC',
    revoked_at = (revoked_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC';
```

La nuit où l'on recule les horloges, l'heure entre 01 h 00 et 02 h 00 se produit deux fois et une heure locale
enregistrée ne dit pas laquelle c'était : une ligne de cette heure peut finir avec une heure d'écart. Quand les
processus écrivaient dans des fuseaux horaires différents, on ne peut pas distinguer les lignes : enregistrez de nouveau les identifiants dont l'expiration compte.
