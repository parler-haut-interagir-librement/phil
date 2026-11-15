<!-- phil-dev source-sha256=659b8665e1ce1f2970faa4da9172f2f2b6eec36b13147e5afa1ab353c1afc87e -->
# phil/lti

**LTI 1.3 en PHP pur, des deux côtés : un outil que les plateformes lancent, et une plateforme qui lance des outils,
avec Deep Linking et les services de LTI Advantage.**

LTI 1.3 est la façon dont une plateforme d'apprentissage ouvre un outil externe pour une personne — un quiz, un
laboratoire, un lecteur, une vidéo —, lui dit qui elle est, dans quel cours et avec quels rôles, et lui permet de
renvoyer des notes. phil/lti l'implémente des deux côtés :

- **l'outil** répond à l'initiation de connexion de la plateforme par une requête d'authentification OpenID Connect,
  puis vérifie l'`id_token` que la plateforme renvoie par POST : signé en `RS256` par une clé du jeu de clés de la
  plateforme, lu par `phil/safe-http` et gardé dans un cache ; `iss`, `aud`, `azp`, `exp` et `iat` vérifiés par une
  horloge PSR-20 ; le `state` et le `nonce` employés une seule fois ; le déploiement enregistré ; chaque revendication
  de LTI typée ;
- **la plateforme** démarre un lancement, répond à la requête d'authentification de l'outil par un `id_token` signé,
  publie son jeu de clés, émet des jetons d'accès, garde les line items et les notes, et liste les membres d'un
  contexte ;
- **Deep Linking 2.0** : l'outil répond à un `LtiDeepLinkingRequest` par une réponse signée d'éléments de contenu, et
  la plateforme la vérifie et lit les éléments ;
- **Assignment and Grade Services** : line items lus, créés, modifiés et supprimés, notes envoyées, résultats lus ;
- **Names and Role Provisioning Service** : les membres d'un contexte, page après page par l'en-tête `Link` ;
- **jetons d'accès** : le type d'autorisation `client_credentials` avec une assertion JWT signée par la clé de l'outil
  (RFC 7523), gardés dans un cache jusqu'à peu avant leur expiration.

Il s'adresse aux applications PHP qui ouvrent d'autres outils à leurs apprenants, ou que des plateformes
d'apprentissage ouvrent : un site de cours, un système de gestion de l'apprentissage, un quiz, une simulation, un outil
de lecture.

La bibliothèque est en PHP pur : les jetons sont signés et vérifiés par `web-token/jwt-library`, une fois leur forme lue
dans des limites ; les adresses passent par `phil/url` ; les jeux de clés et chaque appel authentifié à un service — le
point de terminaison des jetons, les notes, la liste des membres — par `phil/safe-http`, protégé contre les réseaux
privés ; les caches par les contrats de cache de Symfony, le temps par PSR-20. Les magasins — enregistrements, déploiements, états de
connexion, nonces, clés, jetons d'accès, line items, notes, listes de membres — sont des ports dotés d'adaptateurs en
mémoire ; `phil/lti-bundle` les amène à Symfony avec des tables de Doctrine DBAL.

## État

| Partie | État |
|---|---|
| Outil : initiation de connexion, lancement vérifié (`RS256` par jeu de clés, `iss`/`aud`/`azp`/`exp`/`iat`, `state` et `nonce` une fois, déploiement) | Implémenté et testé |
| Plateforme : lancement démarré, requête d'authentification traitée par un `id_token` signé, jeu de clés | Implémenté et testé |
| Messages `LtiResourceLinkRequest` et `LtiDeepLinkingRequest`, rôles typés, contexte, lien de ressource, présentation, plateforme, revendications personnalisées | Implémenté, testé et fuzzé |
| Deep Linking 2.0 : réponse signée de l'outil, vérifiée par la plateforme, cinq types d'éléments de contenu | Implémenté, testé et fuzzé |
| Assignment and Grade Services : client de l'outil, service de la plateforme | Implémenté et testé |
| Names and Role Provisioning Service : client de l'outil avec pages, service de la plateforme | Implémenté et testé |
| Jetons d'accès par assertion JWT (RFC 7523), mis en cache ; point de terminaison des jetons de la plateforme | Implémenté et testé |
| Jeux de clés lus par `phil/safe-http`, mis en cache, relus une fois pour une clé inconnue | Implémenté et testé |
| Ports et adaptateurs en mémoire | Implémenté et testé |
| LTI Dynamic Registration | Prévu |
| Suite de certification de 1EdTech | Prévue, une étape avant la première version stable |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [L'outil](doc/fr/tool.md) | L'enregistrement des plateformes, l'initiation de connexion, le lancement et ce qu'il porte. |
| [La plateforme](doc/fr/platform.md) | L'enregistrement des outils, le démarrage d'un lancement, la réponse à la requête d'authentification, le jeu de clés. |
| [Deep Linking](doc/fr/deep-linking.md) | La requête, les éléments de contenu, la réponse et sa vérification. |
| [Assignment and Grade Services](doc/fr/ags.md) | Les jetons d'accès, les line items, les notes et les résultats, des deux côtés. |
| [Names and Role Provisioning](doc/fr/nrps.md) | Les membres d'un contexte, des deux côtés. |
| [Clés](doc/fr/keys.md) | Les clés, les jeux de clés, la rotation et le cache des clés de l'autre côté. |
| [Sécurité](doc/fr/security.md) | Ce que chaque vérification empêche, et ce qui reste à l'application. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, leurs adaptateurs en mémoire, et ce qui reste fermé. |
| [Tests](doc/fr/testing.md) | Les tests du paquet, ses cibles de fuzzing et la suite de certification. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

Un outil enregistré auprès d'une plateforme :

```php
use Phil\Lti\Jwt\JwtVerifier;
use Phil\Lti\Key\CachedKeySetSource;
use Phil\Lti\Key\SafeHttpKeySetSource;
use Phil\Lti\Registration\InMemoryPlatformRegistrations;
use Phil\Lti\Registration\PlatformRegistration;
use Phil\Lti\Tool\LaunchValidator;
use Phil\Lti\Tool\LoginInitiator;
use Phil\Lti\Tool\LoginRequest;
use Phil\Url\Url;

$platforms = new InMemoryPlatformRegistrations()->add(new PlatformRegistration(
    'https://lms.example.org',
    'client-id-given-by-the-platform',
    Url::fromString('https://lms.example.org/lti/authorize'),
    Url::fromString('https://lms.example.org/lti/token'),
    Url::fromString('https://lms.example.org/lti/jwks'),
), ['deployment-id']);

// 1. The platform calls the login initiation: send the browser to the platform.
$redirect = new LoginInitiator($platforms, $states, $clock)
    ->initiate(LoginRequest::fromParameters($_REQUEST), Url::fromString('https://tool.example.com/lti/launch'));
header('Location: '.$redirect->url->toString());

// 2. The platform posts the id_token and the state to the launch address.
$verifier = new JwtVerifier(new CachedKeySetSource(new SafeHttpKeySetSource(), $cache, $clock), $clock);
$launch = new LaunchValidator($platforms, $states, $nonces, $verifier)->validate($_POST['id_token'], $_POST['state']);

$launch->subject();                       // the person, for this platform and client
$launch->roles()->isInstructor();
$launch->message->context?->title;
$launch->message->resourceLink?->id;
```

## Prérequis

PHP 8.5 ou plus récent avec les extensions JSON, mbstring, OpenSSL, sodium et URI ; `web-token/jwt-library`,
`phil/url`, `phil/safe-http`, les contrats de cache et de service de Symfony, et `psr/clock`.

## Contribuer

Les contributions sont bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
