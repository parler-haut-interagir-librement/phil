# Checkpoints

A checkpoint asks whether an incident suspends, for the member of the request, what they are about to do. An
established member is spared from the restrictions of `spare_established`; a visitor never is.

## A controller

```php
use Phil\IncidentModeBundle\Attribute\SuspendedDuring;

final class RegistrationController
{
    #[SuspendedDuring('signups', methods: ['POST'])]
    public function register(Request $request): Response
    {
        // …
    }
}
```

`#[SuspendedDuring]` takes a restriction or a list of them, and the HTTP methods it refuses — every method when none
is named. On a class, it covers every action; it may be repeated. Refusing only `POST` lets the page show its form
while the banner says why sending it would be refused; refusing every method shows the refusal page instead.

## A route

A route of another bundle carries no attribute of this one: its defaults name the restrictions instead.

```yaml
# config/routes.yaml
invitation_send:
    path: /invitations
    controller: Acme\InvitationBundle\Controller\InvitationController::send
    defaults:
        _suspended_during: [invitations]
```

## A code path

```php
use Phil\IncidentModeBundle\Application\Checkpoint;

final readonly class Invitations
{
    public function __construct(private Checkpoint $checkpoint)
    {
    }

    public function send(Invitation $invitation): void
    {
        $this->checkpoint->guard('invitations');
        // …
    }
}
```

| Method | Answer |
|---|---|
| `applies('signups')` | Whether the restriction applies to the member of the request now |
| `suspension('invitations', 'signups')` | The first of them that applies, as a `Suspension` with its end; `null` when none does |
| `suspensions()` | Every restriction that applies now, each once, in the order the incidents started |
| `guard('signups')` | Throws `SuspendedByIncident` while one of them applies |

The end of a suspension is the latest end of the incidents in force that suspend it. Every answer reads the incidents
in force at that moment; nothing is kept between requests. A visitor is answered from one read of them.

## A form

```php
$form = $this->createForm(RegistrationType::class, $registration, ['suspended_during' => 'signups']);
```

The option `suspended_during`, on every form, takes a restriction or a list of them. A submission while one of them
applies is invalid, with the error that says what is suspended and until when — the `Suspension` is its cause —, so
that nothing of it is saved.

## The refusal

`SuspendedByIncident` is an HTTP error: the status of `checkpoints.status`, 503 by default, and a `Retry-After` header
with the seconds to the end. The bundle renders it as `@PhilIncidentMode/suspended.html.twig`, in the visitor's
language, never kept by a shared cache. Without Twig, Symfony renders it as any HTTP error, with the same status and
header.

## What visitors read

The words say honestly what is suspended and until when, in the language of the request and in the time zone of
`timezone`, which they name: *Sign-ups are suspended until November 17, 2026 at 12:00 PM (Eastern Standard Time).*
`read-only` says the site can be read but nothing added or changed; a restriction of the application's own is named as
written.

| Where | What |
|---|---|
| `{{ include('@PhilIncidentMode/_banner.html.twig') }}` | A banner in the site's layout, nothing when no incident restricts the member |
| The route `phil_incident_mode_status` | The public page of what is suspended now, `200` |
| The route `phil_incident_mode_notice` | The same notice without the layout, for a page that loads it |
| `phil_incident_suspended('signups')` | Whether to show a form or the notice |
| `phil_incident_suspensions()` | The suspensions, for a notice of the application's own |
| `phil_incident_sentence(suspension)` | The sentence of one suspension |
| `phil_incident_moment(date)` | A moment in the visitor's language and the configured time zone |

Each restriction of the notice carries `data-restriction` and `data-until`, its end in UTC, for a page that wants to
write it in the browser's time zone.
