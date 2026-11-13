# Incidents

## Restrictions

A `Restriction` is one thing an incident suspends, by a name: a lowercase letter, then up to 63 lowercase letters,
digits and hyphens. The library names the common ones; the application adds its own by using a name.

| Constant | Name | What the application stops |
|---|---|---|
| `Restriction::SIGNUPS` | `signups` | New accounts |
| `Restriction::INVITATIONS` | `invitations` | Invitations sent by members |
| `Restriction::NEW_MESSAGES` | `new-messages` | Private messages and new conversations |
| `Restriction::NEW_POSTS` | `new-posts` | New topics, articles, listings |
| `Restriction::NEW_COMMENTS` | `new-comments` | Comments and replies |
| `Restriction::REACTIONS` | `reactions` | Reactions and votes |
| `Restriction::UPLOADS` | `uploads` | Uploaded files |
| `Restriction::PROFILE_CHANGES` | `profile-changes` | Changes to a profile, an avatar, a display name |
| `Restriction::READ_ONLY` | `read-only` | Every restriction, the application's own included |

The library decides nothing on its own: the application checks a restriction where it applies, and decides what a
suspended action shows.

## Presets

A `Preset` is a name, a label, the restrictions it suspends, the duration an activation proposes and the longest it
allows. **A preset has no activation without an end**: both durations are required, the proposed one within the
longest, and a `Duration` is from one minute to ninety days.

```php
use Phil\IncidentMode\Duration;
use Phil\IncidentMode\Preset;

$quiet = Preset::of('quiet-listings', 'Suspend new listings', ['new-listings'], Duration::ofHours(4), Duration::ofDays(2));
```

`Presets` is the catalogue, in the order it offers them:

| Name | Restrictions | Proposed | Longest |
|---|---|---|---|
| `suspend-signups` | `signups` | 2 hours | 7 days |
| `suspend-invitations` | `invitations` | 2 hours | 7 days |
| `suspend-new-messages` | `new-messages` | 1 hour | 3 days |
| `suspend-contributions` | `signups`, `invitations`, `new-messages`, `new-posts`, `new-comments`, `uploads` | 1 hour | 3 days |
| `read-only` | `read-only` | 30 minutes | 2 days |

`Presets::defaults()` holds these five. `Presets::withDefaults($presets, $without)` adds the application's presets
after them; a preset of the same name replaces the built-in one in its place, and the names in `$without` are left
out. `new Presets($presets)` holds only the presets given.

The label is English; an interface translates it by the name of the preset.

## Starting

```php
$activation = $incidents->start('suspend-signups', Duration::ofHours(2), Actor::of($memberId), 'Spam wave');
```

The duration is required and the preset bounds it: a longer one is refused with `InvalidIncident`, an unknown preset
with `UnknownPreset`. The reason is optional, up to 500 characters. The returned `Activation` copies the preset's
restrictions, so that changing a preset later leaves the incidents in force as they were started.

An `Actor` is the stable identifier the application gives its members — a UUID, an OIDC `sub`, a member number —, up
to 190 visible characters. **An identifier that looks like an e-mail address is refused**: an address changes and
names the person to whoever reads the journal. `null` stands for the system.

## Checking

| Method | Answer |
|---|---|
| `isSuspended('signups')` | Whether an incident in force names the restriction, or is read only |
| `suspended()` | The names of the restrictions suspended now, each once, in the order the incidents started |
| `inForceNow()` | The activations in force now, the earliest start first |

An incident is in force from its start until its end or its lifting, whichever comes first. Each answer is one
indexed question to the store at the moment the clock gives; nothing is kept between two calls.

## Extending and lifting

`extend($id, $additional, $actor)` moves the end of an incident in force; the incident as a whole stays within the
longest duration of its preset, counted from its start, and an incident whose preset left the catalogue is not
extended. `lift($id, $actor)` ends an incident in force now. Both throw `IncidentNotActive` when the incident is
unknown, lifted or past its end.

## The journal

`journal($limit, $offset)` gives every activation, the latest start first: preset, restrictions, start, end, who
started it and why; once over, when, by whom and whether it was lifted by hand (`LiftReason::Manual`) or expired
(`LiftReason::Expired`, without an actor). `find($id)` gives one.

## Events

When an event dispatcher is given, `IncidentMode` sends:

| Event | When | Holds |
|---|---|---|
| `Event\IncidentStarted` | An incident was started | The activation |
| `Event\IncidentExtended` | An incident in force was given a later end | The activation, the previous end, the actor |
| `Event\IncidentLifted` | An incident was lifted by hand, or its expiry recorded | The lifted activation |

The events are the extension points: a listener notifies the team, posts a status, clears a cache. Each is sent
once, even when two processes lift the same incident at once.
