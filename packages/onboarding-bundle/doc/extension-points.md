# Extension points

## `CurrentUser`

The port of who the current request is for, in `Phil\OnboardingBundle\Application\Contract`: the identifier their
progress is kept under, or null for a visitor. `SecurityCurrentUser` reads Symfony Security's user identifier; another
sign-in names its own service under `phil_onboarding.current_user`.

```php
interface CurrentUser
{
    public function id(): ?string;
}
```

## `StepChecker`

Says whether a user has done a step, from the application's own data. A step names its service under `checker`, or a
checker declares its own step with `AsOnboardingStep`; the container stops when the service does not implement the
port. See [Flows and steps](flows.md#completion).

```php
interface StepChecker
{
    public function isComplete(string $user, string $flow, string $step): bool;
}
```

## `NamesOnboardingUser`

Implemented by an event of the application that completes the steps of someone other than the user of the current
request — an event dispatched by a worker, or about someone else.

```php
interface NamesOnboardingUser
{
    public function onboardingUser(): ?string;
}
```

## `AnswerGrants`

What the application grants a user who answered a question: the roles and the subscriptions of the choices they picked,
in an `Answer` — the user, the flow, the step, the choices, the roles and the subscriptions, each once. The bundle never
writes a role or a subscription itself; it calls the port before it records the step done, so an exception leaves the
question to answer again. The same answer may come twice, when two requests race or the flow is started again: a grant
is idempotent. `NoAnswerGrants` grants nothing, for questions whose choices grant nothing; the container stops when a
choice grants something and `phil_onboarding.answer_grants` names no service.

```php
interface AnswerGrants
{
    public function grant(Answer $answer): void;
}

final readonly class TopicGrants implements AnswerGrants
{
    public function __construct(private Members $members, private Newsletters $newsletters) {}

    public function grant(Answer $answer): void
    {
        $this->members->addRoles($answer->user, $answer->roles);
        $this->newsletters->subscribe($answer->user, $answer->subscriptions);
    }
}
```

## `TourPreference`

Whether a user wants the guided tours. `AlwaysTours` says yes to everyone; with `phil/settings-bundle`,
`SettingsTourPreference` reads the user's setting `onboarding.tours`. An application names its own under
`phil_onboarding.tour_preference`.

```php
interface TourPreference
{
    public function wantsTours(string $user): bool;
}
```

## The declarations

`AsOnboardingFlow` and `AsOnboardingStep` declare flows and steps from any package or the application; a package may
also prepend the configuration of `phil_onboarding` in its own bundle:

```php
public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
{
    $builder->prependExtensionConfig('phil_onboarding', ['flows' => ['gallery' => [
        'title' => 'onboarding.gallery.title',
        'translation_domain' => 'gallery',
        'steps' => ['first-album' => ['title' => 'onboarding.gallery.first_album']],
    ]]]);
}
```

## The store

`ProgressRepository`, in `Phil\OnboardingBundle\Domain\Repository`, is the port of where the progress lives; the
bundle ships Doctrine and in-memory stores. Another store keeps one progress per user and flow, and throws
`ProgressNotSaved` when another request kept the same progress first.

## The templates and the events

The templates are overridden as any bundle's; the events of [Events](events.md) are the way to act after a change
without changing the bundle.

The buttons are the application's own copy of the recipe `Button`, installed by the Symfony UX Toolkit: changing it
changes them, and `kit_subdirectory` says where it is.

Every service of the bundle keeps no state between requests. A `CurrentUser`, a `StepChecker`, an `AnswerGrants` or a
`TourPreference` of the application keeps none either, or resets it with `kernel.reset`.
