<!-- phil-dev source-sha256=e280572e0fcacb28a61e752864cc5328a70dddde3fcb3ba379e63306f6d2e828 -->
# Cross-Account Protection

La Cross-Account Protection (RISC) de Google avertit les applications auxquelles une personne se connecte avec Google
quand il arrive quelque chose à son compte Google : ses sessions ou ses jetons ont été révoqués, le compte a été
désactivé — détourné, ou créé en masse —, réactivé, ou supprimé. Google envoie chaque événement sous forme de jeton
d'événement de sécurité (RFC 8417), un JWT qu'il signe, au point de terminaison du récepteur (RFC 8935).

## L'activer

```yaml
phil_identity_provider_google:
    risc:
        enabled: true
        holds_pool: cache.identity_holds   # a durable pool, see below
```

Le point de terminaison est `POST /identity/google/risc` avec le préfixe de la recette, route
`phil_identity_provider_google_risc`. Il est sans état, et laissé à l'accès anonyme par le pare-feu : c'est la
signature du jeton qui authentifie Google.

## Ce que vérifie le point de terminaison

1. Le corps est `application/secevent+jwt` — ou `application/jwt` —, de 16 Kio au plus, lu jusqu'à un octet au-delà et pas plus loin, un JWT en forme compacte.
2. Sa signature : RS256 seulement, contre les clés que nomme le document de configuration
   `https://accounts.google.com/.well-known/risc-configuration`, lu par phil/safe-http et mis en cache, par
   l'`OidcSignatureVerifier` de Symfony.
3. Ses revendications : l'émetteur `https://accounts.google.com/`, l'un des `risc.audiences` parmi ses audiences, un
   identifiant (`jti`), une heure d'émission qui n'est pas plus vieille que `risc.max_age` et au plus cinq minutes dans
   le futur, et un objet `events` de 16 événements au plus. Un jeton sans `events` — un jeton d'identité, par exemple —
   est refusé.
4. Un jeton dont l'identifiant a déjà été traité est acquitté sans qu'on agisse de nouveau.

| Réponse | Quand |
|---|---|
| `202 Accepted` | On a agi sur le jeton, ou il a été reçu auparavant. |
| `400` avec `{"err": "…", "description": "…"}` | Le jeton est refusé : `invalid_request` (ce n'est pas un jeton d'événement de sécurité, trop long, trop vieux), `authentication_failed` (la signature), `invalid_issuer`, `invalid_audience`. |
| `503` avec `Retry-After: 60` | Les clés de Google n'ont pas pu être lues : Google renverra le jeton plus tard. |
| `404` | `risc.enabled` est désactivé. |

Un jeton n'est mémorisé qu'une fois qu'on a agi sur lui, pour qu'un échec à mi-chemin — la base de données hors service
— laisse la nouvelle tentative de Google terminer le travail.

## Ce que fait chaque événement

| Événement | Ce que fait le récepteur |
|---|---|
| `sessions-revoked` | Ferme les sessions du compte lié au compte Google. |
| `tokens-revoked` | Les ferme aussi : la personne a retiré l'accès de l'application chez Google. |
| `token-revoked` | Nomme un jeton par son empreinte ; le paquet n'en a conservé aucun, il ne nomme donc aucun compte. |
| `account-disabled` | Les ferme ; avec la raison `hijacking`, retient aussi le compte Google (voir plus bas). |
| `account-enabled` | Libère le compte Google. |
| `account-purged` | Ferme les sessions : le compte Google a été supprimé. |
| `account-credential-change-required` | Rien de plus que l'événement : l'application peut demander à la personne de revoir son compte. |
| `verification` | Rien : c'est le test du flux par Google. |
| `session-revoked` (Shared Signals Framework, CAEP) | Ferme les sessions, pour un fournisseur qui l'envoie. |

Les sessions sont fermées par `Sessions::closeAll()` du bundle, qui révoque aussi les jetons que le bundle y a émis
comme fournisseur OpenID. Sans le module `sessions` du bundle, rien n'est fermé, et l'événement est tout de même
envoyé.

Un événement sur un compte Google lié à aucun compte ne change rien d'autre qu'une rétention. Un événement d'un autre
type est laissé de côté, comme le demande la RFC 8417. Un événement dont le sujet n'est pas un émetteur et un
identifiant de sujet — un jeton que Google nomme par son empreinte, que ce paquet n'a jamais conservé — ne nomme aucun
compte.

Après avoir agi, le récepteur envoie un `SecurityEventReceived` par événement, avec le fournisseur, le type,
l'identifiant du compte, la raison et les sessions fermées : voir [Points d'extension](extension-points.md#the-event).

## Comptes retenus

Un compte Google désactivé parce qu'il a été détourné est retenu : `GoogleSignIn` le refuse, avec `external.failed`,
jusqu'à ce qu'un événement `account-enabled` le libère. Les rétentions sont gardées par `CacheSubjectHolds` dans un
bassin PSR-6, sans expiration, sous l'empreinte du fournisseur et du sujet. Le bassin doit conserver ce qu'on lui
confie — un bassin Doctrine DBAL, PDO ou Redis sans éviction —, et non un cache sur système de fichiers qu'un
déploiement vide, sans quoi un compte retenu est de nouveau admis :

```yaml
framework:
    cache:
        pools:
            cache.identity_holds:
                adapter: cache.adapter.doctrine_dbal
```

`risc.hold_hijacked: false` désactive les rétentions ; les sessions sont tout de même fermées.

## Enregistrer le flux chez Google

Google envoie des événements une fois que l'application a enregistré son point de terminaison, avec l'API RISC de son
projet Google Cloud :

1. activez l'**API RISC** dans le projet du client OAuth ;
2. créez un compte de service avec le rôle **RISC Configuration Admin**, et connectez-vous avec lui pour obtenir un
   jeton d'accès à l'API RISC ;
3. appelez `https://risc.googleapis.com/v1beta/stream:update` avec la méthode de livraison par envoi (*push*),
   l'adresse du point de terminaison et les événements demandés ;
4. appelez `https://risc.googleapis.com/v1beta/stream:verify` avec un état : un événement `verification` revient au
   point de terminaison, qui répond `202`.

Le guide de Google, « Protect user accounts with Cross-Account Protection », donne chaque requête. Le paquet ne fait pas
ces appels : ils exigent la clé du compte de service, qui appartient aux outils de l'exploitant, non à l'application.

## Autres fournisseurs

Le récepteur est conçu pour tout émetteur du Shared Signals Framework. `TransmitterSettings` nomme le fournisseur dont
les identités liées font l'objet des événements, l'émetteur de ses jetons, les audiences, les émetteurs des sujets et
l'âge accepté ; `TransmitterSettings::google()` est celui de Google. Le paquet d'un autre fournisseur construit un
`SecurityEventReceiver` avec ses propres réglages et l'`OidcSignatureVerifier` de son propre document de configuration,
et route un `RiscController` vers lui.
