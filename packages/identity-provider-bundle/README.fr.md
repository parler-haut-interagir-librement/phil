<!-- phil-dev source-sha256=ca4f062cb134b5929457c3c41b16a9d6e3b5720d3ac5c6addc91883130b650ac -->
# phil/identity-provider-bundle

**Une brique d'identité ouverte pour Symfony : des comptes, un module par façon de se connecter et un fournisseur OpenID Connect.**

phil/identity-provider-bundle garde des comptes — un identifiant de votre choix, ULID ou UUIDv7, une adresse avec sa
forme canonique et sa vérification, des adresses de récupération et d'avis, un état générique, un nom, un mot de passe
haché avec Argon2id — et chaque façon d'en faire la preuve : des mots de passe comparés aux fuites par `phil/passwords`, des clés
d'accès (WebAuthn), une application d'authentification (TOTP) avec des codes de récupération, des liens de connexion
par courriel par l'authentificateur `login_link` de Symfony Security, un numéro de téléphone vérifié par message texte
et des identités de fournisseurs fédérés dont les paquets se branchent sur un port. Il demande une deuxième étape quand
un compte a une application d'authentification, redemande avant les actions sensibles grâce au mode sudo de Symfony
Security 8.2, liste les appareils et les sessions d'un compte et les ferme depuis un autre appareil, et avertit d'une
connexion depuis un nouvel appareil.

C'est aussi un fournisseur OpenID Connect bâti sur les composants de Symfony, dont les jetons sont signés et vérifiés
par `web-token/jwt-library` : découverte, jeu de clés qui tourne, flux du code d'autorisation avec PKCE, jetons
d'identité et d'accès signés par des clés de la base de données, de fichiers ou d'un module de sécurité matériel,
jetons d'actualisation renouvelés avec détection de réutilisation et liés à leur session, clients qui s'authentifient par un secret ou par une assertion de client signée avec leur propre clé
(`private_key_jwt`), octroi `client_credentials` pour les jetons d'une ressource, userinfo, révocation (RFC 7009),
introspection (RFC 7662), déconnexion à l'initiative du client, consentement par client et clients enregistrés dans la
base de données. Il réussit les plans Basic OP, Config OP et RP-Initiated Logout OP de la suite de
conformité de l'OpenID Foundation, exécutés dans Docker par `tests/Conformance/run.sh`.

Chaque méthode est un module que la configuration active ou désactive, avec ses propres routes, tables et pages : un
site qui ne veut ni liens de connexion ni messages texte les désactive, et ils ne sont plus joignables. Le bundle garde
le minimum sur les personnes — aucune adresse de leur connexion, aucun jeton d'un fournisseur fédéré — et ses
événements ne portent que des identifiants.

## État

