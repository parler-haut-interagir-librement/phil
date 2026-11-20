<!-- phil-dev source-sha256=1db44f284ef22c959bf28ff730d10c98ac648d74f432bf4aa70b6fc9bcd7013f -->
# Installation

```sh
composer require phil/identity-provider-bundle
```

La recette Flex du bundle l'enregistre, écrit `config/packages/phil_identity_provider.yaml` avec chaque module
facultatif désactivé, et `config/routes/phil_identity_provider.yaml` avec les routes des modules activés. La recette se
trouve dans le dépôt de recettes de PHIL, lu une fois que l'application déclare ce dépôt dans
`extra.symfony.endpoint` ; sans lui, enregistrez le bundle et écrivez la configuration à la main, comme ci-dessous. Le
pare-feu reste à écrire par l'application.

Le paquet exige PHP 8.5 ou une version plus récente avec les extensions `mbstring`, `openssl` et `sodium`, Symfony 8.2
ou une version plus récente, Doctrine ORM 3.7 ou une version plus récente et `web-token/jwt-library` 4.2 ou une version
plus récente ; le composant Rate Limiter compte les
inscriptions et, avec `login_limiter`, les connexions par identifiant. Le composant Mailer envoie ses courriels, le
composant Notifier ses messages texte, le composant KeyManagement chiffre ses secrets, StimulusBundle sert son
contrôleur de clés d'accès, et Symfony UX Twig Component avec les recettes du kit dessine ses pages : chacun est
facultatif. Les modules `totp` et `oidc` gardent des secrets, et
refusent de démarrer sans `secrets.kms_key` sauf si `secrets.allow_plaintext_secrets: true` permet de les garder en
clair.

## Le bundle

```php
// config/bundles.php
Phil\IdentityProviderBundle\PhilIdentityProviderBundle::class => ['all' => true],
```

Le bundle associe à Doctrine les tables des modules activés et préconfigure le hacheur de mots de passe de ses
utilisateurs : Argon2id (`sodium`). Générez la migration des tables avec `bin/console doctrine:migrations:diff`.

## Mise à niveau depuis 0.2

