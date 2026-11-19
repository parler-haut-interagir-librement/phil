<!-- phil-dev source-sha256=b992f7709b3471a0afc348dbe3096ca8745f0b82a7a3a2e4f7be6f166f54560d -->
# BigBlueButton

`Phil\VirtualClassroom\BigBlueButton\BigBlueButton` joint un serveur BigBlueButton par son API.

```php
new BigBlueButton(
    $httpClient,                                                  // Symfony's HttpClientInterface
    Url::fromString('https://bbb.example.com/bigbluebutton/api/'),
    $secret,                                                      // the shared secret, 16 bytes or more
    $clock,                                                       // a PSR-20 clock
    ChecksumAlgorithm::Sha256,                                    // Sha256, Sha384 or Sha512
    new HttpBudget(timeout: 5.0, maxDuration: 10.0, maxBytes: 1_048_576),
    analytics: true,
    signedAnalytics: true,
);
```

L'adresse est en https. Le secret n'est jamais envoyé : chaque appel porte une **somme de contrôle**, l'empreinte du
nom de l'appel, de sa chaîne de requête et du secret, parmi celles de SHA-2 que le serveur accepte depuis la 2.5 — son
`supportedChecksumAlgorithms`. SHA-1, la seule des serveurs plus anciens, n'est pas offerte.

## Ouvrir et rejoindre

`open()` appelle `create` avec le nom, l'identifiant de réunion, `record`, `duration` quand il est donné, et les
rappels : `meetingEndedURL` à partir de `endedUrl`, `meta_bbb-recording-ready-url` à partir de `eventsUrl` quand la
salle enregistre, et `meta_analytics-callback-url` à partir de `eventsUrl` quand `analytics` est activé. Une salle déjà
en cours répond avec son heure de création ; une salle en cours avec d'autres réglages est refusée avec `idNotUnique`,
et son heure de création est lue dans `getMeetingInfo`.

`joinUrl()` écrit le lien `join` : `fullName`, `meetingID`, `role` — `MODERATOR` ou `VIEWER` —, `userID`, et
`createTime` tiré de l'`OpenedRoom`, pour que le serveur refuse le lien d'une séance antérieure. Le serveur n'est pas
appelé.

## Terminer, présences, enregistrements

`end()` appelle `end` ; `notFound` désigne une salle déjà disparue. BigBlueButton répond qu'il a envoyé la demande : la
salle se termine un instant plus tard.

`presences()` appelle `getMeetingInfo` : `running` donne l'état, `notFound` une salle disparue, et chaque `attendee`
dont le `userID` est un `ParticipantId` est listé une seule fois. Un participant arrivé sans `userID` — par un lien que
l'application n'a pas écrit — ne l'est pas.

`recordings()` appelle `getRecordings` pour la salle : un `Recording` par format de lecture — `presentation`, `video`,
`podcast` —, avec son adresse, s'il est publié, et le début et la fin de la séance enregistrée. Un format dont le type
ou l'adresse est illisible est écarté.

## Notifications

| Canal | Requête | Événement |
|---|---|---|
| `ended` | GET avec `meetingID`, envoyé à `meetingEndedURL` | `RoomEnded`, maintenant |
| `events` | POST avec `signed_parameters`, un jeton signé en HS256 sous le secret partagé | `RecordingReady`, sans détails |
| `events` | POST de JSON depuis le rappel d'analyse de l'apprentissage | `ParticipantJoined` et `ParticipantLeft` pour chaque séjour |

La fin d'une salle est un simple GET : la preuve est l'adresse, que l'application signe et que BigBlueButton ne montre
pas aux participants. Les adresses `meta_` sont visibles des participants de la salle ; ce qui parvient à `eventsUrl`
doit donc porter la signature du serveur : toujours celle du jeton d'enregistrement, et, pour l'assiduité, son jeton
porteur — un jeton Web JSON signé sous le secret partagé, son expiration vérifiée —, sauf si `signedAnalytics` est
désactivé pour un serveur qui n'en envoie aucun. Avec `analytics` désactivé, l'assiduité est ignorée.

L'assiduité lit `meeting_id`, puis le `ext_user_id` de chaque participant — le `userID` du lien d'accès — avec ses
`joins` et ses `leaves` : des instants en secondes ou en millisecondes, sous forme de nombres, de chiffres, de dates
RFC 3339 ou d'objets avec un `timestamp`. Au plus 1000 participants et 200 séjours chacun sont lus ; une notification
de 1 Mio au plus.

## Limites

- L'assiduité arrive à la fin de la séance, une fois l'analyse de l'apprentissage écrite ; d'ici là, `presences()`,
  interrogée régulièrement, note qui arrive et qui repart au moment où on la demande.
- Un enregistrement prêt ne nomme que l'enregistrement : `recordings()` le décrit.
- Le rappel signé d'analyse de l'apprentissage — l'assiduité postée avec son jeton porteur — n'est vérifié que contre
  des réponses simulées de BigBlueButton : il n'a pas encore été vérifié contre un vrai serveur BigBlueButton 3.x.
