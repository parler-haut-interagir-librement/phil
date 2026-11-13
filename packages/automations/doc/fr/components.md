<!-- phil-dev source-sha256=b9281da590f52a2c80fe2f5ff8dd6061cfcf5d9e12b1e6fd013ccdf4f995b1c8 -->
# Déclencheurs et actions

Un paquet qui distribue des événements offre des déclencheurs ; un paquet qui peut faire quelque chose offre des
actions. Les deux se décrivent avec un `ComponentDescription` : un type à points, une étiquette pour le constructeur,
les options qu'ils prennent et, pour une action, si elle peut défaire ce qu'elle a fait.

## Un déclencheur

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

La clé d'événement de la correspondance nomme cette occurrence de l'événement : le même événement livré deux fois donne
la même clé, et le moteur démarre une seule exécution. Son sujet est la personne ou l'enregistrement dont parle
l'exécution. Sa charge utile ne contient que des identifiants — une clé est un nom en minuscules, une valeur un entier,
un booléen, `null` ou une chaîne d'au plus 255 octets sans caractères de contrôle, 32 entrées au plus. Un nom, une
adresse ou un message n'est jamais une charge utile : l'action le lit à partir de l'identifiant.

## Une action

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

| `ActionContext` | Contient |
|---|---|
| `runId`, `automationId`, `stepId` | L'endroit où l'action s'exécute |
| `options` | Les options de l'étape |
| `payload`, `subject` | Ce que le déclencheur a donné à l'exécution |
| `outputs` | Ce que chaque étape d'action précédente a rendu, par `id` d'étape |
| `idempotencyKey` | La même pour chaque tentative de cette étape de cette exécution |
| `attempt` | 0, puis 1, 2… pour les nouvelles tentatives |

Une action peut s'exécuter plus d'une fois pour une même étape : un *worker* qui s'arrête entre l'action et la
sauvegarde de son résultat, une nouvelle tentative. Transmettez `idempotencyKey` au service appelé, ou vérifiez-la, pour
que la seconde exécution ne change rien.

Une action qui ne peut pas faire son travail lève `ActionFailed::retryable()` quand réessayer plus tard peut réussir —
un service hors service, une limite de débit — ou `ActionFailed::permanent()` quand cela ne réussira jamais. Toute autre
exception compte comme une exception à réessayer.

Une action qui implémente `CompensableAction` défait, dans `compensate()`, ce qu'`execute()` a fait, quand une étape
qui la suit échoue pour de bon. Elle reçoit le résultat qu'`execute()` a retourné : retournez assez d'identifiants pour
défaire exactement ce qui a été fait — ici, si l'étiquette a été ajoutée par cette exécution ou si elle y était déjà.
Une compensation peut aussi s'exécuter deux fois.

## Les options

| `OptionType` | Une valeur valide |
|---|---|
| `Text` | Une chaîne |
| `Integer` | Un entier |
| `Boolean` | `true` ou `false` |
| `Identifier` | Un nom d'enregistrements de l'application : lettres, chiffres, `_ . : / -`, 255 caractères au plus |
| `Url` | Une adresse `https`, lue comme un navigateur la lit |
| `Choice` | Un des `choices` de l'option |
| `Identifiers` | Une liste d'identifiants |

Une option obligatoire doit être donnée et ne pas être `null` ; une option que la description ne liste pas est refusée.

## Le catalogue

`Catalogue` contient les déclencheurs, les actions et les évaluateurs de condition, chacun par son type ou son langage ;
deux du même type sont refusés. Il trouve les déclencheurs qui lisent un événement, et liste les descriptions pour un
constructeur : `describeTriggers()`, `describeActions()`, `languages()`.

## L'action de webhook

`WebhookAction`, de type `webhook`, envoie un `POST` JSON signé comme l'exige Standard Webhooks :

| Option | Type | Ce que c'est |
|---|---|---|
| `url` | `Url`, obligatoire | L'adresse `https` du récepteur |
| `event` | `Identifier`, obligatoire | Le `type` du corps |
| `key` | `Identifier`, obligatoire | Le nom des clés de signature, données par le `WebhookKeys` de l'application |

```json
{"type": "member.welcomed", "timestamp": "2026-11-13T10:00:00+00:00",
 "data": {"automation": "welcome", "run": "…", "step": "notify", "subject": "42", "payload": {"order": "7"}, "outputs": {}}}
```

Chaque tentative d'une même étape d'une même exécution envoie le même `webhook-id`, `msg_` suivi de 32 chiffres
hexadécimaux tirés de la clé d'idempotence, de sorte que la déduplication du récepteur absorbe une nouvelle tentative.
L'adresse est lue avec `phil/url` : ni nom d'utilisateur, ni adresse IP, ni hôte d'un seul libellé, `https`
seulement. Le client HTTP est enveloppé dans le `NoPrivateNetworkHttpClient` de Symfony, qui refuse un hôte qui se
résout vers une adresse privée, de bouclage ou réservée ; `allowPrivateNetworks: true` retire la protection, pour un
récepteur du réseau interne. Aucune redirection n'est suivie, et la requête attend dix secondes par défaut. Une réponse
`2xx` réussit, avec le statut et le `webhook-id` comme résultat ; `408`, `425`, `429`, `5xx` et l'absence de réponse
valent une nouvelle tentative ; toute autre réponse échoue pour de bon.
