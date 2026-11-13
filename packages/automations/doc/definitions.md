# Definitions

A definition is one version of an automation: its id, its version, a name, the trigger it starts on and its steps. A
no-code builder saves it as JSON; `DefinitionReader` reads that JSON, and `DefinitionWriter` writes it back, always to
the same bytes for one definition.

```json
{
    "id": "welcome",
    "version": 1,
    "name": "Welcome",
    "trigger": {"type": "newsletter.confirmed", "options": {"list": "news"}},
    "steps": [
        {"id": "hello", "type": "action", "action": "newsletter.send", "options": {"template": "hello"}, "retries": 3},
        {"id": "wait", "type": "delay", "seconds": 86400},
        {"id": "still-there", "type": "filter", "condition": {"language": "expression", "source": "subject != null"}},
        {"id": "plan", "type": "branch",
         "condition": {"language": "expression", "source": "payload['plan'] == 'pro'"},
         "then": [{"id": "pro-tips", "type": "action", "action": "newsletter.send", "options": {"template": "pro"}}],
         "else": [{"id": "upgrade", "type": "action", "action": "newsletter.send", "options": {"template": "upgrade"}}]}
    ]
}
```

```php
use Phil\Automations\Definition\DefinitionReader;
use Phil\Automations\Definition\DefinitionWriter;

$definition = new DefinitionReader()->read($json);
$json = new DefinitionWriter()->write($definition);
```

A definition can also be built in code, with `new AutomationDefinition(...)` and the named constructors of `Step`.

## The steps

| Kind | Keys | What it does |
|---|---|---|
| `action` | `action`, `options`, `retries` | Runs the action of that type with its options; tried again up to `retries` times (0 to 10) when it fails in a way worth retrying |
| `delay` | `seconds` | Waits that long, from 1 second to 366 days, then goes on |
| `filter` | `condition` | Ends the run, without failure, unless the condition holds |
| `branch` | `condition`, `then`, `else` | Follows `then` when the condition holds and `else` otherwise, then the steps after the branch; either path may be empty |

Each step has an `id`, a lowercase name unique in the definition. An action's result is kept under its step's id, for
the conditions and the actions after it.

## Conditions

A condition has a `language` — `expression` when it is left out — and a `source`. The language `expression` is Symfony's
ExpressionLanguage without a single function: no `constant()`, no `enum()`. It sees three variables:

| Variable | Holds |
|---|---|
| `payload` | The identifiers the trigger gave the run: `payload['plan']` |
| `outputs` | What each action step before it gave back, by step id: `outputs['subscribe']['created']` |
| `subject` | The id of the person or record the run is about, or `null` |

A condition gives `true` or `false`. Read a key that may be missing with `??`: `payload['coupon'] ?? null`. A condition
that gives another value, or fails while it is evaluated, fails the run, and the steps done are compensated. Another
language — the rules of an application, say — comes from a `ConditionEvaluator` the catalogue is given.

## Limits

| Limit | Value |
|---|---|
| JSON | 262 144 bytes, 64 levels |
| Steps, branches included | 200 |
| Branch nesting | 15 levels |
| Options of a trigger or an action | 32, strings of 2 048 bytes, lists of 64 integers or strings |
| A condition | 2 048 bytes |
| A name | 120 characters |

The reader refuses anything else with `InvalidDefinition` and never throws another exception: an unknown key, a value
of the wrong type, a missing key. A secret is never an option: an option names it, and a port of the application gives
it, as the webhook action's `key` does.

## Versions

A run keeps the version it started with. To change an automation, publish a new version: the runs already started go
on with their own steps, the new ones start with the new steps. `Definitions::publish()` makes a version the published
one, enabled or not; `disable()` stops an automation from starting runs, and the runs under way go on.

## Validation

`DefinitionValidator` checks a definition against the catalogue before it is published: its trigger and every action
registered, their options given as their descriptions say, every condition in a known language and readable.

```php
use Phil\Automations\Definition\DefinitionValidator;

$problems = new DefinitionValidator($catalogue)->problems($definition);   // list of sentences, none when valid
new DefinitionValidator($catalogue)->validate($definition);               // throws InvalidDefinition with all of them
```
