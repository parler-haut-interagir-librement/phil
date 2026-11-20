<!-- phil-dev source-sha256=6697d0cdc11291e1d640991605741b8015d9ff5a682abe4b33a15257053a5fcb -->
# Fournisseur OpenID Connect

Le module `oidc` fait du site un fournisseur OpenID Connect pour ses clients : d'autres sites et applications
connectent leurs utilisateurs avec les comptes de celui-ci. Il implémente le flux du code d'autorisation avec PKCE, le
seul flux que conservent les meilleures pratiques de sécurité actuelles d'OAuth 2.0 (RFC 9700) pour les personnes, et
l'octroi `client_credentials` pour un client qui demande des jetons pour lui-même.

```yaml
phil_identity_provider:
    modules: { oidc: true }
    oidc:
        issuer: 'https://id.example.com'
        algorithm: RS256
```

## Points de terminaison

| Point de terminaison | Route | Méthode |
|---|---|---|
| Découverte (OpenID Connect Discovery 1.0, RFC 8414) | `/.well-known/openid-configuration` | `GET` |
| Jeu de clés | `/oidc/jwks` | `GET` |
| Autorisation | `/oidc/authorize` | `GET`, `POST` |
| Consentement | `/oidc/consent` | `POST` |
| Jeton (RFC 6749) | `/oidc/token` | `POST` |
| Userinfo | `/oidc/userinfo` | `GET`, `POST` |
| Révocation (RFC 7009) | `/oidc/revoke` | `POST` |
| Introspection (RFC 7662) | `/oidc/introspect` | `POST` |
| Déconnexion à l'initiative du client | `/oidc/logout` | `GET`, `POST` |

Le document de découverte et le jeu de clés sont publics et peuvent être mis en cache cinq minutes ; toutes les autres
réponses portent `Cache-Control: no-store`.

## Clients

Les clients sont enregistrés dans `phil_identity_client` par la console :

```sh
bin/console phil:identity-provider:client:create app "The App" \
    --redirect-uri=https://app.example/callback \
    --post-logout-redirect-uri=https://app.example/ \
    --scope=email --scope=offline_access
bin/console phil:identity-provider:client:create spa "The SPA" --redirect-uri=https://spa.example/cb --public
bin/console phil:identity-provider:client:create server "A server" --redirect-uri=https://server.example/cb --pkce-optional
bin/console phil:identity-provider:client:create worker "A worker" --jwks=config/clients/worker.jwks.json \
    --resource=https://api.example/ --scope=events:read
bin/console phil:identity-provider:client:list
bin/console phil:identity-provider:client:disable app --rotate-secret
bin/console phil:identity-provider:client:keys worker config/clients/worker-next.jwks.json
bin/console phil:identity-provider:client:disable app
```

Un client confidentiel reçoit un secret de 256 bits, montré une seule fois et conservé sous forme de SHA-256 ; il
s'authentifie par HTTP Basic (`client_secret_basic`) ou dans le corps du formulaire (`client_secret_post`). Un client
public — une application à page unique ou native — n'a aucun secret et se prouve par PKCE seul (`none`). Une adresse
de redirection est une adresse `https` absolue lue par `phil/url`, sans fragment — `http` seulement sur `localhost`
avec `oidc.allow_localhost` —, conservée sous sa forme canonique, que le client envoie exactement : les adresses sont
comparées comme des chaînes. `--first-party` marque un client du même propriétaire, qui n'a pas besoin de consentement.

Un client confidentiel peut enregistrer, à la place d'un secret, les clés publiques dont il signe ses assertions de
client (`--jwks`, un fichier JSON dont `keys` est une liste de JSON Web Keys) : il s'authentifie alors par
`private_key_jwt` seulement et ne reçoit aucun secret. Un client enregistre les ressources pour lesquelles il peut
demander des jetons avec `--resource`, lues et conservées comme une adresse de redirection ; un client qui demande des
jetons seulement pour lui-même enregistre des ressources et aucune adresse de redirection.

