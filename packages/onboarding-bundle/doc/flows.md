# Flows and steps

## Declaring

A flow is declared in the configuration — see [Configuration](configuration.md) — or with attributes on a class that
the container autoconfigures:

```php
use Phil\OnboardingBundle\Domain\Attribute\AsOnboardingFlow;
use Phil\OnboardingBundle\Domain\Attribute\AsOnboardingStep;
use Phil\OnboardingBundle\Domain\Flow\Completion;

#[AsOnboardingFlow('author', 'onboarding.author.title')]
#[AsOnboardingStep('author', 'profile', 'onboarding.author.profile', route: 'app_profile', target: '#profile-form')]
#[AsOnboardingStep('author', 'first-page', 'onboarding.author.first_page', Completion::Event, event: PageWritten::class)]
final class AuthorOnboarding
{
}
```

A step may join a flow declared elsewhere — by the configuration, by the application or by another package —; a flow
may not be declared twice. The container stops on a step whose flow is not declared.

## Order

The steps of a flow come in the order of their declaration — the configuration's first, then the attributes' —,
unless they ask to come `before` or `after` others: the steps a step waits for are then placed right before it, in
their order of declaration. A step that is not declared is passed over, so a package can place its step next to one of
a package that may not be installed; a circle stops the container.

## Completion

| Completion | Done when | Who may tick it |
|---|---|---|
| `manual` | the person ticks it in the checklist, or moves past it in the tour; or the application calls `complete()` | the person |
| `event` | the event the step names is dispatched; or the application calls `complete()` | nobody |
| `checker` | the `StepChecker` the step names answers yes when the progress is read; or the application calls `complete()` | nobody |
| `question` | the person answers its question in the checklist or the tour; or the application calls `complete()` | the person, by answering |

**An event** completes its step for the user it names when it implements `NamesOnboardingUser`, for the user of the
current request otherwise; nobody's event completes nothing. The listener passes over a progress that cannot be saved,
so the application's own action never fails because of the onboarding.

```php
final readonly class PageWritten implements NamesOnboardingUser
{
    public function __construct(public string $author) {}

    public function onboardingUser(): ?string
    {
        return $this->author;
    }
}
```

**A checker** reads the application's own data. It is asked each time the progress of a user is read, for each of its
steps not completed yet, unless the flow is dismissed: it must answer quickly — an indexed query, a cached count. A
class implementing `StepChecker` that declares its own step with `Completion::Checker` and no `checker` checks it:

```php
#[AsOnboardingStep('author', 'avatar', 'onboarding.author.avatar', Completion::Checker)]
final readonly class AvatarChecker implements StepChecker
{
    public function __construct(private Members $members) {}

    public function isComplete(string $user, string $flow, string $step): bool
    {
        return $this->members->hasAvatar($user);
    }
}
```

**A question** asks the person something whose answer the application acts on — their interests, their level, the
part of the site they came for. Its title asks it; it offers two to twenty choices, of which the person picks one, or
as many as they like with `multiple`. Each choice may name roles and subscriptions, as the application names them:

```yaml
phil_onboarding:
    answer_grants: App\Onboarding\TopicGrants
    flows:
        member:
            title: onboarding.member.title
            steps:
                topics:
                    title: onboarding.member.topics
                    completion: question
                    question:
                        multiple: true
                        choices:
                            news: { label: topic.news, subscriptions: [newsletter.news] }
                            events: { label: topic.events, roles: [ROLE_EVENTS], subscriptions: [newsletter.events] }
                            quiet: { label: topic.quiet }
```

The bundle never writes a role or a subscription. On an answer, it hands them to the application's `AnswerGrants` —
see [Extension points](extension-points.md#answergrants) — and records the step done once the grant returns, so a grant
that fails leaves the question to answer again. The container stops when a choice names a role or a subscription and
no `answer_grants` is named. A choice, a role and a subscription are made of letters, digits, `_`, `.`, `:` and `-`;
a choice grants at most twenty of each.

A question is answered once: an answered or skipped step is refused with `StepRefused` until the flow is started
again, and answering it then hands the same grants once more, which a grant takes in its stride. Taking a role back is
the application's own business.

## Reading and changing the progress

`Onboarding` is the entry point. Each method takes the user identifier, the one `CurrentUser` gives.

```php
$progress = $onboarding->progress('0192f3c4-member-1842', 'author');   // FlowProgress

$progress->done();       // the steps completed or skipped
$progress->total();
$progress->percent();    // rounded down, 0 to 100
$progress->current();    // the first pending StepProgress, or null
$progress->isFinished(); // no step pending
$progress->isActive();   // neither finished nor dismissed

foreach ($progress->steps as $step) {
    $step->step;         // the StepDefinition
    $step->state;        // StepState::Pending, Completed or Skipped
    $step->at;           // when it was completed or skipped
}
```

| Method | |
|---|---|
| `progress($user, $flow)` | Where the user stands; asks the checkers and records what they say is done. |
| `complete($user, $flow, $step)` | Records a step completed, whatever completes it, for the application's code. |
| `markDone($user, $flow, $step)` | Records a step ticked by the person; refuses a step done by an event, a checker or an answer with `StepRefused`. |
| `answer($user, $flow, $step, $choices)` | Hands the grants of the choices picked to `AnswerGrants`, then records the step done; refuses a step without a question, or no longer pending, with `StepRefused`, and choices the question cannot take with `InvalidAnswer`. |
| `skip($user, $flow, $step)` | Records a pending step skipped; refuses a step that cannot be skipped with `StepRefused`. A completed step stays completed. |
| `dismiss($user, $flow)` | Puts the flow away: its checklist and its tour no longer show. |
| `reset($user, $flow)` | Starts the flow again, every step pending; the checkers are asked again on the next reading. |
| `forget($user)` | Removes every progress of the user, to erase their account. |

With `phil/privacy-bundle` installed, `OnboardingPrivacy` joins the exports and the erasures of a person, the data
subject being the user identifier: the export lists where they stand in each flow, step by step, and the erasure calls
`forget()`.

An unknown flow or step throws `UnknownFlow`; a user identifier empty or longer than 320 bytes, `InvalidProgress`; an
answer that picks nothing, a choice the question does not offer, the same choice twice or several where one is asked,
`InvalidAnswer`;
a progress another request kept first, `ProgressNotSaved`. Every exception implements `OnboardingException`.

A flow is finished when no step is pending: a step added to a flow a user finished shows again in their checklist.
`FlowFinished` is dispatched once, the first time every step is done since the flow was started.
