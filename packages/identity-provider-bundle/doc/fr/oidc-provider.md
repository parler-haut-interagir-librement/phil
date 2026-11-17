<!-- phil-dev source-sha256=48c2ee453e5a3e247e97e6a2a054652c91643908dd4fc41c0e8fcade19a86357 -->
# Fournisseur OpenID Connect

Le module `oidc` fait du site un fournisseur OpenID Connect pour ses clients : d'autres sites et applications
connectent leurs utilisateurs avec les comptes de celui-ci. Il implémente le flux du code d'autorisation avec PKCE, le
seul flux que conservent les meilleures pratiques de sécurité actuelles d'OAuth 2.0 (RFC 9700).

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
bin/console phil:identity-provider:client:list
bin/console phil:identity-provider:client:disable app --rotate-secret
bin/console phil:identity-provider:client:disable app
```

Un client confidentiel reçoit un secret de 256 bits, montré une seule fois et conservé sous forme de SHA-256 ; il
s'authentifie par HTTP Basic (`client_secret_basic`) ou dans le corps du formulaire (`client_secret_post`). Un client
public — une application à page unique ou native — n'a aucun secret et se prouve par PKCE seul (`none`). Une adresse
de redirection est une adresse `https` absolue lue par `phil/url`, sans fragment — `http` seulement sur `localhost`
avec `oidc.allow_localhost` —, conservée sous sa forme canonique, que le client envoie exactement : les adresses sont
comparées comme des chaînes. `--first-party` marque un client du même propriétaire, qui n'a pas besoin de consentement.

Chaque client envoie un défi PKCE, sauf un client confidentiel enregistré avec `--pkce-optional` : le profil Basic
d'OpenID Connect permet à un client confidentiel de se passer de PKCE, et il s'appuie alors sur son secret et sur le
`nonce` que son jeton d'identité rapporte. Un défi envoyé malgré tout est vérifié de la même façon, et un client public
ne peut jamais s'en passer.

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
  celle qui l'a renouvelé — révoque toute sa famille, signe d'un jeton volé.

L'introspection répond `active: false` pour un jeton qui n'est pas celui du client qui demande, expiré, révoqué ou
renouvelé, et pour tous les jetons d'un compte supprimé ou désactivé, comme userinfo les refuse. Userinfo refuse aussi
les jetons d'un client désactivé ou supprimé.

Le sujet est l'ULID du compte, le même pour tous les clients. Userinfo prend le jeton d'accès dans l'en-tête
`Authorization` ou, par `POST`, dans le membre `access_token` d'un corps de formulaire (RFC 6750), jamais dans la requête
et jamais des deux façons.

## Signatures JSON Web

Chaque jeton que le fournisseur signe, et chaque jeton qu'il relit, passe par `web-token/jwt-library` : `JWKFactory`
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

## Non implémenté

L'enregistrement dynamique de clients, les objets de requête, les sujets par paire, le paramètre claims, la déconnexion
par canal avant et arrière, l'échange de jetons et la revendication `act` (RFC 8693), et DPoP sont prévus.