Chaque client envoie un défi PKCE, sauf un client confidentiel enregistré avec `--pkce-optional` : le profil Basic
d'OpenID Connect permet à un client confidentiel de se passer de PKCE, et il s'appuie alors sur son secret et sur le
`nonce` que son jeton d'identité rapporte. Un défi envoyé malgré tout est vérifié de la même façon, et un client public
ne peut jamais s'en passer.

## Authentification des clients

`oidc.client_authentication.methods` liste les méthodes que le fournisseur accepte, les quatre par défaut. Un client
s'authentifie par celle que nomme son enregistrement, jamais une autre :

| Méthode | Client | Ce qu'il envoie |
|---|---|---|
| `client_secret_basic` | avec un secret | l'identifiant et le secret en HTTP Basic, chacun encodé comme un formulaire |
| `client_secret_post` | avec un secret | `client_id` et `client_secret` dans le corps du formulaire |
| `private_key_jwt` | avec des clés | `client_assertion_type` à `urn:ietf:params:oauth:client-assertion-type:jwt-bearer` et `client_assertion` |
| `none` | public | `client_id` seul ; PKCE tient lieu de secret |

Un fournisseur qui n'accepte que les assertions de client et les clients publics omet les deux méthodes à secret :

```yaml
phil_identity_provider:
    oidc:
        client_authentication:
            methods: [private_key_jwt, none]
            clock_skew: 60
            assertion_max_lifetime: 300
```

