# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `IncidentMode` | Starts, extends and lifts incidents; answers what is suspended now; gives the journal |
| `Restriction` | One thing an incident suspends, by name; the constants of the common ones |
| `Duration` | How long an incident lasts, from one minute to ninety days |
| `Preset` | A name, a label, restrictions, a proposed and a longest duration |
| `Presets` | The catalogue of presets: the built-in ones and the application's |
| `Actor` | Who started, extended or lifted an incident, by a stable identifier, never an address |
| `Activation` | One incident, in force or over |
| `LiftReason` | `Manual` or `Expired` |
| `Contract\ActivationStore` | Where the activations are kept |
| `Contract\LiftScheduler` | Arranges the automatic lifting of an activation at its end |
| `NoLiftScheduler` | Arranges nothing; a sweep lifts the expired incidents |
| `InMemoryActivationStore` | The activations of one process |
| `Dbal\DbalActivationStore` | The activations in a table of a DBAL connection |
| `Dbal\IncidentTables` | The table and its definition, added to a schema |
| `Dbal\IncidentSchemaListener` | Adds the table to the schema the ORM generates |
| `Messenger\MessengerLiftScheduler` | Sends `LiftIncident` delayed until the end |
| `Messenger\LiftIncident`, `Messenger\LiftIncidentHandler` | The delayed message, by identifier, and its handler |
| `Messenger\LiftExpiredIncidents`, `Messenger\LiftExpiredIncidentsHandler` | The recurring sweep and its handler |
| `Event\IncidentStarted`, `Event\IncidentExtended`, `Event\IncidentLifted` | The events of an incident |
| `Exception\IncidentModeException` | Every exception of the library |
| `Exception\InvalidIncident` | A value refused |
| `Exception\UnknownPreset` | No preset of that name |
| `Exception\IncidentNotActive` | The incident is not in force |
