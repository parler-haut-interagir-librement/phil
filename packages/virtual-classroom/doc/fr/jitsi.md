<!-- phil-dev source-sha256=30353504a671817b3252d7dbf5109c6989c41320c6371c2128279a740421dd53 -->
# Jitsi

`Phil\VirtualClassroom\Jitsi\Jitsi` écrit les liens d'un serveur Jitsi Meet : une salle existe dès que quelqu'un y
entre, par un lien qui porte un jeton Web JSON. L'adaptateur n'appelle aucun serveur.

## Un serveur propre à l'application

```php
Jitsi::selfHosted(
    Url::fromString('https://meet.example.com'),
    'my-app',             // the application identifier its prosody expects
    $appSecret,           // its application secret, 16 bytes or more
    $clock,
    tokenTtl: 10_800,     // how long a link lets its participant in, 60 seconds to a day
    webhookSecret: null,  // the secret of the notifications, when a module sends them
    audience: 'jitsi',    // the `aud` claim
    subject: null,        // the `sub` claim, the host of the server by default
);
```

Le jeton est signé en HS256 et contient `aud`, `iss` — l'identifiant de l'application —, `sub`, `room`, `iat`, `nbf`,
`exp` et `context.user` : `id`, l'identifiant du participant, `name`, `moderator` sous forme de booléen et
`affiliation`, `owner` pour un modérateur et `member` pour un participant, à l'intention des modules de jeton qui la
lisent. Le lien est `https://meet.example.com/<room>?jwt=<token>`.

## JaaS

```php
Jitsi::jaas('vpaas-magic-cookie-…', 'vpaas-magic-cookie-…/abc123', $privateKeyPem, $clock, webhookSecret: $secret);
```

Le jeton est signé en RS256 sous la clé privée de la clé d'API, son identifiant dans l'en-tête `kid`. Il contient `aud`
`jitsi`, `iss` `chat`, `sub` l'identifiant de l'application, `room`, les instants, `context.user` avec `moderator`
valant `"true"` ou `"false"`, et `context.features` : `recording` pour un modérateur d'une salle enregistrée,
diffusion en direct, transcription et appels sortants désactivés. Le lien est
`https://8x8.vc/<application>/<room>?jwt=<token>` ; un autre serveur peut être donné.

## Notifications

Avec un secret de webhook, `receive()` lit les notifications telles que JaaS les poste, signées dans
`X-Jaas-Signature` : `t=<moment>,v1=<signature>`, le HMAC-SHA-256 en base64 de l'instant, d'un point et du corps.
L'instant est à moins de cinq minutes de l'horloge ; n'importe quelle `v1` peut correspondre ; le corps fait 256 Kio au
plus.

| `eventType` | Événement |
|---|---|
| `PARTICIPANT_JOINED` | `ParticipantJoined`, le participant par `data.id`, l'identifiant de son jeton |
| `PARTICIPANT_LEFT` | `ParticipantLeft` |
| `ROOM_DESTROYED` | `RoomEnded` |
| `RECORDING_UPLOADED` | `RecordingReady` avec un enregistrement `download`, son lien valide un jour |

La salle est lue dans `fqn`, `<application>/<room>`, et doit être celle de l'application pour JaaS. Un participant dans
une salle de sous-groupe, un participant sans identifiant ou un autre événement ne donne aucun événement.

## Limites

- Jitsi n'a pas d'API pour terminer une salle, dire qui s'y trouve ou lister ses enregistrements : `end()`,
  `presences()` et `recordings()` lèvent `Unsupported`. Une salle se termine quand son dernier participant part ; un
  lien cesse de laisser entrer quiconque quand son jeton expire.
- Un serveur propre à l'application n'envoie aucune notification de lui-même. Un module prosody qui poste les mêmes
  événements, signés de la même façon, les rend lisibles ; sans lui, aucune assiduité n'est connue.
