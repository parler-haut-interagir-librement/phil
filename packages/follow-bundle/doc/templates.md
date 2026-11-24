# Templates

The templates work without JavaScript: each form posts to a signed address of the bundle's routes with the stateless
CSRF token `phil_follow`, makes its change for the current member, and comes back to the page with a `303`. A visitor
sees none of the forms. The markup is plain HTML — `details`, `button`, `fieldset` — that the PHIL UX styles dress.

| Template | Context | Shows |
|---|---|---|
| `@PhilFollow/follow_button.html.twig` | `subject`, `type:id` | The follow button, the menu of levels, and a level for each kind of event of `events` |
| `@PhilFollow/requests.html.twig` | — | The requests the current member has to answer, each accepted or rejected |
| `@PhilFollow/followers.html.twig` | `subject`, `limit` | The followers of a subject and their count, or nothing when they are hidden from the viewer |
| `@PhilFollow/visibility.html.twig` | — | The current member's choice to show or hide their followers |
| `@PhilFollow/mutual.html.twig` | `member` | The mutual relationship with another member: ask, wait, end |
| `@PhilFollow/stopped.html.twig` | `message`, `back` | What stopped a change, with the way back |

```twig
{{ include('@PhilFollow/follow_button.html.twig', {subject: 'thread:' ~ thread.id}) }}
{{ include('@PhilFollow/followers.html.twig', {subject: 'member:' ~ member.id}) }}
```

An application dresses a template by overriding it under `templates/bundles/PhilFollowBundle/`.

## The Twig functions

| Function | Returns, for the current member |
|---|---|
| `phil_follow_button(subject)` | `subject`, `level`, `inherited`, `pending`, `levels`, `events`, `action`, `token`; null for a visitor |
| `phil_follow_requests(limit = 50)` | `requests` — each `id`, `kind`, `requester`, `name`, `subject`, `requested_at` —, `action`, `token`; null for a visitor |
| `phil_follow_followers(subject, limit = 50)` | `hidden`, `count`, `followers` — each `id` and `name` |
| `phil_follow_visibility()` | `hidden`, `action`, `token`; null for a visitor |
| `phil_follow_mutual(member)` | `mutual`, `pending`, `action`, `token`; null for a visitor, for oneself, or when relationships are off |

The names come from the `RecipientDirectory` of phil/notifications-bundle, else the identifier.

## The forms

| Route | Fields | Change |
|---|---|---|
| `phil_follow_level` | `level` — `watching`, `mentions`, `ignoring`, `custom` or `none` —, `events[<event>]` | Follow, change the level, or stop following |
| `phil_follow_request` | `request`, `decision` — `accept`, `reject` or `withdraw` | Answer or withdraw a request |
| `phil_follow_visibility` | `hidden`, `1` or `0` | Hide or show one's followers |
| `phil_follow_mutual` | `action`, `request` or `end` | Ask for, or end, a mutual relationship |

| Status | When |
|---|---|
| `303` | The change is made; back to the page |
| `403` | The address is not signed, the token does not pass, there is no member, or the change is refused |
| `404` | The request does not wait for the member |
| `422` | A value of the form is unknown |

## The Flex recipe

The bundle ships no recipe yet: the routes and the configuration are written by hand, as [Installation](installation.md)
shows.
