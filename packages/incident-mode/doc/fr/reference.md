<!-- phil-dev source-sha256=62c36263a17d9314d66b120b95e7322de323a0b54f0d806630af9c25d343f2a7 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `IncidentMode` | Déclenche, prolonge et lève les incidents ; répond à ce qui est suspendu maintenant ; donne le journal |
| `Restriction` | Une chose qu'un incident suspend, par son nom ; les constantes des plus courantes |
| `Duration` | La durée d'un incident, d'une minute à quatre-vingt-dix jours |
| `Preset` | Un nom, un libellé, des restrictions, une durée proposée et une durée maximale |
| `Presets` | Le catalogue des préréglages : ceux qui sont fournis et ceux de l'application |
| `Actor` | Qui a déclenché, prolongé ou levé un incident, par un identifiant stable, jamais une adresse |
| `Activation` | Un incident, en vigueur ou terminé |
| `LiftReason` | `Manual` ou `Expired` |
| `Contract\ActivationStore` | Où les activations sont conservées |
| `IncidentRestrictions` | Si une restriction s'applique maintenant à un membre, les membres établis épargnés selon la configuration |
| `Contract\EstablishedMembers` | Qui est un membre établi, par l'identifiant de l'application |
| `NoEstablishedMembers` | Ne tient personne pour établi |
| `Contract\LiftScheduler` | Prévoit la levée automatique d'une activation à sa fin |
| `NoLiftScheduler` | Ne prévoit rien ; un balayage lève les incidents échus |
| `InMemoryActivationStore` | Les activations d'un seul processus |
| `Dbal\DbalActivationStore` | Les activations dans une table d'une connexion DBAL |
| `Dbal\IncidentTables` | La table et sa définition, ajoutées à un schéma |
| `Dbal\IncidentSchemaListener` | Ajoute la table au schéma que l'ORM génère |
| `Messenger\MessengerLiftScheduler` | Envoie `LiftIncident` différé jusqu'à la fin |
| `Messenger\LiftIncident`, `Messenger\LiftIncidentHandler` | Le message différé, par identifiant, et son handler |
| `Messenger\LiftExpiredIncidents`, `Messenger\LiftExpiredIncidentsHandler` | Le balayage récurrent et son handler |
| `Event\IncidentStarted`, `Event\IncidentExtended`, `Event\IncidentLifted` | Les événements d'un incident |
| `Exception\IncidentModeException` | Chaque exception de la bibliothèque |
| `Exception\InvalidIncident` | Une valeur refusée |
| `Exception\UnknownPreset` | Aucun préréglage de ce nom |
| `Exception\IncidentNotActive` | L'incident n'est pas en vigueur |