- Sur MySQL et MariaDB, les clés des tables prennent la collation binaire : lancez `bin/console doctrine:migrations:diff`,
  lisez l'`ALTER TABLE … MODIFY … CHARACTER SET utf8mb4 COLLATE utf8mb4_bin` qu'elle écrit pour les colonnes listées dans
  [Tables](reference.md#tables), puis migrez. Les lignes que l'ancienne collation prenait pour une seule — `App` et
  `app` — restent deux lignes. PostgreSQL et SQLite n'exigent aucune migration.
- Réglez `public_origin` ou `framework.trusted_hosts`, comme [ci-dessus](#où-mènent-les-liens) : sans l'un ou l'autre,
  l'environnement `prod` ne démarre plus.
- L'inscription est limitée par défaut ; `registration: true` et `registration: false` continuent de fonctionner, et
  `registration: { rate_limit: false }` désactive les limites.
- Avec le module `oidc`, le schéma gagne la table `phil_identity_assertion`, les colonnes `jwks`, `resources`,
  `backchannel_logout_uri` et `sector_identifier` de `phil_identity_client`, et la colonne `audience` de
  `phil_identity_token`, dont `account_id` devient nullable : lancez `bin/console doctrine:migrations:diff` et migrez.
  Chaque nouvelle colonne est nullable ; aucune ligne ne change.
- Un jeton d'actualisation émis dans une session est refusé une fois la session terminée, sa famille révoquée
  (`oidc.refresh_token_bound_to_session`, activée par défaut) ; réglez-la à `false` pour garder l'ancien comportement.
- `TokenController::AUTH_METHODS` a disparu : le document de découverte lit les méthodes dans
  `oidc.client_authentication.methods`. `IssuedToken::accountId()` retourne null pour un jeton de l'octroi
  `client_credentials`, et `GrantRepository` gagne `useAssertion()` : une implémentation maison l'ajoute.
  `Tokens::bearer()` donne un troisième membre, le client du jeton.

## Où mènent les liens

Les liens des courriels ne doivent jamais prendre l'hôte qu'une requête nomme. Réglez l'adresse canonique du site, ou
les hôtes auxquels il répond, ou les deux :

```yaml
# config/packages/phil_identity_provider.yaml
phil_identity_provider:
    public_origin: '%env(APP_PUBLIC_ORIGIN)%'   # https://example.com

# config/packages/framework.yaml
framework:
    trusted_hosts: ['^example\.com$']
```

Dans l'environnement `prod`, le bundle refuse de démarrer quand ni l'un ni l'autre n'est réglé : voir [Où mènent les
liens](accounts.md#où-mènent-les-liens).

## Le pare-feu

Le pare-feu nomme le fournisseur d'utilisateurs et le vérificateur d'utilisateurs du bundle, le formulaire de
connexion de l'application et les authentificateurs des modules activés :

```yaml
# config/packages/security.yaml
security:
    providers:
        accounts:
            id: phil_identity_provider.user_provider
    firewalls:
        main:
            lazy: true
            provider: accounts
            user_checker: phil_identity_provider.user_checker
            form_login:
                login_path: app_login
                check_path: app_login
                enable_csrf: true
            login_link:                       # the module login_link
                check_route: phil_identity_provider_login_link_check
                signature_properties: [userIdentifier, passwordChangedAt]
                lifetime: 600
                max_uses: 1
            custom_authenticators:
                - Phil\IdentityProviderBundle\Infrastructure\Security\PasskeyAuthenticator     # passkeys
                - Phil\IdentityProviderBundle\Infrastructure\Security\OneTimeCodeAuthenticator # totp
                - Phil\IdentityProviderBundle\Infrastructure\Security\ExternalAuthenticator    # external
            entry_point: form_login
            re_authentication_entry_point: phil_identity_provider.re_authentication_entry_point
            recent_authentication_lifetime: 600
            logout:
                path: app_logout
            login_throttling:
                limiter: phil_identity_provider.login_limiter   # with login_limiter.enabled; else max_attempts: 5
```

`login_throttling` limite les tentatives devant chaque authentificateur : par défaut par adresse et par identifiant et
adresse, et avec le limiteur du bundle aussi par identifiant quelle que soit l'adresse (voir [Limiter les
connexions](accounts.md#limiter-les-connexions), avec ce que `framework.trusted_proxies` doit dire derrière un
mandataire) ; le verrou du bundle compte en plus les échecs de chaque compte. `max_uses` de `login_link` exige un pool
de cache.

## Les routes

```yaml
# config/routes/phil_identity_provider.yaml
phil_identity_provider:
    resource: .
    type: phil_identity_provider
```

Le chargeur déclare seulement les routes des modules activés, listées dans la [Référence](reference.md#routes). Un
préfixe ou un hôte relève de l'application : ajoutez `prefix:` à l'importation. Le document de découverte d'OpenID
Connect doit rester à `/.well-known/openid-configuration` sous l'émetteur.

## Les pages et les recettes du kit

Les pages étendent `@PhilIdentityProvider/layout.html.twig`, ou la mise en page que nomme l'option `layout`, et
dessinent leurs parties avec des recettes du kit PHIL UX, installées dans l'application par le Symfony UX Toolkit ;
rien n'est rendu depuis `vendor/` :

```sh
php bin/console ux:install alert --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install card --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install field --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install input --kit=github.com/parler-haut-interagir-librement/ux-kit
```

Les recettes ont les noms, les props et les blocs du kit Shadcn du Toolkit : un site sur Tailwind les installe donc
depuis le kit Shadcn à la place. On les trouve sous le sous-répertoire des composants de la configuration `ux_toolkit`,
comme `phil_identity_kit('Field:Label')` les nomme ; dans un environnement où le Toolkit n'est pas activé, répétez-le
dans l'option `kit_subdirectory`. Tout gabarit se remplace par un gabarit de l'application dans
`templates/bundles/PhilIdentityProviderBundle/`.

Les boutons de clés d'accès sont pilotés par le contrôleur Stimulus `passkey` du paquet
`@phil/identity-provider-bundle`, que le bundle ajoute aux chemins d'AssetMapper ; StimulusBundle l'active depuis
`assets/package.json`.

## La page de connexion

Le formulaire de connexion est celui de l'application. Une page qui offre les clés d'accès inclut le bouton du bundle :

```twig
{{ include('@PhilIdentityProvider/passkey/_sign_in.html.twig') }}
<a href="{{ path('phil_identity_provider_login_link') }}">Sign in by email</a>
<a href="{{ path('phil_identity_provider_external_start', {provider: 'google'}) }}">Sign in with Google</a>
```

## La planification

Lancez `phil:identity-provider:rotate-keys` chaque jour quand le fournisseur est activé, et
`phil:identity-provider:purge` chaque jour pour retirer les codes et les jetons expirés et les sessions inactives, par
le composant Scheduler ou par un cron.
