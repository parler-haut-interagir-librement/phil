<!-- phil-dev source-sha256=fee1f9671cbcbd34c8893cbd46e3f5ddf88ecd722c34c561ec1edc3fb997d6c9 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Ajouter un préréglage, remplacer ou écarter un préréglage fourni | `Presets::withDefaults($presets, $without)`, ou `new Presets($presets)` |
| Suspendre quelque chose qui est propre à l'application | Nommez une restriction dans un préréglage et vérifiez-la avec `isSuspended()` |
| Garder les activations ailleurs | Implémentez `Contract\ActivationStore` ; ses méthodes `lift()` et `extend()` ne changent une ligne que tant qu'elle n'est pas levée |
| Lever à la fin d'une autre façon | Implémentez `Contract\LiftScheduler` |
| Réagir à un incident | Écoutez `Event\IncidentStarted`, `Event\IncidentExtended` et `Event\IncidentLifted` |
| Choisir le moment | Donnez à `IncidentMode` n'importe quelle `Psr\Clock\ClockInterface` |
