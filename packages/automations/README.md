# phil/automations

**Automations a site's editors build without code: when an event happens, run these steps, wait, check, branch, and
undo what was done if a later step fails.**

phil/automations runs automations defined as data. A definition names a trigger — a subscription confirmed, an order
paid — and a list of steps: actions, delays, filters and branches. Packages register the triggers and the actions they
offer, each describing its options, so that a no-code builder can list them and the validator can check a definition
before it is published. When the site dispatches an event, every enabled definition whose trigger matches starts one
run, once per occurrence of the event. Runs go on through Symfony Messenger: a delay sends a delayed message, and a
schedule of Symfony Scheduler picks up whatever is due, so that a delay of three weeks survives deployments and
restarts. A step that fails in a way worth retrying waits and tries again; past its retries, the steps already done
are undone, in reverse order, by the actions that know how.

It is for any PHP application whose staff want to react to what happens on the site without asking a developer: a
welcome sequence of emails, a follow-up after an order, a webhook to another service when a member joins, a tag added
when a form is answered. Email sequences are one use of it: the newsletter's package offers the actions that send a
message or change a subscription, and no trigger ever fires on the opening of an email.

It is a plain PHP library on Symfony components — Messenger, Scheduler, ExpressionLanguage, the HTTP client — with
`phil/standard-webhooks` for the webhook action and `phil/url` to read addresses. Runs carry identifiers only, never
personal data: what is heavy or personal is read by the action from its own store, by its identifier. Several workers
may handle the same runs: a run is created once per event key, and a worker saves a run only over the revision it
read, so one worker at a time holds it. Definitions and runs live in memory or in two tables of a Doctrine DBAL
connection on PostgreSQL, MySQL, MariaDB or SQLite.

## Status

| Part | State |
|---|---|
| Definitions as data: trigger, action, delay, filter and branch steps, read from and written to JSON, bounded | Implemented and tested |
| The catalogue of triggers, actions and condition languages, with option descriptions for a builder, and the validator | Implemented and tested |
| Conditions in Symfony's ExpressionLanguage, without functions, over the run's identifiers | Implemented and tested |
| The engine: runs started once per event, leases, retries with backoff, compensation in reverse order | Implemented and tested |
| Messenger messages and handlers, the Scheduler schedule that resumes due runs | Implemented and tested |
| The webhook action, signed as Standard Webhooks, guarded against private networks | Implemented and tested |
| In-memory and Doctrine DBAL stores, tested on MySQL 8.4 and PostgreSQL 17 | Implemented and tested |
| A fuzz target for the definition reader | Implemented |
| A builder's pages and the wiring of a Symfony bundle | Not part of this library |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, declaring the services in Symfony. |
| [Definitions](doc/definitions.md) | The JSON of a definition, each kind of step, conditions, limits, versions, validation. |
| [Triggers and actions](doc/components.md) | Writing a trigger and an action, options, compensation, the webhook action. |
| [Runs](doc/runs.md) | How a run goes: events, messages, leases, delays, retries, compensation, states. |
| [Storage](doc/storage.md) | The stores, the database tables, the purge. |
| [Email sequences](doc/email-sequences.md) | An email sequence as an automation, and what it never does. |
| [Extension points](doc/extension-points.md) | The ports an application implements or replaces. |
| [Testing](doc/testing.md) | The library's tests, and how to test an automation. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/automations
```

```php
use Phil\Automations\Catalogue\Catalogue;
use Phil\Automations\Definition\DefinitionReader;
use Phil\Automations\Definition\DefinitionValidator;
use Phil\Automations\Definition\InMemoryDefinitions;
use Phil\Automations\Engine\AutomationEngine;
use Phil\Automations\Expression\ExpressionConditions;
use Phil\Automations\Run\InMemoryRunStore;

$catalogue = new Catalogue([$orderPaidTrigger], [$webhookAction, $sendAction], [new ExpressionConditions()]);
$definition = new DefinitionReader()->read($jsonFromTheBuilder);
new DefinitionValidator($catalogue)->validate($definition);

$definitions = new InMemoryDefinitions();
$definitions->publish($definition);

$engine = new AutomationEngine($catalogue, $definitions, new InMemoryRunStore(), $messageBus, $clock);
$engine->handle(new OrderPaid($order));   // starts the runs, sends an AdvanceRun for each
```

A worker handles `AdvanceRun` with `AdvanceRunHandler`, and the schedule `automations` sends `ResumeDueRuns` every
minute.

## Requirements

PHP 8.5 or later, and Symfony 8.2 components. Doctrine DBAL 4.5 or later for the database stores.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
