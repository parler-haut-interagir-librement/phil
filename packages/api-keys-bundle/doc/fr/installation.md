<!-- phil-dev source-sha256=3afc54e7198a0a821e018dbe77e04d7cb4e6f83373d73f6f8a5a8cf89c9d8815 -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent, avec les extensions OpenSSL et Sodium.
- Symfony 8.2 : FrameworkBundle, SecurityBundle, et les composants `security-core` et `security-http`.
- Doctrine ORM 3.7 avec DoctrineBundle, pour le stockage `doctrine`.
- Facultatifs : `symfony/console` pour les commandes; `symfony/form`, `symfony/security-csrf` et `twig/twig` pour la
  page des membres; `phil/api-contract-bundle` pour les détails de problème; `phil/rate-limit-bundle` pour les limites
  de débit par jeton, avec `phil/usage-meter-bundle` pour les quotas sur des compteurs; `phil/ip-anonymizer` pour
  l'adresse de la dernière utilisation; `symfony/http-client`, `symfony/cache` et `phil/url` pour le point de
  terminaison de l'analyse de secrets de GitHub.

## Enregistrer le bundle

```sh
composer require phil/api-keys-bundle
```

```php
// config/bundles.php
return [
    // ...
    Phil\ApiKeysBundle\PhilApiKeysBundle::class => ['all' => true],
];
```

## La recette

Avec Symfony Flex, la recette enregistre le bundle, écrit `config/packages/phil_api_keys.yaml` avec le préfixe `pat` et
la clé des empreintes lue dans `API_KEYS_HASH_KEY`, et ajoute cette variable, vide, à `.env` : remplissez-la comme
ci-dessous. La page des membres et ses routes restent désactivées tant que l'application ne les active pas. La recette
se trouve dans le dépôt de recettes de PHIL, lu dès que l'application nomme ce dépôt dans `extra.symfony.endpoint` ;
sans lui, enregistrez le bundle et écrivez la configuration à la main.

## La clé

Chaque jeton est gardé sous forme de HMAC-SHA-256 de son identifiant et de son secret, sous une clé d'au moins
32 octets. Gardez-la dans le coffre de secrets, jamais dans le dépôt :

```sh
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;' | bin/console secrets:set API_KEYS_HASH_KEY -
```

```yaml
# config/packages/phil_api_keys.yaml
phil_api_keys:
    prefix: acme
    hash_key: '%env(API_KEYS_HASH_KEY)%'
```

Pour changer la clé, mettez la nouvelle en premier et gardez la précédente après elle : les nouveaux jetons sont hachés
sous la première clé, et les jetons émis sous la précédente continuent de fonctionner. Retirez la clé précédente une
fois `max_lifetime` écoulé, quand chaque jeton émis sous elle a expiré :

```yaml
phil_api_keys:
    hash_key: ['%env(API_KEYS_HASH_KEY)%', '%env(API_KEYS_PREVIOUS_HASH_KEY)%']
```

Retirer une clé fait refuser chaque jeton émis sous elle.

## La table

Le bundle associe l'entité `ApiToken` pour Doctrine, à la table `phil_api_token`, indexée par propriétaire et par
expiration. Générez la migration comme pour n'importe quelle entité :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

### Les instants en UTC

Chaque instant que le bundle enregistre est écrit et relu en UTC, quel que soit le fuseau horaire du processus PHP —
`phil_api_token` : `created_at`, `expires_at`, `last_used_at`, `revoked_at`. Les colonnes sont associées au type `datetime_utc_immutable` et les requêtes qui les comparent passent le même
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
UPDATE phil_api_token SET
    created_at = CONVERT_TZ(created_at, 'America/Toronto', 'UTC'),
    expires_at = CONVERT_TZ(expires_at, 'America/Toronto', 'UTC'),
    last_used_at = CONVERT_TZ(last_used_at, 'America/Toronto', 'UTC'),
    revoked_at = CONVERT_TZ(revoked_at, 'America/Toronto', 'UTC');
-- PostgreSQL
UPDATE phil_api_token SET
    created_at = (created_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC',
    expires_at = (expires_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC',
    last_used_at = (last_used_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC',
    revoked_at = (revoked_at AT TIME ZONE 'America/Toronto') AT TIME ZONE 'UTC';
```

La nuit où l'on recule les horloges, l'heure entre 01 h 00 et 02 h 00 se produit deux fois et une heure locale
enregistrée ne dit pas laquelle c'était : une ligne de cette heure peut finir avec une heure d'écart. Quand les
processus écrivaient dans des fuseaux horaires différents, on ne peut pas distinguer les lignes : renouvelez les jetons dont l'expiration compte.

## Le pare-feu

L'API se trouve derrière un pare-feu sans état dont l'authentificateur `access_token` nomme le gestionnaire de jetons et
l'extracteur du bundle, et les routes qui ont besoin d'un jeton exigent un utilisateur authentifié :

```yaml
# config/packages/security.yaml
security:
    firewalls:
        api:
            pattern: ^/api/
            stateless: true
            provider: app_users
            access_token:
                token_handler: phil_api_keys.token_handler
                token_extractors: [phil_api_keys.token_extractor]
                realm: api
    access_control:
        - { path: ^/api/, roles: IS_AUTHENTICATED_FULLY }
```

Le fournisseur d'utilisateurs du pare-feu charge l'utilisateur à partir du propriétaire du jeton : l'identifiant
d'utilisateur du membre qui l'a créé. Avec `phil/api-contract-bundle`, nommez `phil_api_keys.problem_responder` comme
`entry_point` du pare-feu et comme `failure_handler` de l'authentificateur pour obtenir des détails de problème : voir
[Authentification et portées](authentication.md#les-réponses).

## La page des membres

Activez-la et importez ses routes derrière le pare-feu des membres connectés : voir
[La page des membres](members-page.md).

## L'analyse de secrets de GitHub

Facultativement, GitHub révoque les jetons qu'il trouve dans des dépôts publics par un point de terminaison de
l'application : voir [Analyse de secrets](secret-scanning.md).

## La purge

Exécutez `phil:api-keys:purge-expired` chaque jour, depuis le planificateur ou cron, pour supprimer les jetons expirés
ou révoqués au-delà de la rétention : voir [Console](console.md).