| Partie | État |
|---|---|
| Comptes : inscription sans révéler qui a un compte, limitée par client et par adresse, identifiants d'un générateur (ULID ou UUIDv7), adresses canoniques, adresses de récupération et d'avis, états génériques, adresses vérifiées par des liens signés ou conservés confirmés par un formulaire, liens des courriels sur l'adresse canonique du site, page du compte | Implémenté et testé |
| Mots de passe : Argon2id, force et fuites vérifiées par `phil/passwords`, réinitialisation par un lien signé sur le mot de passe et servi une fois, autres sessions fermées à chaque changement, verrou progressif qui peut être désactivé, limiteur de connexion par identifiant pour `login_throttling` | Implémenté et testé |
| Clés d'accès : enregistrement, connexion, renommage, retrait ; la dernière façon de se connecter est conservée ; AAGUID et indicateurs de copie de secours enregistrés, attestation `packed` lue contre des racines de confiance, politique des marques et des clés d'accès synchronisées | Implémenté et testé |
| Application d'authentification (TOTP), codes de récupération, deuxième étape d'une connexion | Implémenté et testé |
| Liens de connexion par courriel (`login_link` de Symfony Security) | Implémenté et testé |
| Numéro de téléphone vérifié par message texte, par le composant Notifier ou par un `SmsSender` à vous | Implémenté et testé |
| Réauthentification sur Symfony 8.2 : `PHIL_STEP_UP`, preuves `AuthenticationMethodBadge` (RFC 8176), `requestReAuthentication()` | Implémenté et testé |
| Appareils et sessions : liste, fermeture d'une session ou de toutes depuis un autre appareil, connexion risquée signalée | Implémenté et testé |
| Identités fédérées par le port `ExternalProvider`, liées sans fusion sur une adresse non vérifiée | Implémenté et testé |
| Fournisseur OpenID Connect : découverte, JWKS avec rotation, clés de signature de la base de données, de fichiers ou d'un module de sécurité matériel, clés chargées une seule fois, flux du code avec PKCE, rotation de l'actualisation, userinfo, révocation, introspection, déconnexion, déconnexion par canal arrière, sujets opaques et par paire, consentement | Implémenté et testé |
| Magasins Doctrine sur SQLite, MySQL 8.4 et PostgreSQL 17 ; magasins en mémoire pour les tests | Implémenté et testé |
| Gabarits dessinés avec les recettes du kit, un contrôleur Stimulus pour les clés d'accès | Implémenté et testé |
| Piste d'audit par `phil/audit-bundle` quand il est installé ; secrets chiffrés par le composant KeyManagement, en clair seulement quand la configuration le dit | Implémenté et testé |
| Suite de conformité de l'OpenID Foundation : plans Basic OP, Config OP et RP-Initiated Logout OP, dans Docker | Exécutée et réussie — voir [Conformité OpenID](doc/fr/openid-conformance.md) |
| Certification par l'OpenID Foundation, revue de sécurité par quelqu'un d'autre | Avant l'utilisation en production — voir [Modèle de menaces](doc/fr/threat-model.md) et [Guide du réviseur](doc/fr/security-review.md) |
| Connexion entre appareils par code QR, authentification unique d'entreprise (SAML), comptes d'enfants avec un tuteur, comptes invités, comptes de service, usurpation d'identité auditée et revendication `act` (RFC 8693), enregistrement dynamique de clients, objets de requête, SMS comme deuxième facteur, inscription par clé d'accès d'abord, suppression de compte | Prévu |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, le bundle, le pare-feu, les routes, les tables et les recettes du kit. |
| [Configuration](doc/fr/configuration.md) | Chaque option, module par module, avec un exemple. |
| [Comptes et mots de passe](doc/fr/accounts.md) | Les identifiants, les états, les adresses selon leur usage, l'inscription, les adresses vérifiées, les liens conservés, la page du compte, les mots de passe, leur réinitialisation et le verrou. |
| [Clés d'accès](doc/fr/passkeys.md) | WebAuthn : les cérémonies, ce qui est vérifié, l'attestation et sa politique, le contrôleur Stimulus. |
| [Application d'authentification et codes de récupération](doc/fr/totp.md) | TOTP, codes de récupération et deuxième étape d'une connexion. |
| [Liens de connexion et numéros de téléphone](doc/fr/links-and-sms.md) | L'authentificateur `login_link` et la vérification par message texte. |
| [Réauthentification](doc/fr/step-up.md) | `PHIL_STEP_UP` sur le mode sudo de Symfony Security 8.2, et la page de réauthentification. |
| [Appareils et sessions](doc/fr/sessions.md) | Des sessions liées à des lignes, fermées depuis un autre appareil ; les connexions risquées. |
| [Fournisseurs fédérés](doc/fr/external-providers.md) | Le port `ExternalProvider` et les règles qui lient les identités aux comptes. |
| [Fournisseur OpenID Connect](doc/fr/oidc-provider.md) | Les points de terminaison, les clients et leur authentification — `private_key_jwt` compris —, les jetons et leurs familles, les jetons d'actualisation liés à la session, les ressources, l'octroi `client_credentials`, les sources de clés et leur rotation, le consentement, la déconnexion. |
| [Modèle de menaces](doc/fr/threat-model.md) | Les actifs, les frontières de confiance, ce dont le bundle se défend et comment, les risques ouverts. |
| [Guide du réviseur](doc/fr/security-review.md) | La portée de la revue de sécurité, ses points d'entrée, et comment exécuter les tests et la suite. |
| [Conformité OpenID](doc/fr/openid-conformance.md) | La suite de conformité de l'OpenID Foundation dans Docker : comment l'exécuter, et ses résultats. |
| [Points d'extension](doc/fr/extension-points.md) | Les services à remplacer, les étiquettes à compléter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, les serveurs que lisent ses tests d'intégration, et les cibles de fuzzing. |
| [Référence](doc/fr/reference.md) | Chaque classe, service, route, table et événement du bundle. |

## Quick start

```php
// config/bundles.php
Phil\IdentityProviderBundle\PhilIdentityProviderBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_identity_provider.yaml
phil_identity_provider:
    site_name: Example
    login_route: app_login
    mailer: { from: accounts@example.com }
    modules: { passkeys: true, totp: true, recovery_codes: true }
    passkeys: { rp_id: example.com, origins: ['https://example.com'] }
    secrets: { kms_key: identity }   # the TOTP secrets, encrypted by symfony/key-management
```

```yaml
# config/routes/phil_identity_provider.yaml
phil_identity_provider:
    resource: .
    type: phil_identity_provider
```

Le pare-feu nomme le fournisseur d'utilisateurs, le vérificateur d'utilisateurs et les authentificateurs du bundle :
voir [Installation](doc/fr/installation.md).

## Prérequis

PHP 8.5 ou une version plus récente avec les extensions `mbstring`, `openssl` et `sodium`, Symfony 8.2 ou une version
plus récente, Doctrine ORM 3.7 ou une version plus récente, `web-token/jwt-library` 4.2 ou une version plus récente, `phil/passwords`, `phil/rate-limit` et `phil/url`. En production, réglez
`public_origin` ou `framework.trusted_hosts` : le bundle refuse de démarrer sans l'un ou l'autre (voir
[Où mènent les liens](doc/fr/accounts.md#où-mènent-les-liens)).

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
