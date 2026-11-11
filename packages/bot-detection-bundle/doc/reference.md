# API reference

Every public type of the bundle, by layer, with its role and state. Namespaces are relative to
`Phil\BotDetectionBundle`.

## Bundle

| Type | Role | State |
|---|---|---|
| `PhilBotDetectionBundle` | The bundle class; extension alias `phil_bot_detection` | No service, no configuration ([configuration.md](configuration.md)) |

## Domain

| Type | Role | State |
|---|---|---|
| `Domain\Verdict\Verdict` | String-backed enum `Human` (`human`), `Robot` (`robot`), `Unknown` (`unknown`); `fromSubmitted(?string): self`, strict and never throwing; `isRobot()` | Implemented and tested ([verdict.md](verdict.md)) |
| `Domain\Friction\Friction` | Pure enum `None`, `Step`, with no refusal case; `adds()` | Implemented and tested ([friction.md](friction.md)) |
| `Domain\Friction\FrictionPolicy` | `robotOnly()`, `robotOrUnknown()`, `frictionFor(Verdict): Friction`; public `unknownAddsFriction` | Implemented and tested ([friction.md](friction.md)) |
| `Domain\Counter\RobotVerdictCounter` | Interface: `increment(): void`, without argument | Port only ([extension-points.md](extension-points.md)) |

## Application

| Type | Role | State |
|---|---|---|
| `Application\VerdictHandler` | `__construct(FrictionPolicy, RobotVerdictCounter)`, `handle(Verdict): Friction`: counts a robot verdict and returns the friction | Implemented and tested ([verdict-handling.md](verdict-handling.md)) |

## Browser

| File | Role | State |
|---|---|---|
| `assets/src/verdict_controller.js` | The `verdict` Stimulus controller: runs the detectors and leaves `human` or `robot` on the root element and in its field | Implemented, tested with Node |
| `assets/src/detectors.js` | The detectors and their combination into one verdict | Implemented, tested with Node; no third-party code included |

## Not yet in the skeleton

| Piece | State |
|---|---|
| The signals of the design not written yet (rewritten native functions, the window against a worker, permissions, speech voices, WebRTC, hardware and screen consistency) | Not written ([browser-signals.md](browser-signals.md)) |
| Services, configuration, a Twig helper for the controller | Not written |
