# Administration

The page is served by the routes of `@PhilIncidentModeBundle/config/routes/admin.php`, under the prefix the application
chooses:

| Route | Method and path | What it does |
|---|---|---|
| `phil_incident_mode_admin` | `GET /` | The page: the incidents in force, the presets, the journal — `?page=2` for the next page |
| `phil_incident_mode_admin_start` | `POST /start` | Starts an incident from `preset`, for `amount` of `unit` (`minutes`, `hours`, `days`), with an optional `reason` |
| `phil_incident_mode_admin_extend` | `POST /{id}/extend` | Gives the incident a later end, by `amount` of `unit` |
| `phil_incident_mode_admin_lift` | `POST /{id}/lift` | Lifts the incident now |

## The page

- **The incidents in force**: the label of the preset, what it suspends, its end in the configured time zone, its
  reason, how much longer its preset lets it last with the form that extends it, and the button that lifts it.
- **The presets**: one form each, filled with the proposed duration in its largest whole unit, with the longest the
  preset allows, a reason kept in the journal, and the button that starts the incident.
- **The journal**: every incident, the latest first — preset, start, end or lifting, how it ended, who started it, who
  lifted it, why —, a page of `admin.page_size` at a time.

A form the library would refuse is shown again with the status 422 and what went wrong: no preset of that name, a
duration out of bounds, a duration longer than the preset allows, a reason over 500 characters, an incident no longer
in force. The page is never kept by a cache.

## Who may run it

The member signed in holds the role of `admin.role`, `ROLE_ADMIN` by default; anyone else meets the firewall, as with
any denied page, and so does everyone when no authorization checker is registered. The actor of what they start,
extend or lift is the identifier `CurrentMember` gives: by default the identifier of Symfony Security's user. **An
identifier that looks like an e-mail address is refused**, since it names the person to whoever reads the journal and
changes; an application whose users sign in with their address names them through its own `CurrentMember`:

```php
namespace App\Security;

use Phil\IncidentModeBundle\Application\Contract\CurrentMember;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class MemberUuid implements CurrentMember
{
    public function __construct(private Security $security)
    {
    }

    public function identifier(): ?string
    {
        $user = $this->security->getUser();

        return $user instanceof \App\Entity\User ? $user->uuid() : null;
    }
}
```

```yaml
phil_incident_mode:
    current_member: App\Security\MemberUuid
```

## The forms

Every form carries the token `phil_incident_mode` in the field `_token`. The bundle adds the token to the stateless
tokens of FrameworkBundle, so that the page needs no session; a form posted from another site — its
`Sec-Fetch-Site` header says `cross-site` — or without a valid token is refused with the status 400.

## The look

The pages draw the recipes of the kit — `Alert`, `Badge`, `Button`, `Field`, `Input`, `NativeSelect`, `Table`,
`Textarea` —, which the PHIL UX kit and the Shadcn kit of the Symfony UX Toolkit both have, through the Twig function
`phil_incident_kit('Button')`, which prefixes the components subdirectory. They extend the template of `layout`, which
fills the blocks `title` and `main`; an application gives its own, or overrides the templates under
`templates/bundles/PhilIncidentModeBundle/`. No script is needed.

## The console

The same actions run from the console, the moments in UTC:

```sh
php bin/console phil:incident:start suspend-signups --duration=2h --actor=8d2e0f --reason="Spam wave"
php bin/console phil:incident:extend 3f2a… --by=1h --actor=8d2e0f
php bin/console phil:incident:lift 3f2a… --actor=8d2e0f
php bin/console phil:incident:lift --expired
php bin/console phil:incident:list
php bin/console phil:incident:list --journal --limit=20 --offset=20
php bin/console phil:incident:list --presets
```

The duration and the actor are required: an incident never lasts without an end, and the journal always says who
started it, by a stable identifier. A duration is written `90`, `45m`, `2h`, `3d` or `1d 12h`. A refusal of the library
— a preset, a duration longer than it allows, an address as the actor — ends the command with a failure and its reason.
