<!-- phil-dev source-sha256=50ef83bb6054719e60df1dd261641306bc9e6768840b450d1d62b987a802f2f7 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Ajouter un préréglage, remplacer ou écarter un préréglage fourni | `Presets::withDefaults($presets, $without)`, ou `new Presets($presets)` |
| Suspendre quelque chose qui est propre à l'application | Nommez une restriction dans un préréglage et vérifiez-la avec `isSuspended()` |
| Épargner les membres établis de certaines restrictions | Implémentez `Contract\EstablishedMembers` et donnez-la à `IncidentRestrictions` avec les noms épargnés |
| Garder les activations ailleurs | Implémentez `Contract\ActivationStore` ; ses méthodes `lift()` et `extend()` ne changent une ligne que tant qu'elle n'est pas levée |
| Lever à la fin d'une autre façon | Implémentez `Contract\LiftScheduler` |
| Réagir à un incident | Écoutez `Event\IncidentStarted`, `Event\IncidentExtended` et `Event\IncidentLifted` |
| Choisir le moment | Donnez à `IncidentMode` n'importe quelle `Psr\Clock\ClockInterface` |
