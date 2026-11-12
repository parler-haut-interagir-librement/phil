<!-- phil-dev source-sha256=f88f3175f556e9b5a252ac72d2cdc346f8028a9f7ff15b8b5f59169446790574 -->
# Synchronisation

## Les connexions

Une `CalendarConnection` est un calendrier de quelqu'un, lié au site :

| Champ | Ce qu'il contient |
|---|---|
| `id` | L'identifiant que l'application lui donne, de 1 à 128 octets |
| `provider` | Le nom de l'adaptateur : `ics`, `caldav`, `google`, `microsoft`, ou un de ceux de l'application |
| `calendar` | Le calendrier dans les termes de l'adaptateur : l'adresse d'un flux ou d'une collection, un identifiant de calendrier |
| `timezone` | Le fuseau des heures qu'un flux écrit sans fuseau, et de ses journées entières ; UTC par défaut |

L'application garde ses connexions et les donne par `Contract\ConnectionRepository`. Une connexion ne contient aucun
identifiant de connexion.

## Les identifiants de connexion

`Contract\CredentialStore` donne les `Credentials` d'une connexion à chaque appel : `Credentials::bearer()` pour un
jeton d'accès OAuth, `Credentials::basic()` pour un nom d'utilisateur et un mot de passe d'application,
`Credentials::none()` pour un flux.

L'application obtient les jetons, les garde chiffrés et les rafraîchit avant de les donner. Le paquet ne les garde
jamais : `Credentials` ne peut pas être sérialisé, et son affichage ne montre que son genre. Quand un service les
refuse, `ProviderFailed::isUnauthorized()` est vrai, et l'application peut rafraîchir le jeton ou demander au membre de
lier de nouveau le calendrier.

## Les plages occupées

`CalendarSync::refreshBusy()` lit les plages occupées d'une connexion dans une `SyncWindow` — 400 jours au plus —, les
coupe à la fenêtre, les trie et joint celles qui se chevauchent ou se touchent, puis les donne à `Contract\BusyStore`,
qui remplace ce que la connexion avait dans cette fenêtre. Une plage jointe n'est provisoire que si toutes celles
qu'elle joint le sont.

Quand le service échoue, ou que sa réponse est illisible, l'exception est levée avant que le dépôt soit appelé : les
plages lues en dernier restent telles qu'elles étaient. Un calendrier qui donne plus de 10 000 plages dans une fenêtre
est refusé plutôt que lu à moitié, car un calendrier à moitié lu laisserait libres des heures qui ne le sont pas.

Une `BusyPeriod` contient un début, une fin, en UTC, et si elle est provisoire. Rien d'autre n'est lu du calendrier,
donc rien d'autre ne peut être stocké.

## Les événements écrits

`CalendarSync::publish()` écrit un `OutboundEvent` dans une connexion ; écrire de nouveau le même identifiant le
remplace. `CalendarSync::withdraw()` le retire ; un événement qui n'y est pas n'est pas une erreur.

Un `OutboundEvent` contient :

| Champ | Ce qu'il contient |
|---|---|
| `uid` | Son identifiant, stable d'une écriture à l'autre : de 1 à 255 caractères ASCII visibles |
| `title` | De 1 à 255 caractères |
| `start`, `end` | Dans le fuseau de l'événement, la fin exclue ; de minuit à minuit pour les journées entières |
| `allDay` | Des journées entières |
| `description`, `location`, `url` | Facultatifs : 8 000 caractères, 255 caractères, une adresse de `phil/url` |
| `sequence` | Sa révision, 0 ou plus |

Ce que l'événement montre est le choix de l'application : un site qui ne fait que bloquer la plage écrit un titre comme
« Occupé » et rien d'autre.

## Les messages

Deux messages laissent un *worker* faire la synchronisation, chacun ne portant que des identifiants :

| Message | Ce qu'il porte | Son *handler* |
|---|---|---|
| `RefreshBusyTimes` | La connexion, le début de la fenêtre en UTC, ses jours | Lit les plages occupées de la fenêtre |
| `SyncEvent` | La connexion, l'identifiant de l'événement | Demande à `Contract\OutboundEvents` l'événement tel qu'il est maintenant, l'écrit, ou le retire s'il n'y en a pas |

`RefreshBusyTimes::of($connectionId, $from, $days)` écrit le début en `DateTimeInterface::ATOM`. Comme le *handler* de
`SyncEvent` lit l'événement à son exécution, un message envoyé avant un changement écrit ce changement, et un message
envoyé pour un événement supprimé depuis le retire.

## Les échecs

| Exception | Quand |
|---|---|
| `UnknownConnection` | La connexion est inconnue, ou aucun adaptateur de son nom n'est enregistré pour ce qui a été demandé |
| `ProviderFailed` | Le service a refusé les identifiants de connexion, répondu par une erreur, refusé la requête, ou n'a pas répondu |
| `InvalidCalendarData` | Sa réponse n'est pas celle qu'elle devrait être, ou dépasse la taille permise |
| `InvalidSync` | Une valeur est refusée : une fenêtre, une connexion, un événement, des identifiants de connexion, une adresse de calendrier |

Toutes implémentent `Exception\CalendarSyncException`. Leurs messages nomment l'adaptateur et le statut, jamais une
adresse, un jeton ni un corps de réponse — l'adresse d'un flux peut être son secret —, et une erreur de transport n'est
pas chaînée, car son message peut contenir l'adresse.
