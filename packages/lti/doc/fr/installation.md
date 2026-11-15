<!-- phil-dev source-sha256=de60bd7dacd82638af7438e6e2975a4479b6879f61013d3051ce6a75ffc4debf -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent, avec les extensions JSON, mbstring, OpenSSL, sodium et URI — OpenSSL pour les signatures RSA
  que `web-token/jwt-library` fait et vérifie ;
- `web-token/jwt-library`, qui signe et vérifie les jetons ;
- `phil/url`, qui lit chaque adresse, et `phil/safe-http`, qui lit les jeux de clés de l'autre côté et envoie les
  requêtes des services de LTI Advantage ;
- les contrats de cache de Symfony, pour les jeux de clés et les jetons d'accès gardés entre les requêtes ;
- une horloge PSR-20, comme le composant Clock de Symfony.

## Installation

```sh
composer require phil/lti
```

Dans une application Symfony, installez plutôt `phil/lti-bundle` : il enregistre les services, garde les magasins dans
des tables, monte les routes et transforme un lancement en jeton de sécurité.

## Ce que l'application fournit

La bibliothèque ne garde aucun état. L'application lui donne :

| Quoi | Pourquoi | Port |
|---|---|---|
| Les plateformes auprès desquelles l'outil est enregistré, et leurs déploiements | Un lancement n'est accepté que de celles-ci | `PlatformRegistrations` |
| Les outils que la plateforme a enregistrés, et leurs déploiements | Un lancement n'est envoyé qu'à ceux-ci | `ToolRegistrations` |
| Un magasin d'états de connexion | Le `state` et le `nonce` d'une connexion vivent jusqu'à son lancement | `LoginStates` |
| Un magasin de valeurs employées une seule fois | Un `nonce` ou un `jti` rejoué est refusé | `Nonces` |
| Ses clés | Elle signe avec la clé courante et publie son jeu de clés | `KeyStore` |
| Un cache | Les jeux de clés et les jetons d'accès ne sont pas relus à chaque requête | `CacheInterface` de Symfony |
| Sur la plateforme : jetons d'accès, line items, notes, liste de membres | Les services de LTI Advantage | `AccessTokens`, `LineItemStore`, `ScoreStore`, `Roster` |

Chaque port est fourni avec un adaptateur en mémoire, pour les tests et pour un processus unique ; voir les
[points d'extension](extension-points.md).
