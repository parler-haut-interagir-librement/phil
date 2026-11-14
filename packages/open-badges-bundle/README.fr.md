<!-- phil-dev source-sha256=5387644a9bea6fc68d0cdb202f83c4766330e36efda5a32aa5d9a8c1facb4463 -->
# phil/open-badges-bundle

**Les Open Badges dans une application Symfony : l'émetteur configuré une fois, son profil et ses clés publiés, ses
révocations servies, et une page où n'importe qui vérifie un badge.**

phil/open-badges-bundle intègre [`phil/open-badges`](https://github.com/parler-haut-interagir-librement/open-badges) à une application Symfony. La
configuration nomme l'émetteur et sa clé — conservée comme un secret —, et la façon de sécuriser ses attestations :
comme jetons Web JSON (JWT), ou avec des preuves Data Integrity. Le bundle sert ensuite ce dont tout le monde a besoin
pour les vérifier : le profil de l'émetteur à son identifiant, ses clés publiques, et les listes d'état qui disent
quelles attestations ont été révoquées ou suspendues. Sa page de vérification contrôle une attestation collée, ou une
image de badge dans laquelle l'attestation est intégrée — celles de l'application et, à sa demande, celles d'autres
émetteurs, dont les clés Ed25519 ou RSA sont lues dans leurs profils ou leurs documents `did:web` par un client HTTP gardé.

Il sert à l'application qui décerne des badges ou des certificats et veut qu'ils restent valables hors d'elle.
L'application décide qui obtient quoi et conserve ce qu'elle a émis ; le bundle lui donne les constructeurs, déjà
configurés, et le côté public de l'émetteur.

## État

| Partie | État |
|---|---|
| L'émetteur et sa clé tirés de la configuration (EdDSA ou RS256), les constructeurs d'attestations, d'endossements, de relevés d'apprentissage et de listes d'état | Implémenté et testé |
| La sécurisation par jetons Web JSON ou par preuves Data Integrity | Implémenté et testé |
| Le profil de l'émetteur avec son Multikey, son JSON Web Key Set | Implémenté et testé |
| Les Bitstring Status Lists servies à partir des `StatusLists` de l'application | Implémenté et testé |
| La page de vérification : jetons, documents, images PNG et SVG avec attestation intégrée, identité du destinataire | Implémenté et testé |
| Les vérifications de la page comptées contre un limiteur de `phil/rate-limit-bundle`, par configuration | Implémenté et testé |
| Les contextes JSON-LD que fournit l'application, lus depuis ses fichiers | Implémenté et testé |
| La construction refusée, avec les options à régler, quand aucun émetteur n'est configuré | Implémenté et testé |
| La vérification des attestations d'émetteurs que l'application ne configure pas : leur profil lu par `phil/safe-http`, leurs clés mises en cache, émetteurs de confiance facultatifs | Implémenté, testé et fuzzé |
| Les clés Ed25519 et RSA d'autres émetteurs, en `Multikey` ou en `JsonWebKey`; les émetteurs connus par un `did:web`, leur document DID lu là où la méthode le place | Implémenté, testé et fuzzé |
| Tests de conformité de 1EdTech | En cours : cette version n'est pas certifiée; [Conformité](doc/fr/conformance.md) dit ce qui est testé et comment vérifier une attestation dès aujourd'hui |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'activation du bundle, la clé, les routes. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec sa valeur par défaut. |
| [Émission](doc/fr/issuing.md) | Les services qu'une application injecte pour émettre et sécuriser des attestations. |
| [Publication](doc/fr/publishing.md) | Le profil, les clés et les listes d'état que servent les routes. |
| [Page de vérification](doc/fr/verification-page.md) | Ce que la page vérifie et affiche, sa mise en page et ses traductions. |
| [Autres émetteurs](doc/fr/remote-issuers.md) | La lecture des clés d'émetteurs que l'application ne configure pas, `did:web` compris, ses bornes et ses refus. |
| [Conformité](doc/fr/conformance.md) | Où en sont les tests de conformité de 1EdTech, ce qui est testé, et comment vérifier une attestation dès aujourd'hui. |
| [Points d'extension](doc/fr/extension-points.md) | `StatusLists`, la mise en page, les clés de confiance, les méthodes de vérification, les contextes, la limite sur les vérifications. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et le test d'une application qui émet des badges. |
| [Référence](doc/fr/reference.md) | Chaque classe du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/open-badges-bundle
```

```yaml
# config/packages/phil_open_badges.yaml
phil_open_badges:
    issuer:
        id: https://example.org/open-badges/issuer
        name: Example
        url: https://example.org
    key:
        secret: '%env(base64:OPEN_BADGES_SEED)%'
    securing: data_integrity
```

```yaml
# config/routes/phil_open_badges.yaml
phil_open_badges:
    resource: '@PhilOpenBadgesBundle/config/routes.php'
    prefix: /open-badges
phil_open_badges_verification:
    resource: '@PhilOpenBadgesBundle/config/verification_routes.php'
    prefix: /open-badges
```

```php
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadgesBundle\Application\Securing;

$credential = $credentials->credential($achievement, $recipient, 'urn:uuid:'.$uuid, $awardedAt);
$secured = $securing->secure($credential);    // a SecuredCredential: ->content, ->mediaType
```

## Conformité

Les tests de conformité de 1EdTech sont en cours, et cette version n'est pas certifiée par 1EdTech. Les attestations se
vérifient dès aujourd'hui par la page de vérification du bundle, par le service `CredentialVerifier`, par tout
vérificateur de Verifiable Credentials 2.0 qui lit VC-JWT ou `eddsa-rdfc-2022`, ou en exécutant le validateur de 1EdTech
contre un émetteur déployé; voir [Conformité](doc/fr/conformance.md).

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 ou plus récent, `phil/open-badges` 0.1.1 ou plus récent, `phil/url` et
`phil/safe-http`. La page de vérification exige TwigBundle; la lecture des profils et des documents DID d'autres
émetteurs, un pool de cache.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
