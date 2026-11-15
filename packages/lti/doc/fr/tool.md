<!-- phil-dev source-sha256=34578de63b82f588e9ccf386adf178f8469cce1de11d9d796ea2ea9ecd27ba8d -->
# L'outil

Un outil est lancé par des plateformes. Chaque plateforme l'enregistre une fois — en lui donnant un identifiant de
client, des déploiements et ses propres points de terminaison — et l'outil enregistre en retour la plateforme.

## Enregistrer une plateforme

```php
use Phil\Lti\Registration\InMemoryPlatformRegistrations;
use Phil\Lti\Registration\PlatformRegistration;
use Phil\Url\Url;

$platforms = new InMemoryPlatformRegistrations()->add(new PlatformRegistration(
    issuer: 'https://lms.example.org',                                   // the `iss` of its tokens
    clientId: 'client-id-given-by-the-platform',                         // the `aud` of its tokens
    authorizationUrl: Url::fromString('https://lms.example.org/lti/authorize'),
    tokenUrl: Url::fromString('https://lms.example.org/lti/token'),
    keySetUrl: Url::fromString('https://lms.example.org/lti/jwks'),
    tokenAudience: null,                                                 // the token endpoint's address when null
), deploymentIds: ['deployment-id']);
```

Une plateforme peut enregistrer l'outil plusieurs fois sous un même émetteur : `find()` a alors besoin du client, que
la plupart des plateformes envoient dans l'initiation de connexion. Un lancement d'un déploiement que l'outil ne connaît
pas est refusé.

L'outil donne en retour à la plateforme son adresse d'initiation de connexion, son adresse de lancement — l'adresse de
redirection d'OpenID Connect —, l'adresse de son jeu de clés et, pour Deep Linking, l'adresse de son lancement de Deep
Linking.

## L'initiation de connexion

La plateforme envoie le navigateur à l'initiation de connexion de l'outil, par GET ou POST, avec `iss`, `login_hint`,
`target_link_uri`, et souvent `lti_message_hint`, `client_id` et `lti_deployment_id`.

```php
use Phil\Lti\Tool\LoginInitiator;
use Phil\Lti\Tool\LoginRequest;

$initiator = new LoginInitiator($platforms, $states, $clock, stateLifetime: 600);
$redirect = $initiator->initiate(LoginRequest::fromParameters($parameters), $launchUrl);
// redirect the browser to $redirect->url
```

`initiate()` vérifie que la plateforme et son déploiement sont enregistrés et que la cible du lancement se trouve sur
l'origine de l'adresse de lancement — schéma, hôte et port —, de sorte qu'une initiation falsifiée ne puisse pas
envoyer la personne ailleurs après le lancement. Elle fabrique un `state` et un `nonce` de 32 octets aléatoires chacun,
les garde dans `LoginStates` pendant dix minutes par défaut, et répond l'adresse du point de terminaison d'autorisation
de la plateforme avec la requête d'authentification : `scope=openid`, `response_type=id_token`,
`response_mode=form_post`, `prompt=none`, le client, l'adresse de redirection, l'indice de connexion, l'indice de
message, le `state` et le `nonce`.

## Le lancement

La plateforme envoie `id_token` et `state` par POST à l'adresse de lancement.

```php
use Phil\Lti\Jwt\JwtVerifier;
use Phil\Lti\Tool\LaunchValidator;

$validator = new LaunchValidator($platforms, $states, $nonces, new JwtVerifier($keySets, $clock));
$launch = $validator->validate($idToken, $state);
```

`validate()` prend l'état dans son magasin — une seule fois : un second lancement avec le même état est refusé — puis
vérifie, dans l'ordre :

1. le jeton est émis par la plateforme de l'état et adressé à son client : `iss` égal, `aud` le client ou une liste qui
   le contient, `azp` le client lorsqu'il est présent, exigé lorsque `aud` en liste plusieurs ;
2. sa signature se vérifie avec `RS256` et la clé que son `kid` nomme dans le jeu de clés de la plateforme ;
3. `exp` est dans le futur et `iat` ni dans le futur ni plus vieux que dix minutes, avec cinq secondes de tolérance dans
   chaque sens ;
4. son `nonce` est celui de l'état, et n'a jamais été employé auparavant ;
5. les revendications de LTI sont bien formées, le déploiement enregistré, et la cible celle de la connexion.

Toute vérification échouée lève une exception : `InvalidRequest` pour l'état, `InvalidToken` pour le jeton,
`UnknownRegistration` pour la plateforme ou le déploiement. Chacune dit quelle vérification a échoué et ne répète
jamais le jeton.

## Ce qu'un lancement porte

`Launch` donne l'émetteur et le client de la plateforme, le `LaunchMessage` typé, et chaque revendication du jeton dans
`claims`.

| Membre | Ce que c'est |
|---|---|
| `type()` | `MessageType::ResourceLink` ou `MessageType::DeepLinking`. |
| `deploymentId()` | Le déploiement de l'outil sur la plateforme. |
| `subject()` | Le `sub` : la personne, stable pour cette plateforme et ce client ; null dans un lancement anonyme. |
| `userKey()` | Le SHA-256 de l'émetteur, du client et du sujet : un identifiant de la personne unique d'une plateforme à l'autre. |
| `roles()` | `Roles` : `has()`, `isInstructor()`, `isLearner()`, `isTestUser()`. Un rôle de contexte envoyé par son nom court est lu comme son URI complet. |
| `message->person` | Le nom, les prénoms et noms de famille, le courriel, la photo et la locale, dans la mesure où la plateforme les partage. |
| `message->context` | Le cours, la section ou le groupe : identifiant, étiquette, titre, types. |
| `message->resourceLink` | Le placement de l'outil : identifiant, titre, description. |
| `message->presentation` | Où l'outil est affiché, sa taille, la locale, l'adresse de retour. |
| `message->platform` | L'instance de la plateforme telle qu'elle se décrit. |
| `message->custom` | Les paramètres personnalisés, après que la plateforme a remplacé ses variables, en texte. |
| `message->ags` | Le point de terminaison des Assignment and Grade Services, lorsque la plateforme l'accorde : voir [AGS](ags.md). |
| `message->nrps` | Le point de terminaison du Names and Role Provisioning Service : voir [NRPS](nrps.md). |
| `message->deepLinking` | Les paramètres d'une requête de Deep Linking : voir [Deep Linking](deep-linking.md). |

Une revendication que le paquet ne type pas — d'une autre spécification, ou de la plateforme — se lit dans `claims`.

## Adresses sur localhost

Chaque adresse passe par `phil/url`, qui refuse `localhost` et les adresses IP. En développement, passez
`allowLocalhost: true` à `LoginInitiator`, à `LaunchValidator`, aux clients des services et à
`LaunchMessage::fromClaims()` ; jamais en production.
