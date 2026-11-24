<!-- phil-dev source-sha256=66d0d1e4ccb30e7c6d3f02b3f8f2576dbbf59a13636a3da665669fbed8163213 -->
# phil/saml-sp-bundle

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/saml-sp-bundle/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/saml-sp-bundle)

**Authentification unique des organisations dans une application Symfony : les gens se connectent par le fournisseur
d'identité SAML 2.0 de leur organisation — Microsoft Entra ID, Okta ou un autre — et leur compte est créé à leur
première connexion.**

phil/saml-sp-bundle place phil/saml-sp dans Symfony. Chaque organisation est un **fournisseur d'identité** de la
configuration, sous une clé que portent ses routes : ses métadonnées à partir d'une adresse — récupérées par
phil/safe-http et mises en cache —, d'un fichier ou du XML lui-même, un préréglage de noms de revendications (Microsoft
Entra ID, Okta, LDAP), les rôles que ses groupes donnent, et ce que ses réponses doivent respecter. Chacun reçoit quatre
routes : les **métadonnées** du fournisseur de service, que son administrateur importe, la **connexion** qui y envoie la
personne, le **service de consommation d'assertions** auquel la réponse est postée, et le **service de déconnexion**.

L'**`SamlAuthenticator`** de Symfony Security lit la réponse avec chaque contrôle de phil/saml-sp — signatures sur les
éléments lus, enveloppement de signature, destination, audience, requête à laquelle on répond, heures, rejeu — et
connecte la personne. Une assertion est acceptée une seule fois, sur un nombre quelconque de serveurs : les assertions
acceptées vont dans une table de la connexion Doctrine DBAL de l'application, une insertion atomique chacune, ou dans une
réserve de cache partagée sans base de données ; en production, une réserve de cache locale à chaque serveur est refusée
à la construction du conteneur. La connexion est liée par un témoin au navigateur qui l'a lancée, et la personne retourne au
chemin local d'où elle est partie. Son compte vient de **`SamlAccounts`**, le port que l'application implémente pour
trouver une personne et créer son compte à la première connexion ; sans lui, un **`SamlUser`** vit dans la session avec
les rôles de la configuration. La déconnexion de Symfony se poursuit jusqu'au fournisseur d'identité quand il a un
service de déconnexion, et une déconnexion que le fournisseur d'identité lance met fin à la session du navigateur et est
annoncée à l'application. Des événements informent l'application de chaque connexion, de chaque compte créé, de chaque
déconnexion et de chaque refus ; `phil:saml:check` avertit avant qu'un certificat n'expire.

## État

| Partie | État |
|---|---|
| Configuration : fournisseur de service, clés, fournisseurs d'identité, préréglages, rôles de groupes, options de sécurité | Implémenté et testé |
| Routes : métadonnées, connexion, service de consommation d'assertions, service de déconnexion | Implémenté et testé |
| `SamlAuthenticator`, `SamlUserProvider`, `SamlUser`, liaison au navigateur, destinations locales | Implémenté et testé |
| `SamlAccounts` : comptes trouvés ou créés à la première connexion | Implémenté et testé |
| Déconnexion unique lancée par l'application ou par le fournisseur d'identité | Implémenté et testé |
| Assertions acceptées dans une table de base de données par défaut, une réserve de cache sinon, une réserve locale refusée en production | Implémenté et testé |
| Événements, `phil:saml:check`, recette Flex, contrôleur Stimulus de la liaison HTTP-POST | Implémenté et testé |
| Page de découverte où une personne choisit son organisation, provisionnement SCIM | Non écrit |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | L'installation, la paire de clés, le pare-feu et les routes. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec sa valeur par défaut. |
| [Microsoft Entra ID](doc/fr/entra-id.md) | L'enregistrement de l'application dans Microsoft Entra ID, pas à pas. |
| [Okta](doc/fr/okta.md) | L'enregistrement de l'application dans Okta, pas à pas. |
| [Comptes et rôles](doc/fr/accounts.md) | `SamlAccounts`, le compte créé à la première connexion, `SamlUser`, les rôles issus des groupes. |
| [Déconnexion unique](doc/fr/logout.md) | La déconnexion depuis l'application et depuis le fournisseur d'identité. |
| [Événements et console](doc/fr/events.md) | Les événements du bundle et `phil:saml:check`. |
| [Sécurité](doc/fr/security.md) | Ce que le bundle contrôle et lie, le témoin, le cache, les clés. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports et services qu'une application remplace, et le pont vers d'autres paquets de connexion. |
| [Tests](doc/fr/testing.md) | Les tests et l'application d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, route et service. |

## Démarrage rapide

```sh
composer require phil/saml-sp-bundle
```

```yaml
# config/packages/phil_saml_sp.yaml
phil_saml_sp:
    signing:
        certificate: '%env(file:resolve:SAML_SP_CERTIFICATE)%'
        private_key: '%env(file:resolve:SAML_SP_PRIVATE_KEY)%'
    identity_providers:
        acme:
            metadata_url: 'https://login.microsoftonline.com/<tenant>/federationmetadata/2007-06/federationmetadata.xml?appid=<application>'
            preset: entra_id

# config/packages/security.yaml
security:
    providers:
        saml: { id: phil_saml_sp.user_provider }
    firewalls:
        main:
            provider: saml
            custom_authenticators: [Phil\SamlSpBundle\Infrastructure\Security\SamlAuthenticator]
            logout: { path: /logout }
```

Un lien vers `/saml/acme/login?target=/courses` connecte une personne d'Acme et l'amène à `/courses`.

## Prérequis

PHP 8.5 ou une version plus récente ; Symfony 8.2 ou une version plus récente avec FrameworkBundle et SecurityBundle ;
phil/saml-sp. `symfony/console` pour `phil:saml:check`, StimulusBundle pour que la page de la liaison HTTP-POST se poste
d'elle-même.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
