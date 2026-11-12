<!-- phil-dev source-sha256=812d16214a86763748f13e29d3bb38bdf45399645001ee474ba57a99182dfad4 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `CalendarSync` | Lit les plages occupées d'une connexion dans le dépôt ; écrit et retire des événements, par l'adaptateur de la connexion |
| `BusyPeriod` | Une plage où quelqu'un est pris : un début et une fin en UTC, provisoire ou non |
| `BusyPeriods` | Des plages occupées coupées à une fenêtre, triées et jointes ; `MAX` au plus |
| `SyncWindow` | La période lue, en UTC, de `MAX_DAYS` jours au plus |
| `CalendarConnection` | Un calendrier lié : son identifiant, son adaptateur, son calendrier et son fuseau |
| `Credentials` | Un jeton porteur, un nom d'utilisateur et un mot de passe, ou rien ; jamais sérialisés |
| `OutboundEvent` | Un événement que le site écrit dans un calendrier |
| `Contract\BusySource` | Un adaptateur qui lit des plages occupées |
| `Contract\CalendarDestination` | Un adaptateur qui écrit et retire des événements |
| `Contract\ConnectionRepository` | Les connexions de l'application |
| `Contract\CredentialStore` | Les identifiants de connexion de l'application, pour un seul appel |
| `Contract\BusyStore` | Où l'application garde les plages occupées lues |
| `Contract\OutboundEvents` | Les événements de l'application, pour le *handler* de `SyncEvent` |
| `Adapter\IcsFeed` | L'adaptateur `ics` : un flux iCalendar publié, en lecture seule |
| `Adapter\CalDav` | L'adaptateur `caldav` : une collection CalDAV |
| `Adapter\GoogleCalendar` | L'adaptateur `google` : l'API Google Calendar v3 |
| `Adapter\MicrosoftGraph` | L'adaptateur `microsoft` : Microsoft Graph v1.0 |
| `ICalendar\ICalendarReader` | Les plages occupées d'un texte iCalendar dans une fenêtre |
| `ICalendar\ICalendarWriter` | Un événement sous la forme de l'objet iCalendar qu'un serveur CalDAV stocke |
| `Message\RefreshBusyTimes` | Demande les plages occupées d'une connexion, par identifiant et fenêtre |
| `Message\RefreshBusyTimesHandler` | Traite `RefreshBusyTimes` |
| `Message\SyncEvent` | Demande qu'un événement soit écrit ou retiré, par identifiants |
| `Message\SyncEventHandler` | Traite `SyncEvent` |
| `Exception\CalendarSyncException` | Ce que le paquet lève |
| `Exception\InvalidSync` | Une valeur refusée |
| `Exception\InvalidCalendarData` | Une réponse ou un calendrier illisible, ou trop grand |
| `Exception\ProviderFailed` | Un service qui a refusé, échoué ou n'a pas répondu ; son adaptateur et son statut |
| `Exception\UnknownConnection` | Une connexion, ou un adaptateur, qui n'existe pas |

`Adapter\HttpCall`, `Adapter\Json`, `ICalendar\Components`, `ICalendar\EventTimes` et `ICalendar\Periods` sont
internes.
