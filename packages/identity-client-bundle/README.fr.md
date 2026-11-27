<!-- phil-dev source-sha256=e31448f41271944688ebc64f1df403d006a818e7b495690f94523d7b06d83571 -->
# phil/identity-client-bundle

**Connectez les utilisateurs d'une application Symfony par n'importe quel fournisseur OpenID Connect, de façon
renforcée.**

phil/identity-client-bundle configure l'authentificateur `oidc_login` de Symfony à partir d'un seul bloc de
configuration. Il écrit le pare-feu, fixe l'algorithme de signature et ajoute ce dont une partie de confiance a besoin
au-delà de la connexion : un `return_to` validé, une déconnexion par canal arrière qui n'accepte chaque jeton qu'une
fois, avec un plancher pour une réserve de rejeu qui a été vidée, une durée de session plafonnée, un niveau d'assurance
exigé et l'authentification renforcée par `acr_values` et `max_age`, et un renouvellement des jetons qui ne présente
jamais deux fois un jeton de renouvellement déjà remplacé. Avec `phil/saml-sp-bundle`, les connexions des fournisseurs
d'identité SAML aboutissent aux mêmes comptes.

Il s'adresse aux applications Symfony qui confient l'authentification à un fournisseur OpenID Connect. Le flux
lui-même, l'état et le nonce, la découverte, les vérifications de signature et l'authentification du client restent
ceux de Symfony.

## État

| Partie | État |
|---|---|
| Configuration sémantique qui compose le pare-feu `oidc_login` | Implémentée et testée |
| Approvisionnement sur `sub`, revendications conservées, jetons retirés de la session | Implémenté et testé |
| `return_to` validé contre les origines déclarées | Implémenté et testé |
| Déconnexion par canal arrière avec garde contre la réutilisation de `jti`, plafond de session | Implémentée et testée |
| Plancher de démarrage à froid de la réserve de rejeu | Implémenté et testé |
| Niveau d'assurance exigé, vérifié sur l'`acr` retourné | Implémenté et testé |
| Authentification renforcée : voter, `acr_values`, `max_age`, défi de la RFC 9470 | Implémentée et testée |
| Renouvellement des jetons sérialisé | Implémenté et testé |
| Connexions SAML approvisionnées dans les mêmes comptes, créés à la première connexion | Implémentées et testées |

Une revue de sécurité indépendante est prévue avant une première version.

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, enregistrement, le pare-feu, les routes. |
| [Configuration](doc/fr/configuration.md) | Chaque clé, et ce qu'elle compose. |
| [Approvisionnement](doc/fr/provisioning.md) | `UserProvisioner`, l'identité, ce que garde la session. |
| [Retour](doc/fr/return-to.md) | Le paramètre, la politique, où aboutit une connexion. |
| [Déconnexion](doc/fr/logout.md) | La déconnexion par canal arrière, la garde contre la réutilisation, le plafond de session, le plancher de démarrage à froid. |
| [Authentification renforcée](doc/fr/step-up.md) | Les niveaux d'assurance, le niveau exigé, le voter, le défi. |
| [Renouvellement des jetons](doc/fr/token-refresh.md) | Le verrou, le résultat partagé, la révocation de la famille. |
| [Comptes SAML](doc/fr/saml.md) | Les connexions SAML par `phil/saml-sp-bundle`, dans les comptes de l'approvisionneur. |
| [Points d'extension](doc/fr/extension-points.md) | Ports, événements, services. |
| [Sécurité](doc/fr/security.md) | Les menaces couvertes et ce qui reste à l'application. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |

## Démarrage rapide

```yaml
# config/packages/security.yaml
security:
    firewalls:
        main:
            lazy: true
```

```yaml
# config/packages/phil_identity_client.yaml
phil_identity_client:
    issuer: 'https://id.example.org'
    client_id: 'example-site'
    client_secret: '%env(OIDC_CLIENT_SECRET)%'
    claims: ['name', 'email']
```

```yaml
# config/routes/security.yaml
oidc_login:
    resource: security.route_loader
    type: service
```

Un lien vers `/oidc/start?return_to=/account` connecte l'utilisateur et le mène à la page de son compte.

## Prérequis

PHP 8.5 ou plus récent avec `ext-sodium` et `ext-uri` ; le bundle et les composants de sécurité de Symfony 8.2, le
framework bundle, le client HTTP, le cache, l'horloge et le verrou ; `web-token/jwt-library` 4.2, sur lequel tourne
l'authentificateur `oidc_login`.
Les comptes SAML exigent `phil/saml-sp-bundle`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
