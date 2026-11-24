<!-- phil-dev source-sha256=4dbeb27c196031eb0e0f4ea4e3d548dbf59a5f7763829e36b3fdb5a10932d2a7 -->
# phil/saml-sp

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/saml-sp/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/saml-sp)

**Authentification unique des membres d'une organisation par son fournisseur d'identité SAML 2.0 — Microsoft Entra ID,
Okta ou un autre : un fournisseur de service qui vérifie les assertions signées et fait correspondre leurs attributs à
un utilisateur.**

phil/saml-sp est une bibliothèque PHP sans cadre, fondée sur `ext-dom` et `ext-openssl`. Une application se décrit
comme un **`ServiceProvider`** — son identifiant d'entité, son service de consommation d'assertions, son service de
déconnexion, ses paires de clés — et chaque organisation qu'elle accepte comme un **`IdentityProvider`** : les
métadonnées lues par `MetadataReader` à partir du XML ou récupérées par `MetadataLoader` à son adresse, par
phil/safe-http, puis mises en cache ; l'**`AttributeMap`** de ses revendications (avec des préréglages pour Microsoft
Entra ID et Okta) ; et ce que ses réponses doivent respecter.

`MessageFactory` écrit l'**`AuthnRequest`**, et `MessageSender` la place sur la liaison HTTP-Redirect, signée dans la
requête, ou sur HTTP-POST, signée à l'intérieur. **`ResponseProcessor`** lit la réponse renvoyée et retourne une
**`SamlIdentity`** — l'identifiant de nom, la session chez le fournisseur d'identité, les attributs et les champs
associés —, ou la refuse avec une `SamlException`. Il vérifie les signatures XML comme SAML les signe, sans rien de
plus souple : C14N exclusive, RSA avec SHA-256 et plus, SHA-1 refusé sauf s'il est permis, la référence menant à
l'élément même qui est lu, chaque ID unique et une seule assertion, de sorte que l'enveloppement de signature ne trouve
rien à envelopper. Il contrôle le statut, l'émetteur, la destination, le destinataire, l'audience, la requête à
laquelle on répond, les heures dans les limites d'un décalage d'horloge, et refuse une assertion présentée deux fois.
Une `EncryptedAssertion` est déchiffrée avec RSA-OAEP et AES-GCM ou AES-CBC. Les documents sont lus sans DOCTYPE, sans
entité et dans les limites d'un budget d'octets, de profondeur et de nœuds.

`LogoutProcessor` et les messages de déconnexion de `MessageFactory` portent la déconnexion unique dans les deux sens,
et `ServiceProviderMetadata` écrit les métadonnées qu'un fournisseur d'identité importe. La bibliothèque ne garde aucun
état entre les appels : les requêtes qui attendent leur réponse et les assertions déjà acceptées vivent derrière deux
ports, avec des implémentations PSR-6, de sorte qu'elle sert un *worker* de longue durée et plusieurs serveurs.

## État

| Partie | État |
|---|---|
| `SafeXml`, `XmlBudget` : documents lus sans DOCTYPE ni entité, bornés | Implémenté et testé |
| `SignatureVerifier`, `Signer` : signatures XML enveloppées, C14N exclusive, RSA-SHA256 et plus | Implémenté et testé |
| `Decrypter` : assertions chiffrées, RSA-OAEP avec AES-GCM ou AES-CBC | Implémenté et testé |
| `MessageFactory`, `MessageSender`, `HttpRedirect`, `HttpPost` : requêtes et messages de déconnexion sur les deux liaisons | Implémenté et testé |
| `ResponseProcessor` : les contrôles du profil Web Browser SSO, rejeu compris | Implémenté et testé |
| `LogoutProcessor` : déconnexion unique lancée par le fournisseur de service ou par le fournisseur d'identité | Implémenté et testé |
| `MetadataReader`, `MetadataLoader`, `SafeHttpMetadataFetcher`, `ServiceProviderMetadata` | Implémenté et testé |
| `AttributeMap` avec les préréglages Microsoft Entra ID, Okta et LDAP, `SamlIdentity` | Implémenté et testé |
| `DbalReplayGuard` : les assertions acceptées dans une table de base de données, une insertion atomique chacune | Implémenté et testé |
| `CacheReplayGuard`, `CachePendingRequests` et leurs équivalents en mémoire | Implémenté et testé |
| Liaison Artifact, ECP, identifiants de nom chiffrés, provisionnement SCIM | Non écrit |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et la construction des objets sans cadre. |
| [Connexion](doc/fr/sign-in.md) | La requête, la réponse, l'identité, les réponses non sollicitées, le chiffrement. |
| [Métadonnées](doc/fr/metadata.md) | La lecture des métadonnées d'un fournisseur d'identité, leur cache, et l'écriture de celles du fournisseur de service. |
| [Attributs](doc/fr/attributes.md) | La table des attributs, ses préréglages et les champs d'une `SamlIdentity`. |
| [Déconnexion unique](doc/fr/logout.md) | La déconnexion lancée par le fournisseur de service et par le fournisseur d'identité. |
| [Le magasin de rejeu](doc/fr/replay-store.md) | Les gardes de rejeu en base de données, en cache et en mémoire, la table, sa migration et sa purge. |
| [Sécurité](doc/fr/security.md) | Chaque contrôle, les défenses contre l'enveloppement de signature, les attaques XML et le rejeu, et les clés. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les magasins et les options qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests, les clés et jeux de données d'essai, la cible de fuzzing et le banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/saml-sp
```

```php
use Phil\SamlSp\Attribute\AttributeMap;
use Phil\SamlSp\Attribute\UserField;
use Phil\SamlSp\Binding\MessageSender;
use Phil\SamlSp\Message\MessageFactory;
use Phil\SamlSp\Message\ResponseProcessor;
use Phil\SamlSp\Metadata\MetadataReader;
use Phil\SamlSp\Provider\IdentityProvider;
use Phil\SamlSp\Provider\ServiceProvider;
use Phil\SamlSp\Store\CacheReplayGuard;
use Phil\Url\Url;

$sp = new ServiceProvider('https://app.example.org/saml/metadata', Url::fromString('https://app.example.org/saml/acs'), signing: $keyPair);
$idp = new IdentityProvider(new MetadataReader()->read($federationMetadataXml), AttributeMap::entraId());

// Sending the person to the identity provider: remember the ID of the request, redirect the browser.
$request = new MessageFactory($clock)->authnRequest($sp, $idp);
$location = MessageSender::send($request, $sp->signing)->url;

// Reading the response posted back to the assertion consumer service.
$identity = new ResponseProcessor($clock, new CacheReplayGuard($cachePool))->process($_POST['SAMLResponse'], $idp, $sp, $request->id);
$identity->subject();                  // the stable identifier of the person at the identity provider
$identity->value(UserField::Email);
```

## Prérequis

PHP 8.5 ou une version plus récente, avec les extensions `dom`, `libxml`, `openssl`, `sodium` et `zlib` ; `phil/url` et
`phil/safe-http` ; les interfaces PSR-6 et PSR-20 et les contrats de cache et de services de Symfony. Dans une
application Symfony, phil/saml-sp-bundle ajoute les routes, l'authentificateur et la configuration.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
