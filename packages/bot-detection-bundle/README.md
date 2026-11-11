# phil/bot-detection-bundle

**Tell a robot from a human in the browser, and let only that one word out.**

phil/bot-detection-bundle produces a single output: a robot or human verdict. In the browser, a Stimulus
controller runs automation detectors locally (among them WebDriver, headless Chrome, automation globals and stack
frames, a software renderer) and lets only `human` or `robot` out. On the server, `Verdict` reads what the browser
submits, strictly. A `FrictionPolicy` chosen per surface maps it to friction, and robot verdicts go to an aggregate
counter that takes no argument.

It is for Symfony applications that want to slow automated visitors without hurting people. No identifier and no
browser fingerprint is computed, sent or kept, the verdict itself is never stored, and no third party is ever
called. A verdict never refuses a request: friction means one more step. Telling good crawlers from bad ones is
left to a server-side crawler list.

Other packages build on the verdict. `phil/analytics-bundle` reads it to keep robots out of its counters, and
`phil/spam-protection-bundle` gives its inspection of a form as a verdict.

The bundle is a skeleton of the verdict, the friction policy and `VerdictHandler`, with one port for what the
application decides: where `RobotVerdictCounter` keeps its count. `PhilBotDetectionBundle` declares no service and
no configuration.

## Status

| Area | State |
|---|---|
| `Domain\Verdict\Verdict` | Implemented and tested |
| `Domain\Friction\Friction`, `Domain\Friction\FrictionPolicy` | Implemented and tested |
| `Application\VerdictHandler` | Implemented and tested |
| `Domain\Counter\RobotVerdictCounter` | Port, implemented by the application |
| Browser detectors: the Stimulus controller `verdict` | Implemented, tested with Node; no third-party code included |
| `PhilBotDetectionBundle` | No service, no configuration |

## Documentation

- [Installation](doc/installation.md) — requirements, installing from a repository until the bundle is on
  Packagist, and registering it (which changes nothing yet).
- [Configuration](doc/configuration.md) — the bundle has no configuration key and declares no service; how to wire
  one policy, counter and handler per surface, and the decisions the application owns.
- [The verdict](doc/verdict.md) — the `human` and `robot` wire values, strict parsing that never throws, and why a
  verdict is neither an identifier nor stored.
- [Friction](doc/friction.md) — one more step and never a refusal, the two policies for a missing verdict, and
  choosing between them per surface.
- [Handling a verdict](doc/verdict-handling.md) — `VerdictHandler`, the caller's obligations, and why the counter
  counts robot verdicts rather than friction steps.
- [Browser signals](doc/browser-signals.md) — the in-browser detectors of the `verdict` Stimulus controller, the
  signals still to write, what is never collected or sent,
  and the open-source projects read as design references (motifs only).
- [Extension points](doc/extension-points.md) — the `RobotVerdictCounter` port, its invariants and example
  adapters.
- [Testing](doc/testing.md) — running the tests from the monorepo or with the bundle installed alone, and testing
  your counter and surfaces.
- [API reference](doc/reference.md) — every public type by layer, with its role and state.

## Quick start

```bash
composer require phil/bot-detection-bundle:^0.1@dev   # from a path or VCS repository until published
```

```php
use Phil\BotDetectionBundle\Application\VerdictHandler;
use Phil\BotDetectionBundle\Domain\Friction\FrictionPolicy;
use Phil\BotDetectionBundle\Domain\Verdict\Verdict;

$handler = new VerdictHandler(FrictionPolicy::robotOrUnknown(), $signUpRobotVerdictCounter);

$friction = $handler->handle(Verdict::fromSubmitted($request->request->get('verdict')));

if ($friction->adds()) {
    // one more step on this surface: a back-off, a stronger challenge...
}
// then drop the verdict: never log it, never store it
```

## Requirements

- PHP 8.5 or later
- `symfony/dependency-injection` and `symfony/config` 8.2

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
