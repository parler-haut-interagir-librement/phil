<!-- phil-dev source-sha256=485dae1320af0b65af3a2ee2904c328a5b4ee21474a4a4b4717d100ec29e6994 -->
# Adaptateurs

Chaque adaptateur envoie une requête à la fois, sans mandataire — pas même celui de l'environnement, qui résoudrait
l'hôte au-delà de la garde des réseaux privés —, ne suit aucune redirection, borne le silence du service à 10 secondes et
la requête entière à 30, et lit 10 Mio de réponse au plus. Un `401` ou un `403` donne `ProviderFailed::unauthorized()`,
un autre statut d'erreur `ProviderFailed::status()`, l'absence de réponse `ProviderFailed::unreachable()`.

## `ics` : un flux publié

`IcsFeed` lit un calendrier publié comme flux iCalendar : l'adresse secrète que Google, Microsoft, Apple ou Proton
donnent pour un calendrier. Il ne fait que lire.

- **Calendrier** : l'adresse du flux ; `webcal://` et `webcals://` sont lues comme `https://`.
- **Identifiants de connexion** : aucun. L'adresse est souvent le seul secret du flux : gardez-la comme un identifiant
  de connexion.
- **Transport** : `phil/safe-http`, qui refuse un hôte sur un réseau privé, vérifie chaque redirection qu'il suit et
  s'arrête à 10 Mio ; un flux coupé là est refusé.

## `caldav` : une collection CalDAV

`CalDav` lit et écrit une collection de calendrier d'un serveur CalDAV (RFC 4791) : Nextcloud, Fastmail, iCloud,
Radicale.

- **Calendrier** : l'adresse `https` de la collection, sans requête ni fragment.
- **Identifiants de connexion** : un nom d'utilisateur et un mot de passe d'application, ou un jeton porteur.
- **Plages occupées** : le rapport `free-busy-query` (§7.10), qui répond avec des heures seulement. Un serveur qui
  répond `400`, `404`, `405`, `415`, `422` ou `501` à ce rapport reçoit une `calendar-query` pour les heures des
  événements — début, fin, durée, règle, dates, statut, transparence —, développées par le serveur ; le lecteur les
  développe de nouveau quand le serveur ne le fait pas.
- **Événements** : écrits par `PUT` à `<collection>/<SHA-256 de l'identifiant>.ics`, pour qu'aucun identifiant ne
  devienne un chemin, et supprimés à cette adresse ; un `404` ou un `410` en réponse à un retrait n'est pas une erreur.
- **Transport** : le `NoPrivateNetworkHttpClient` de Symfony autour du client donné, de sorte qu'un hôte qui est un
  réseau privé, ou qui s'y résout, est refusé. `resolve` fixe un hôte à une adresse, quand même vérifiée.
- **Multistatus** : lu avec `ext-dom`, sans accès au réseau ; un type de document est refusé.

## `google` : Google Calendar

`GoogleCalendar` lit et écrit un calendrier Google par l'API Google Calendar v3.

- **Calendrier** : l'identifiant du calendrier, `primary` ou une adresse comme `abc123@group.calendar.google.com`.
- **Identifiants de connexion** : un jeton d'accès OAuth, avec la portée `calendar.freebusy` pour lire et
  `calendar.events` pour écrire.
- **Plages occupées** : `freeBusy`, qui répond avec des heures seulement. Une erreur du calendrier dans la réponse
  devient `ProviderFailed::refused()` avec la raison de Google, comme `notFound`.
- **Événements** : gardés sous un identifiant dérivé de celui de l'événement, son SHA-256 en hexadécimal, que l'alphabet
  de Google admet. Une écriture remplace l'événement, et l'insère quand Google répond `404`. Une heure dans un fuseau
  nommé est écrite dans ce fuseau ; une heure avec seulement un décalage, en UTC.

## `microsoft` : Microsoft Graph

`MicrosoftGraph` lit et écrit un calendrier Outlook d'un compte Microsoft 365 ou Outlook.com, par Microsoft Graph v1.0.

- **Calendrier** : l'identifiant Graph d'un calendrier de l'utilisateur connecté, ou `primary` pour son calendrier par
  défaut.
- **Identifiants de connexion** : un jeton d'accès OAuth, avec `Calendars.ReadBasic` pour lire et `Calendars.ReadWrite`
  pour écrire.
- **Plages occupées** : la vue du calendrier, qui ne demande que le début, la fin, la disponibilité et l'annulation de
  chaque événement, en UTC. Les événements libres, ceux « en télétravail ailleurs » et ceux annulés sont écartés, les
  provisoires gardés comme provisoires. 20 pages de 500 événements au plus ; une page suivante n'est suivie que sur
  `graph.microsoft.com`.
- **Événements** : retrouvés par une propriété étendue qui contient l'identifiant de l'événement, mis à jour quand ils
  sont trouvés, créés sinon, supprimés quand ils sont trouvés. Les heures sont écrites en UTC, les journées entières de
  minuit à minuit.
