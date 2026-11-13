# Triggers and actions

A package that dispatches events offers triggers; a package that can do something offers actions. Both describe
themselves with a `ComponentDescription`: a dotted type, a label for the builder, the options they take and, for an
action, whether it can undo what it did.

## A trigger

```php
use Phil\Automations\Attribute\AsAutomationTrigger;
use Phil\Automations\Catalogue\{ComponentDescription, OptionSpec, OptionType};
use Phil\Automations\Contract\Trigger;
use Phil\Automations\Definition\Options;
use Phil\Automations\Engine\{Payload, TriggerMatch};

#[AsAutomationTrigger]
final readonly class OrderPaidTrigger implements Trigger
{
    public function describe(): ComponentDescription
    {
        return new ComponentDescription('shop.order_paid', 'Order paid', [
            new OptionSpec('plan', OptionType::Choice, label: 'Plan', choices: ['basic', 'pro']),
        ]);
    }

    public function events(): array
    {
        return [OrderPaid::class];
    }

    public function match(object $event, Options $options): ?TriggerMatch
    {
        if (!$event instanceof OrderPaid || ($options->string('plan') ?? $event->plan) !== $event->plan) {
            return null;
        }

        return new TriggerMatch('order-paid:'.$event->orderId, $event->customerId, Payload::of(['order' => $event->orderId]));
    }
}
```

The match's event key names this occurrence of the event: the same event delivered twice gives the same key, and the
engine starts one run. Its subject is the person or record the run is about. Its payload holds identifiers only — a
key is a lowercase name, a value an integer, a boolean, `null` or a string of at most 255 bytes without control
characters, 32 entries at most. A name, an address or a message is never a payload: the action reads it by the
identifier.

## An action

```php
use Phil\Automations\Attribute\AsAutomationAction;
use Phil\Automations\Contract\CompensableAction;
use Phil\Automations\Engine\{ActionContext, Payload};
use Phil\Automations\Exception\ActionFailed;

#[AsAutomationAction]
final readonly class TagCustomer implements CompensableAction
{
    public function describe(): ComponentDescription
    {
        return new ComponentDescription('crm.tag', 'Tag the customer', [new OptionSpec('tag', OptionType::Identifier, true, 'Tag')], true);
    }

    public function execute(ActionContext $context): Payload
    {
        $added = $this->tags->add($context->subject ?? throw ActionFailed::permanent('No customer.'), $context->options->string('tag'));

        return Payload::of(['added' => $added]);
    }

    public function compensate(ActionContext $context, Payload $result): void
    {
        if (true === $result->get('added')) {
            $this->tags->remove($context->subject, $context->options->string('tag'));
        }
    }
}
```

| `ActionContext` | Holds |
|---|---|
| `runId`, `automationId`, `stepId` | Where the action runs |
| `options` | The step's options |
| `payload`, `subject` | What the trigger gave the run |
| `outputs` | What each action step before this one gave back, by step id |
| `idempotencyKey` | The same for every attempt of this step of this run |
| `attempt` | 0, then 1, 2… for the retries |

An action may run more than once for one step: a worker that stops between the action and the save of its result,
a retry. Pass `idempotencyKey` on to the service called, or check it, so that the second run changes nothing.

An action that cannot do its work throws `ActionFailed::retryable()` when trying again later may succeed — a service
down, a rate limit — or `ActionFailed::permanent()` when it never will. Any other exception counts as retryable.

An action that implements `CompensableAction` undoes, in `compensate()`, what `execute()` did, when a step after it
fails for good. It is given the result `execute()` returned: return enough identifiers to undo exactly what was done
— here, whether the tag was added by this run or was already there. A compensation may run twice too.

## Options

| `OptionType` | A valid value |
|---|---|
| `Text` | A string |
| `Integer` | An integer |
| `Boolean` | `true` or `false` |
| `Identifier` | A name of the application's records: letters, digits, `_ . : / -`, at most 255 characters |
| `Url` | An `https` address, read as a browser reads it |
| `Choice` | One of the option's `choices` |
| `Identifiers` | A list of identifiers |

A required option must be given and not `null`; an option the description does not list is refused.

## The catalogue

`Catalogue` holds the triggers, the actions and the condition evaluators, each by its type or language; two of one
type are refused. It finds the triggers that read an event, and lists the descriptions for a builder:
`describeTriggers()`, `describeActions()`, `languages()`.

## The webhook action

`WebhookAction`, of type `webhook`, sends a JSON `POST` signed as Standard Webhooks asks:

| Option | Type | What it is |
|---|---|---|
| `url` | `Url`, required | The receiver's `https` address |
| `event` | `Identifier`, required | The `type` of the body |
| `key` | `Identifier`, required | The name of the signing keys, given by the application's `WebhookKeys` |

```json
{"type": "member.welcomed", "timestamp": "2026-11-13T10:00:00+00:00",
 "data": {"automation": "welcome", "run": "…", "step": "notify", "subject": "42", "payload": {"order": "7"}, "outputs": {}}}
```

Every attempt of one step of one run sends the same `webhook-id`, `msg_` and 32 hexadecimal digits drawn from the
idempotency key, so the receiver's deduplication absorbs a retry. The address is read with `phil/url`: no user name, no
IP address, no single-label host, `https` only. The HTTP client is wrapped in Symfony's `NoPrivateNetworkHttpClient`,
which refuses a host that resolves to a private, loopback or reserved address; `allowPrivateNetworks: true` removes the
guard, for a receiver of the internal network. No redirect is followed, and the request waits ten seconds by default.
A `2xx` answer succeeds, with the status and the `webhook-id` as the result; `408`, `425`, `429`, `5xx` and no answer
are worth retrying; any other answer fails for good.
