<!-- phil-dev source-sha256=e56887dfa86f9783b810e7f7c8f4ffc2e8a94be85226b930fef1d8ac0ac4ab7a -->
# Salles et liens d'accès

## Le port

`Phil\VirtualClassroom\Contract\VirtualClassroom` est un serveur de conférence. Chaque adaptateur l'implémente et dit
dans `capabilities()` ce qu'il sait faire en plus d'ouvrir une salle et d'écrire des liens d'accès :

| Capacité | Appel | BigBlueButton | Jitsi |
|---|---|---|---|
| — | `open(RoomSpec): OpenedRoom` | `create` | aucun appel |
| — | `joinUrl(OpenedRoom, Participant, Role): string` | un lien `join` signé | un lien avec un jeton |
| `end` | `end(RoomId)` | `end` | `Unsupported` |
| `presences` | `presences(RoomId): Presences` | `getMeetingInfo` | `Unsupported` |
| `recordings` | `recordings(RoomId): list<Recording>` | `getRecordings` | `Unsupported` |
| `webhooks` | `receive(Webhook): list<RoomEvent>` | fin, enregistrement, assiduité | avec un secret de webhook |

Un appel à ce que l'adaptateur ne sait pas faire lève `Unsupported` ; interrogez d'abord `capabilities()`.

## La vie d'une salle

Une salle est nommée par un `RoomId` : de 2 à 64 lettres minuscules, chiffres, `-` ou `_`, commençant par une lettre ou
un chiffre. `RoomId::generate()` tire 32 chiffres hexadécimaux de 16 octets aléatoires. L'identifiant est
l'identifiant de réunion de BigBlueButton et le nom de salle de Jitsi, qui compare les noms sans tenir compte de la
casse : un identifiant en minuscules nomme la même salle sur les deux. Là où n'importe qui peut entrer dans une salle
Jitsi par son nom, un identifiant tiré au hasard est celui à employer.

`RoomSpec` décrit la salle à ouvrir :

| Champ | Sens |
|---|---|
| `id` | Le `RoomId`. |
| `title` | Ce que voient les participants, de 2 à 64 caractères sans caractère de contrôle, sans espaces aux extrémités. |
| `record` | Si la séance peut être enregistrée. |
| `maxMinutes` | De 1 à 1440, ou null pour la limite propre au serveur. |
| `endedUrl` | L'adresse `phil/url` que le serveur appelle quand la salle se termine. |
| `eventsUrl` | L'adresse des notifications signées : un enregistrement prêt, l'assiduité d'une séance. |

`open()` ouvre la salle, ou la trouve déjà en cours : l'appeler de nouveau ne change rien. Elle rend un `OpenedRoom`
avec la clé de la séance quand le serveur en a une — l'heure de création de BigBlueButton, que reprend un lien d'accès
pour que le lien d'une séance antérieure de la même salle soit refusé. Ouvrez la salle avant d'écrire chaque lot de
liens, à la première arrivée par exemple.

`end()` termine la salle pour tous ceux qui s'y trouvent ; une salle déjà disparue n'est pas une erreur. `presences()`
rend le `RoomState` — en cours, en attente de son premier participant, ou disparue — et les participants qui s'y
trouvent. `recordings()` liste ce que le serveur a enregistré.

## Participants et rôles

Un `Participant` est un `ParticipantId` — l'identifiant stable de son compte, de 1 à 190 lettres, chiffres, `.`, `_`,
`:` ou `-`, jamais une adresse courriel — et le nom que la salle affiche, de 1 à 128 caractères sans caractère de
contrôle. L'identifiant voyage jusqu'au serveur dans le lien d'accès et revient dans ses notifications ; le nom ne va
qu'au serveur.

Un `Role` est `Moderator` — qui anime la salle : couper les micros, partager, enregistrer, terminer — ou `Attendee`.
L'application le décide ; le lien le porte, signé.

## Notifications

`receive(Webhook)` lit une notification telle qu'elle est parvenue à l'application : sa méthode, ses en-têtes, sa
requête, son corps et son **canal** — `Webhook::ENDED` pour l'adresse de la fin d'une salle, `Webhook::EVENTS` pour les
événements signés, `Webhook::ACCOUNT` pour l'adresse unique d'un compte. L'adaptateur vérifie ce que son fournisseur
signe et rend les `RoomEvent` dans l'ordre où ils se sont produits : `ParticipantJoined`, `ParticipantLeft`, `RoomEnded`
et `RecordingReady`. Une notification d'autre chose donne une liste vide ; une notification refusée lève
`InvalidWebhook`.

Ce que l'application vérifie elle-même : que l'adresse à laquelle la notification est parvenue est une adresse qu'elle
a donnée au serveur — sa preuve, que signe phil/virtual-classroom-bundle —, et que la salle nommée est l'une de ses
salles sur ce serveur.
