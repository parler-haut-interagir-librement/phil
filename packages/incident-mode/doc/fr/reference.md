<!-- phil-dev source-sha256=0cd92c01a182c079273a1bb4ee1c6d58b2b7fdc1e88e00bf5a9dc471c614a631 -->
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