Une assertion de client (RFC 7523, OpenID Connect Core, section 9) est un JSON Web Token signé avec l'une des clés du
client, celle que nomme son `kid`, avec l'algorithme propre à cette clé, et lu selon les bornes des [signatures JSON
Web](#signatures-json-web) :

- `iss` et `sub` sont l'identifiant du client ; `client_id`, quand il est envoyé, doit être le même ;
- `aud` nomme l'émetteur, ou le point de terminaison des jetons à l'origine de l'émetteur —
  `https://id.example.com/oidc/token` pour l'émetteur `https://id.example.com` —, jamais une adresse construite à partir
  de la requête ;
- `exp` est obligatoire, échu d'au plus `clock_skew`, et pas plus loin devant que `assertion_max_lifetime` ; `iat` et
  `nbf`, quand ils sont présents, sont devant d'au plus `clock_skew` ;
- `jti` est obligatoire, d'au plus 256 octets, et utilisé une seule fois : le fournisseur enregistre le SHA-256 du client
  et du `jti` dans `phil_identity_assertion` jusqu'à l'expiration de l'assertion, le décalage ajouté, et refuse la même
  assertion de nouveau — la clé de la ligne refuse la seconde de deux requêtes qui la présentent en même temps.
  `phil:identity-provider:purge` supprime les lignes expirées.

Chaque refus est `invalid_client`, sans dire quelle vérification a échoué. Une autre méthode envoyée à côté de
l'assertion est `invalid_request`. Les clés d'un client sont uniquement des JSON Web Keys publiques — RSA d'au moins
2048 bits, P-256 ou Ed25519, chacune avec son `kid` et son `alg`, au plus cinq — ; `phil:identity-provider:client:keys`
les remplace pour une rotation : publiez la clé suivante à côté de la clé courante, laissez le client basculer, puis
gardez la clé suivante seule.

## La demande d'autorisation

`AuthorizationRequestParser` lit la demande, dans cet ordre :

1. un client inconnu ou désactivé, ou une adresse de redirection qu'il n'a pas enregistrée exactement, est montré à la
   personne, jamais redirigé, de sorte que le fournisseur ne peut envoyer personne n'importe où ;
2. un paramètre donné deux fois, un objet de requête (`request_not_supported`, `request_uri_not_supported`), un type de
   réponse autre que `code`, un mode de réponse autre que `query`, l'absence de la portée `openid`, un défi PKCE autre
   que `S256`, de forme invalide, ou manquant quand le client en exige un, un `prompt` autre que `none`, `login` et
   `consent` — le fournisseur n'affiche aucun sélecteur de compte, donc `select_account` est refusé plutôt qu'ignoré —,
   `prompt=none` avec une autre valeur, un `max_age` de forme invalide reviennent au client avec l'erreur, l'état et
   l'émetteur (RFC 9207) ;
3. les portées que le fournisseur ou le client ne connaît pas sont écartées.

Ensuite : si personne n'est connecté, le pare-feu lui demande de se connecter et revient — `prompt=none` répond
`login_required` ; `prompt=login`, ou une authentification plus vieille que `max_age`, l'envoie d'abord à la page de
réauthentification. Avant l'une ou l'autre, la demande est gardée comme chemin cible du pare-feu, sous sa forme `GET`,
de sorte que chaque façon de se connecter — le formulaire de l'application compris, et après une demande envoyée par
`POST` — y revient ; un consentement manquant pour les portées affiche la page de consentement — `prompt=none` répond
`consent_required` ; un code revient avec l'état et `iss`.

## Jetons

Le code vit `oidc.code_lifetime` secondes et s'échange une seule fois, par son client, avec la même adresse de
redirection et le vérificateur PKCE de son défi — et sans vérificateur quand la demande ne portait aucun défi, de sorte
qu'on ne peut pas en ajouter après coup (RFC 9700, section 2.1.1) ; un deuxième échange révoque tous les jetons qui en
sont issus. Le code est marqué utilisé par une seule écriture conditionnelle, de sorte que de deux requêtes qui le
présentent en même temps, une seule reçoit des jetons ; l'autre est refusée comme une réutilisation. La réponse
contient :

- un jeton d'accès, un JSON Web Token de type `at+jwt` (RFC 9068) dont le `jti` a une ligne, de sorte qu'il peut être
  révoqué et introspecté ; son audience est l'émetteur, pour userinfo ;
- un jeton d'identité avec `iss`, `sub`, `aud`, `exp`, `iat`, `auth_time`, `nonce`, `amr` — les méthodes prouvées —,
  `sid` et `at_hash` ; les revendications des portées viennent d'userinfo ;
- avec la portée `offline_access`, un jeton d'actualisation opaque, renouvelé à chaque utilisation par une seule
  écriture conditionnelle : un jeton renouvelé présenté de nouveau — même par une requête qui l'a lu en même temps que
  celle qui l'a renouvelé — révoque toute sa famille, signe d'un jeton volé. [Les familles de jetons](#familles-de-jetons)
  disent ce qu'est une famille.

### Familles de jetons

Tous les jetons issus d'un même code d'autorisation — chaque jeton d'accès et chaque jeton d'actualisation, renouvellement
après renouvellement — partagent une famille, un ULID gardé sur chaque ligne. La famille est ce que le fournisseur
révoque quand quelque chose ne va pas :

- un code échangé deux fois révoque la famille de son premier échange ;
- un jeton d'actualisation présenté après son renouvellement — par le client qui l'a renouvelé, ou par qui l'a volé —
  révoque la famille, et l'événement `identity.oidc.token_reuse` est journalisé ;
- un jeton d'actualisation révoqué par le client (RFC 7009) emporte sa famille avec lui ;
- la fin de la session où la famille a été émise la révoque, comme le disent [les jetons d'actualisation et la
  session](#jetons-dactualisation-et-session).

Le renouvellement est un seul `UPDATE … WHERE rotated_at IS NULL AND revoked_at IS NULL` conditionnel, dont le nombre de
lignes nomme l'unique requête qui a renouvelé le jeton : de deux actualisations qui lisent le même jeton en même temps,
l'une reçoit de nouveaux jetons et l'autre est refusée comme une réutilisation, ce qui révoque la famille — les
nouveaux jetons du gagnant avec elle. Les tests jouent les deux ordres avec deux requêtes entrelacées
(`ConcurrentUseTest`) et avec deux connexions à la base de données (`tests/Integration`).

### Jetons d'actualisation et session

Avec `oidc.refresh_token_bound_to_session`, activée par défaut, un jeton d'actualisation émis dans une session ne vit
pas plus longtemps qu'elle :

- une fois la session fermée — déconnexion, fermeture depuis un autre appareil, ou purge —, le jeton d'actualisation est
  refusé avec `invalid_grant` et sa famille révoquée ;
- avec `oidc.session_max_age`, un jeton d'actualisation n'expire jamais plus tard que ce nombre de secondes après
  l'ouverture de la session, et est refusé passé ce délai ; un code échangé passé ce délai ne donne aucun jeton
  d'actualisation.

Un jeton d'actualisation émis sans session, et tous les jetons d'actualisation quand l'option est désactivée, gardent
`oidc.refresh_token_lifetime`. Le module `sessions` détient les sessions : sans lui, rien ne lie les jetons.

### Ressources et audience

Une demande de jeton peut nommer une `resource` (RFC 8707) que le client a enregistrée, comparée sous sa forme
canonique : le jeton d'accès est pour elle, c'est son `aud`, et l'introspection la répond comme `aud`. Un jeton pour une
ressource ne lit plus userinfo, dont l'audience est l'émetteur. Une ressource par requête : plusieurs, ou une que le
client n'a pas enregistrée, est `invalid_target`.

### Jetons pour un client lui-même

Un client confidentiel — avec un secret ou des clés — qui a enregistré des ressources demande un jeton pour lui-même par
l'octroi `client_credentials` (RFC 6749, section 4.4) :

```http
POST /oidc/token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials&resource=https%3A%2F%2Fapi.example%2F&scope=events%3Aread
&client_assertion_type=urn%3Aietf%3Aparams%3Aoauth%3Aclient-assertion-type%3Ajwt-bearer&client_assertion=eyJ…
```

Le jeton d'accès est pour la ressource nommée, ou la seule du client — plusieurs sans qu'aucune soit nommée est
`invalid_target` — ; son `sub` et son `client_id` sont le client ; ses portées sont celles demandées, parmi celles du
client, les portées qui concernent un compte (`openid`, `profile`, `email`, `phone`, `offline_access`) étant écartées —
toutes quand aucune n'est demandée. Aucun jeton d'identité ni jeton d'actualisation ne l'accompagne, et userinfo le
refuse. Un client public, ou sans ressources, reçoit `unauthorized_client`. Les portées des ressources se déclarent dans
`oidc.extra_scopes`, pour que les clients puissent les enregistrer.

### Revendications facultatives du jeton d'identité

| Option | Revendication |
|---|---|
| `oidc.id_token.acr` | `acr`, la même valeur dans chaque jeton d'identité, comme un niveau dont les clients sont convenus |
| `oidc.id_token.idp` | `idp`, le fournisseur d'identité que nomme chaque jeton d'identité |
| `oidc.id_token.session_start` | `session_start`, l'ouverture de la session du jeton d'identité, comme une NumericDate ; omise sans session |

Le document de découverte liste les revendications activées, et `acr_values_supported` nomme l'`acr`.

L'introspection répond `active: false` pour un jeton qui n'est pas celui du client qui demande, expiré, révoqué ou
renouvelé, et pour tous les jetons d'un compte supprimé ou désactivé, comme userinfo les refuse. Userinfo refuse aussi
les jetons d'un client désactivé ou supprimé.

Le sujet suit `oidc.subject.type` ; un jeton de l'octroi `client_credentials` a le client pour sujet.

| Type | `sub` |
|---|---|
| `account`, la valeur par défaut | l'ULID du compte, le même pour tous les clients |
| `public` | un identifiant opaque, le même pour tous les clients : le HMAC-SHA256 de l'ULID du compte sous `oidc.subject.salt`, en base64url |
| `pairwise` | un identifiant opaque par secteur : le HMAC-SHA256 du secteur du client et de l'ULID du compte sous le sel, de sorte que des clients de secteurs différents ne peuvent pas rapprocher leurs personnes par lui (OpenID Connect Core, section 8.1) |

Le secteur d'un client est l'hôte que nomme `--sector-identifier` — partagé par les clients d'un même propriétaire, qui
voient alors le même sujet —, ou l'hôte de ses adresses de redirection quand elles en partagent un seul, ou sinon son
propre identifiant. Le sel, de 32 octets au moins, vient du coffre des secrets et ne change jamais une fois que les
clients ont vu des sujets : un autre sel donne à chaque compte un autre sujet. L'ULID reste interne avec les types
opaques ; le jeton d'identité, le jeton d'accès, l'introspection, userinfo, le jeton de déconnexion et la vérification
d'un `id_token_hint` emploient tous le sujet du client. Changer le type change tous les sujets que les clients
connaissent. Userinfo prend le jeton d'accès dans l'en-tête `Authorization` ou, par `POST`, dans le membre
`access_token` d'un corps de formulaire (RFC 6750), jamais dans la requête et jamais des deux façons.

## Signatures JSON Web

Chaque jeton que le fournisseur signe, et chaque jeton qu'il relit — les assertions de client comprises —, passe par `web-token/jwt-library` : `JWKFactory`
crée les clés, `JWSBuilder` signe, `JWSVerifier` vérifie, `CompactSerializer` écrit et lit la forme compacte.
`Application\Jose\Jws` borne un jeton avant que la bibliothèque le voie — au plus 8 Kio, trois parties, un en-tête et des
revendications qui sont des objets JSON de profondeur bornée, un en-tête qui nomme un algorithme et une clé, ni `crit`
ni `b64` —, puis le vérifie avec l'unique algorithme de la clé que son `kid` nomme ; `none` et les algorithmes HMAC ne
sont jamais enregistrés. `Application\Jose\PrivateKey` garde une clé sous forme de JSON Web Key de la bibliothèque : une
clé RSA de 3072 bits, une clé P-256 ou une clé Ed25519.

`tests/Benchmark/JwsBench.php` mesure chaque algorithme. La bibliothèque reconstruit le PEM d'une clé RSA à chaque
signature, ce qui fait de RS256 le plus lent de loin — des millisecondes là où ES256 et EdDSA prennent des dizaines de
microsecondes — : choisissez `ES256` ou `EdDSA` quand les clients les prennent en charge.

## Portées et revendications

| Portée | Revendications |
|---|---|
| `openid` | `sub` |
| `email` | `email`, `email_verified` |
| `profile` | `name` |
| `phone` | `phone_number`, `phone_number_verified` |
| `offline_access` | un jeton d'actualisation |

L'application fournit d'autres revendications : un service qui implémente `Application\Contract\ClaimsProvider` —
autoconfiguré, ou étiqueté `phil_identity_provider.claims_provider` — reçoit le compte, les portées du jeton d'accès et
son client, et répond les revendications que ces portées accordent, comme `locale` pour `profile`. Userinfo les ajoute
après les revendications des portées, fournisseur après fournisseur. Un fournisseur qui répond une revendication que le
fournisseur fixe — `sub`, `email`, `acr` et les autres de `UserClaims::RESERVED` — ou une qu'un fournisseur précédent a
répondue fait échouer la requête avec une `LogicException` : une revendication n'en remplace jamais une autre.

```php
use Phil\IdentityProviderBundle\Application\Contract\ClaimsProvider;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;

final readonly class LocaleClaims implements ClaimsProvider
{
    public function claims(Account $account, array $scopes, string $clientId): array
    {
        return \in_array('profile', $scopes, true) ? ['locale' => 'fr-CA'] : [];
    }
}
```

## Clés et rotation

`SigningKeys` garde les clés dans `phil_identity_signing_key`, leur JSON Web Key privée scellée par le `SecretCipher`. Un
fournisseur sans clé en crée et en active une à son premier jeton. `phil:identity-provider:rotate-keys`, lancée chaque
jour, retire les clés retirées depuis plus longtemps que `oidc.retired_key_retention`, active la clé en attente une fois
qu'elle a été publiée pendant `oidc.key_activation_delay` — retirant celle qui était active —, et crée la prochaine clé
en attente. Les clients qui mettent le jeu de clés en cache voient chaque clé avant qu'elle signe, et la gardent jusqu'à
l'expiration du dernier jeton qu'elle a signé. Après l'exposition d'une clé, `--now` active tout de suite la clé en
attente.

La première clé et chaque rotation se font sous un seul verrou, `phil_identity_provider.signing_keys`, pris à la
`LockFactory` de `oidc.lock_factory` — `lock.factory` par défaut —, les clés étant relues une fois le verrou tenu : des
instances qui démarrent, ou font la rotation, en même temps créent une clé, pas une chacune. Le magasin du verrou doit
être partagé par tous les serveurs : un magasin `flock` ne protège qu'un serveur. Chaque lecture des clés règle encore
ce qu'un magasin non partagé, ou des clés écrites avant, ont pu laisser, de la même façon sur chaque instance : de plusieurs clés actives, toutes sauf la dernière activée —
le plus grand `kid` départageant une égalité — sont retirées, et restent publiées pour les jetons qu'elles ont signés ;
de plusieurs clés en attente, toutes sauf la plus ancienne sont supprimées, n'ayant rien signé. Le règlement est
journalisé comme `identity.oidc.key_rotated`, avec les clés retirées et le nombre de clés supprimées.

## Consentement et déconnexion

La page de consentement nomme le client et chaque portée en mots ; le consentement couvre les portées accordées, et une
demande de plus redemande. Le compte le retire depuis la page de ses sessions, ce qui révoque les jetons du client.

`/oidc/logout` avec un `id_token_hint` émis pour le compte connecté déconnecte sur-le-champ ; sans lui, il demande à la
personne de confirmer, de sorte qu'un lien ne peut déconnecter personne. Il retourne ensuite au
`post_logout_redirect_uri` que le client a enregistré, avec l'état, ou montre que la personne est déconnectée.

### Déconnexion par canal arrière

Avec `oidc.backchannel_logout.enabled`, le fournisseur dit aux clients qu'une session a pris fin (OpenID Connect
Back-Channel Logout 1.0). Un client enregistre l'adresse sur laquelle il écoute :

```sh
bin/console phil:identity-provider:client:create app "The App" --redirect-uri=https://app.example/callback \
    --backchannel-logout-uri=https://app.example/backchannel-logout
```

Chaque fois qu'une session se ferme — déconnexion ici ou par la déconnexion à l'initiative du client, fermeture depuis
un autre appareil, ou fermeture avec toutes les autres sessions après un changement de mot de passe —, chaque client à
qui le fournisseur a émis des jetons dans cette session reçoit un `Infrastructure\Messenger\SendLogoutToken` sur le bus
de `oidc.backchannel_logout.bus`, qui porte les identifiants du client, du compte et de la session, rien de plus. Son
*handler* signe un jeton de déconnexion à son exécution — un JSON Web Token de type `logout+jwt`, `iss` le
fournisseur, `aud` le client, `sub` le compte, `sid` la session que nommait le jeton d'identité, `iat`, un `exp` deux
minutes plus tard, un nouveau `jti`, l'événement `http://schemas.openid.net/event/backchannel-logout` et aucun
`nonce` — et le poste comme `logout_token` dans un corps de formulaire à l'adresse du client, sans suivre aucune
redirection, en `oidc.backchannel_logout.timeout` secondes. Une réponse autre que `200` ou `204`, ou aucune, lève
`DeliveryFailed`, et Messenger réessaie selon la stratégie de nouvelle tentative du transport ; un client supprimé,
désactivé, ou sans adresse est sauté. Routez le message vers un transport asynchrone, pour que la déconnexion n'attende
jamais un client :

```yaml
framework:
    messenger:
        transports:
            identity: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
            Phil\IdentityProviderBundle\Infrastructure\Messenger\SendLogoutToken: identity
```

Le document de découverte annonce alors `backchannel_logout_supported` et `backchannel_logout_session_supported`.

## Non implémenté

L'enregistrement dynamique de clients, les objets de requête, le paramètre claims, la déconnexion par canal avant,
l'échange de jetons et la revendication `act` (RFC 8693), un jeu de clés récupéré à partir du `jwks_uri` d'un client,
le TLS mutuel et DPoP sont prévus.
