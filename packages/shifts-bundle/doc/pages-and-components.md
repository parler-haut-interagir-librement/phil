# Pages and components

Every page is a plain Twig form that works without JavaScript, keyboard first, with the classes of the PHIL UX kit —
`isStack`, `isBadge`, `isWarning`, `isError`, `isSecondary`, `isDanger`. Each button posts a CSRF token; a form from
elsewhere is refused with a 403. The reason of a refusal is written in a `role="alert"` paragraph, which a screen
reader announces, and the page answers 422, 404 for a shift or an offer unknown. The instants are shown in the
configured time zone, with a `<time datetime>` in UTC.

## The volunteers' pages

| Route | Path | Shows |
|---|---|---|
| `phil_shifts_board` | `GET /` | The shifts to come, critical ones lacking volunteers first, and where the volunteer stands. |
| `phil_shifts_show` | `GET /shift/{id}` | A shift, its places and reserve, and the button its mode gives: sign up, join the reserve, say one is interested, withdraw, offer one's place. |
| `phil_shifts_mine` | `GET /mine` | The volunteer's shifts and the hours they served, plain and weighted. |
| `phil_shifts_swaps` | `GET /swaps` | The places offered on shifts still open, with the button to take one, or to cancel one's own. |

The buttons post to `phil_shifts_sign_up`, `phil_shifts_interest`, `phil_shifts_withdraw`, `phil_shifts_swap_offer`,
`phil_shifts_swap_take` and `phil_shifts_swap_cancel`, and redirect with a 303 to the page they came from. A shift
started — or the window of a task or a duration over — shows no button.

A sign-up, an interest or a swap that would give a volunteer a fixed shift overlapping one they hold, closer than
`rest_minutes`, is refused with `shift.conflict`. A place in the reserve holds no shift: it does not conflict.

## The coordinators' pages

| Route | Path | Shows |
|---|---|---|
| `phil_shifts_manage` | `GET /manage` | Every shift to come, those lacking volunteers flagged, and the swaps waiting for an approval. |
| `phil_shifts_manage_create` | `GET, POST /manage/new` | The form of a new shift. |
| `phil_shifts_manage_edit` | `GET, POST /manage/{id}/edit` | The form of a shift, its identifier fixed; a capacity below its volunteers is refused. |
| `phil_shifts_manage_roster` | `GET /manage/{id}` | The places, reserve and interested, with the buttons to assign, take off, allocate and delete; once the shift started, to record an unannounced absence or take it back; for a volunteer blocked by their absences, to lift the block. |

The buttons post to `phil_shifts_manage_assign` — a volunteer by identifier, or one of the reserve or of the
interested —, `phil_shifts_manage_remove`, `phil_shifts_manage_allocate`, `phil_shifts_manage_delete`,
`phil_shifts_manage_absent`, `phil_shifts_manage_forgive`, `phil_shifts_manage_lift`, `phil_shifts_manage_approve` and
`phil_shifts_manage_refuse`. A volunteer recorded absent carries the badge "Absent without notice", one past the
threshold "Blocked by absences": see [Minors and absences](minors-and-absences.md).

The allocation dispatches `AllocateInterest` on the application's Messenger bus when there is one: handled at once,
unless the application routes it to a transport, where a worker handles it. Without Messenger, it runs during the
request.

## The form of a shift

`ShiftType` holds the fields of a shift: identifier, title, kind, start and end in the configured time zone, minutes
for a task or a duration, places, reserve, mode, required role, critical, weight, swap approval, location and
description. `ShiftType::shift()` builds the shift from a submitted form, or adds the library's refusal to the form —
its message is a key of the domain `phil_shifts`, translated in English and French. An application uses it in its own
pages:

```php
use Phil\ShiftsBundle\Infrastructure\Form\ShiftType;

$form = $this->createForm(ShiftType::class, ShiftType::data($roster->shift), ['existing' => true]);
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid() && null !== $shift = ShiftType::shift($form, $roster->shift->id)) {
    $rosters->correct($shift);
}
```

## The components

With Symfony UX Twig Component installed, two components show the shifts on any page:

```twig
{# A shift with the button of the person signed in; nothing for a shift unknown. #}
<twig:PhilShifts:ShiftCard id="desk-sat-am" />
<twig:PhilShifts:ShiftCard :roster="roster" :actions="false" class="isCard" />

{# The shifts to come of the person signed in, and the hours they served; nothing when nobody is signed in. #}
<twig:PhilShifts:MyShifts limit="5" />
```

| Component | Props | Renders |
|---|---|---|
| `PhilShifts:ShiftCard` | `id`, or `roster`; `actions`, `true` by default | an `<article class="phil-shifts-card">` with the title, the window, the places left and the button |
| `PhilShifts:MyShifts` | `limit`, 1 to 50, 5 by default | a `<section class="phil-shifts-mine">` with the shifts not over yet and the hours served |

Other attributes go on the root element. The buttons of a card post to the volunteers' routes, so the routes must be
imported.

## The translations

The pages translate their texts in the domain `phil_shifts`, in English and in French; an application overrides a key
in its own `translations/phil_shifts.<locale>.*`. The reasons of the refusals are keys of the same domain:
`shift.full`, `shift.conflict`, `swap.stale`, `shifts.busy` and the others listed in the reference of phil/shifts.
