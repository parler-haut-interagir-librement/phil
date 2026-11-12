<!-- phil-dev source-sha256=8f9fc5856898b460b38c4326c83dbd2ec418e7062446c518eddc76daae3b1305 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG dit comment.

## Les ports que l'application implémente

| Port | Ce qu'il donne |
|---|---|
| `Contract\ConnectionRepository` | Les connexions, par identifiant |
| `Contract\CredentialStore` | Les identifiants de connexion d'une connexion, rafraîchis, pour un seul appel |
| `Contract\BusyStore` | Où sont gardées les plages occupées lues, remplacées fenêtre par fenêtre |
| `Contract\OutboundEvents` | Les événements que le *handler* de `SyncEvent` écrit, tels qu'ils sont à son exécution |

## Ses propres adaptateurs

Une application lit un autre service en implémentant `Contract\BusySource`, et y écrit en implémentant
`Contract\CalendarDestination`, sous un nom bien à elle — un mot en minuscules de 32 caractères au plus —, puis en
passant l'adaptateur à `CalendarSync` parmi ses sources ou ses destinations. Une connexion nomme l'adaptateur qu'elle
utilise.

Un adaptateur lit des plages occupées seulement, les donne dans n'importe quel ordre, et lève `ProviderFailed` ou
`InvalidCalendarData` : `CalendarSync` les coupe, les trie, les joint et les borne.

## Transports

| Pour | Faites |
|---|---|
| Lire par un client à portée limitée ou des doublures de test | Passez un client à l'adaptateur ; `CalDav` l'enveloppe quand même dans `NoPrivateNetworkHttpClient`, et aucune requête ne passe par un mandataire |
| Fixer un hôte CalDAV à une adresse | Passez `resolve` à `CalDav` : l'adresse est quand même vérifiée |
| Lire des flux avec d'autres limites | Passez à `IcsFeed` un `SafeHttpClient` avec d'autres `Limits` |
| Dater les événements que CalDAV stocke | Passez une horloge à `CalDav` |

## Ce qui reste fermé

Les objets-valeurs, les adaptateurs et `CalendarSync` sont finaux. Le lecteur iCalendar ne lit que des heures : une
application qui a besoin des titres d'un autre calendrier le lit avec son propre code, pas avec celui-ci.
