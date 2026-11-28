<!-- phil-dev source-sha256=e8b90454d52192b061b00a8b6d37a7be7689ba280b7a4e7bddddbc6fdd888e57 -->
# phil/identity-provider-google-bundle

**Permettre de se connecter avec Google à une application qui exécute phil/identity-provider-bundle, et lier le compte
Google au compte que la personne a déjà — jamais fusionné sur une adresse dont Google ne répond pas —, pendant que la
Cross-Account Protection de Google ferme ses sessions quand son compte Google est révoqué, désactivé ou supprimé.**

phil/identity-provider-google-bundle est Google comme fournisseur fédéré de phil/identity-provider-bundle. Il se branche sur
le module `external` du bundle : `/login/with/google` envoie la personne chez Google, et la fonction de rappel du bundle
la relit par ce paquet, puis connecte, lie ou crée le compte selon les règles du bundle.

Le protocole OpenID Connect est celui de Symfony Security : sa découverte des points de terminaison de Google, son
client du point de terminaison des jetons, sa vérification de la signature du jeton d'identité contre les clés de
Google, en cache, et de ses revendications. Le paquet ajoute ce dont une connexion avec Google a besoin autour d'eux :

- la **requête d'autorisation** : un état (*state*), un *nonce* et un défi PKCE (S256) gardés dans la session, plusieurs
  onglets à la fois, chaque réponse acceptée une seule fois ;
- des **données minimales** : les portées `openid` et `email` seulement, le nom avec `profile` quand la configuration le
  demande, aucun jeton de rafraîchissement, et aucun jeton conservé une fois l'identité lue ;
- une **liaison sûre** : une adresse n'est remise au bundle comme vérifiée que si Google en est l'autorité —
  `email_verified` et une adresse `gmail.com`, ou un compte Google Workspace (`hd`) sur son propre domaine —, de sorte
  que le bundle ne lie jamais un compte Google à un compte existant sur une autre adresse ; les domaines Workspace
  acceptés, quand ils sont configurés, sont vérifiés sur la revendication `hd` ;
- un **récepteur d'événements de sécurité** (RFC 8935) : la Cross-Account Protection (RISC) de Google envoie des jetons
  signés quand les sessions ou les jetons d'un compte Google sont révoqués, ou que le compte est désactivé ou supprimé ;
  les sessions du compte lié sont fermées, et un compte désactivé parce qu'il a été détourné ne peut plus se connecter
  avec Google tant que Google ne l'a pas réactivé. Le récepteur lit aussi la révocation de session du Shared Signals
  Framework, pour d'autres fournisseurs.

Chaque requête vers Google passe par phil/safe-http : adresses vérifiées, réseaux privés refusés, aucune redirection,
durée et taille bornées.

## État

| Partie | État |
|---|---|
| `GoogleProvider` et `GoogleSignIn` : flux du code d'autorisation avec état, *nonce* et PKCE, sur les pièces OpenID Connect de Symfony | Implémenté et testé |
| `GoogleClaims` : sujet, adresse de confiance sur l'autorité de Google, domaines Workspace acceptés, nom sur demande | Implémenté et testé |
| `SafeHttpTransport` : le contrat du client HTTP de Symfony sur phil/safe-http | Implémenté et testé |
| `SecurityEventReceiver` et le point de terminaison RISC : jetons signés, garde contre le rejeu, sessions fermées, comptes détournés retenus | Implémenté, testé et soumis au fuzzing |
| Configuration et recette Flex | Implémenté et testé |
| Enregistrer le flux RISC chez Google depuis une commande | Non écrit : voir [Cross-Account Protection](doc/fr/risc.md) |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, le client Google Cloud, le bundle, les routes et le pare-feu. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec sa valeur par défaut. |
| [Liaison des comptes](doc/fr/linking.md) | La connexion, la liaison et la dissociation, l'adresse de confiance, les données demandées. |
| [Cross-Account Protection](doc/fr/risc.md) | Le récepteur des événements de sécurité de Google, ce que fait chaque événement, l'enregistrement du flux. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, l'événement et les services qu'une application remplace. |
| [Tests](doc/fr/testing.md) | Les tests, le Google local et la cible de fuzzing. |
| [Référence](doc/fr/reference.md) | Chaque type public, route et service. |

## Démarrage rapide

```sh
composer require phil/identity-provider-google-bundle
php bin/console secrets:set GOOGLE_CLIENT_SECRET
```

```yaml
# config/packages/phil_identity_provider.yaml
phil_identity_provider:
    modules:
        external: true

# config/packages/phil_identity_provider_google.yaml
phil_identity_provider_google:
    client_id: '%env(GOOGLE_CLIENT_ID)%'
    client_secret: '%env(GOOGLE_CLIENT_SECRET)%'
```

Un bouton « Link with Google » apparaît alors sur la page des identités liées du bundle, et `/login/with/google`
connecte une personne.

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-mbstring`, `ext-openssl` et `ext-sodium`, Symfony 8.2,
phil/identity-provider-bundle avec son module `external`, et `web-token/jwt-library`, avec lequel les pièces OpenID
Connect de Symfony vérifient les jetons.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
