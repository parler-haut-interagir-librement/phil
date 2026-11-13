# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Add a preset, replace or leave out a built-in one | `Presets::withDefaults($presets, $without)`, or `new Presets($presets)` |
| Suspend something of the application's | Name a restriction in a preset and check it with `isSuspended()` |
| Keep the activations elsewhere | Implement `Contract\ActivationStore`; its `lift()` and `extend()` change a row only while it is not lifted |
| Lift at the end another way | Implement `Contract\LiftScheduler` |
| React to an incident | Listen to `Event\IncidentStarted`, `Event\IncidentExtended` and `Event\IncidentLifted` |
| Choose the moment | Give `IncidentMode` any `Psr\Clock\ClockInterface` |
