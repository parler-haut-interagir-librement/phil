<!-- phil-dev source-sha256=9b22eb4d6f2aec805320acb010c0c3f4bd6e3f6e936b391e317f5bd2a1ed1491 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `ext-mbstring`, `ext-openssl` et `ext-sodium` ;
- Symfony 8.2 ;
- phil/identity-provider-bundle, avec son module `external` activé et Symfony Security configuré comme le dit son
  guide d'installation ;
- `web-token/jwt-library`, exigé par ce paquet : les pièces OpenID Connect de Symfony vérifient les jetons de Google
  avec elle.

## Le client chez Google

Dans la console Google Cloud, sous **APIs & Services → Credentials**, créez un **OAuth client ID** de type
**Web application** :

- **Authorized redirect URIs** : la fonction de rappel du bundle, `https://example.com/login/with/google/callback`,
  telle que le routeur la génère — l'adresse doit correspondre au caractère près ;
- **Authorized JavaScript origins** : aucune n'est nécessaire.

Sur l'**OAuth consent screen**, les portées que demande le paquet sont `openid` et `email`, et `profile` quand
`profile: true` est configuré. Ce sont des portées non sensibles : Google n'exige aucune vérification pour elles.

Gardez l'identifiant client dans l'environnement et le secret client dans les secrets de Symfony :

```sh
php bin/console secrets:set GOOGLE_CLIENT_SECRET
```

## Le paquet

```sh
composer require phil/identity-provider-google
```

Avec Symfony Flex, la recette enregistre le bundle, écrit `config/packages/phil_identity_provider_google.yaml` et
`config/routes/phil_identity_provider_google.yaml`, et ajoute `GOOGLE_CLIENT_ID` à `.env`. Sans elle, enregistrez le
bundle et écrivez la configuration :

```php
// config/bundles.php
return [
    // ...
    Phil\IdentityProviderBundle\PhilIdentityProviderBundle::class => ['all' => true],
    Phil\IdentityProviderGoogle\PhilIdentityProviderGoogleBundle::class => ['all' => true],
];
```

```yaml
# config/packages/phil_identity_provider_google.yaml
phil_identity_provider_google:
    client_id: '%env(GOOGLE_CLIENT_ID)%'
    client_secret: '%env(GOOGLE_CLIENT_SECRET)%'
```

Le module `external` du bundle doit être activé :

```yaml
# config/packages/phil_identity_provider.yaml
phil_identity_provider:
    modules:
        external: true
```

## Les routes

La connexion utilise les routes du bundle : `/login/with/google` et `/login/with/google/callback`. Le paquet ajoute une
route à lui, le point de terminaison de la [Cross-Account Protection](risc.md), qui ne répond que lorsque `risc.enabled`
est activé :

```yaml
# config/routes/phil_identity_provider_google.yaml
phil_identity_provider_google:
    resource: '@PhilIdentityProviderGoogleBundle/config/routes.php'
    prefix: /identity/google
```

## Le pare-feu

L'`ExternalAuthenticator` du bundle répond à la fonction de rappel ; il figure déjà parmi les authentificateurs
personnalisés du pare-feu quand le bundle est installé. Le point de terminaison RISC est appelé par Google, sans
session : laissez-le à l'accès anonyme.

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/identity/google/risc$, roles: PUBLIC_ACCESS }
```

## Mode worker

Les services ne gardent rien d'une requête à l'autre : les documents de découverte lus pendant une requête sont oubliés
par `kernel.reset`, et le client HTTP oublie les adresses qu'il a résolues. Les documents et les clés restent dans le
bassin de cache.
