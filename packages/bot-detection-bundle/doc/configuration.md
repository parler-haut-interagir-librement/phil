# Configuration

## The bundle has no configuration yet

`PhilBotDetectionBundle` extends Symfony's `AbstractBundle`, sets its extension alias to `phil_bot_detection`,
and overrides neither `configure()` nor `loadExtension()`:

| Aspect | Today |
|---|---|
| Configuration alias | `phil_bot_detection` |
| Configuration keys | None. The configuration tree is empty. |
| Services declared | None. Loading the extension leaves the container's service ids unchanged (the bundle's own test asserts it). |
| Parameters, tags, compiler passes | None. |
| Assets (browser script) | The `verdict` Stimulus controller, declared in `assets/package.json` and found by StimulusBundle ([browser-signals.md](browser-signals.md)); no Twig helper or asset of its own. |

The only accepted forms are no key at all, or an empty one:

```yaml
# config/packages/phil_bot_detection.yaml — optional; the tree is empty
phil_bot_detection: ~
```

### Build-time errors

Any key under `phil_bot_detection` fails the container build
(`Symfony\Component\Config\Definition\Exception\InvalidConfigurationException`):

```text
Unrecognized option "foo" under "phil_bot_detection". Available options are "".
```

## What the application wires itself

The application registers one `VerdictHandler` per surface that receives a verdict, each with its friction
policy and its own counter. `FrictionPolicy` is built by a named constructor, so it is created with a factory:

```yaml
# config/services.yaml (application)
services:
    app.bot.policy.robot_only:
        class: Phil\BotDetectionBundle\Domain\Friction\FrictionPolicy
        factory: ['Phil\BotDetectionBundle\Domain\Friction\FrictionPolicy', 'robotOnly']

    app.bot.policy.robot_or_unknown:
        class: Phil\BotDetectionBundle\Domain\Friction\FrictionPolicy
        factory: ['Phil\BotDetectionBundle\Domain\Friction\FrictionPolicy', 'robotOrUnknown']

    # One counter per surface: increment() takes no argument, so the split is in the wiring
    app.bot.counter.sign_up:
        class: App\Bot\CacheRobotVerdictCounter
        arguments: ['bot.robot_verdicts.sign_up']
    app.bot.counter.payment:
        class: App\Bot\CacheRobotVerdictCounter
        arguments: ['bot.robot_verdicts.payment']

    app.bot.handler.sign_up:
        class: Phil\BotDetectionBundle\Application\VerdictHandler
        arguments: ['@app.bot.policy.robot_or_unknown', '@app.bot.counter.sign_up']
    app.bot.handler.payment:
        class: Phil\BotDetectionBundle\Application\VerdictHandler
        arguments: ['@app.bot.policy.robot_only', '@app.bot.counter.payment']
```

### Decisions the application owns

| Decision | Where it goes |
|---|---|
| Whether a missing or malformed verdict adds friction, surface by surface | `FrictionPolicy::robotOnly()` or `FrictionPolicy::robotOrUnknown()` ([friction.md](friction.md)) |
| What one step of friction means on each surface, and the limiter that absorbs it | Application code that reads the returned `Friction` |
| The counter adapter per surface, its time window, where the count is reported | `RobotVerdictCounter` adapters ([extension-points.md](extension-points.md)) |
| The name of the request field that carries the verdict | Application code that calls `Verdict::fromSubmitted()` |
| Placing the `verdict` controller on the pages and forms of each surface, serving it from the application's own origin and listing it in each page's script inventory | Application ([browser-signals.md](browser-signals.md#placing-the-controller)) |
| Disclosure in the privacy policy, and the legal position on in-browser signals in the jurisdictions served | Application |
