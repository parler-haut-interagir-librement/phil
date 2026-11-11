# Listings

A `Listing` is immutable. Each transition takes the `KindPolicy` of the listing's kind — another kind's policy throws
`InvalidListing` — and, when time matters, an instant; it returns a new listing, or throws `ForbiddenTransition` when
the listing's status does not allow it. No transition reads a clock or stores anything: the application loads a
listing, calls a transition and saves the result.

Who may call a transition is the application's authorization: the library does not know who is the author, a
moderator or the scheduler. `report()` alone takes the reporting member, to count them and to refuse the author.

## What a listing holds

| Field | Type | |
|---|---|---|
| `id` | `ListingId` | Chosen by the application. |
| `kind` | `ListingKind` | Whose policy applies. |
| `author` | `MemberId` | Who wrote it. |
| `title` | `Title` | Valid UTF-8, white space folded, no control or format character, 1 to 120 characters. |
| `criteria` | `Criteria` | See [Criteria](criteria.md). |
| `location` | `Location` | Online, or an area with an optional exact place. |
| `minimumAge` | `?int` | The older of the listing's and the kind's; null for none. |
| `status` | `ListingStatus` | See below. |
| `createdAt`, `publishedAt`, `expiresAt`, `remindedAt` | instants | `publishedAt` is the first publication. |
| `extensions` | `int` | How many times it was extended. |
| `reporters` | `list<MemberId>` | The distinct members who reported it since its last publication or restoration. |
| `moderationReason` | `?ModerationReason` | Why it was rejected or removed. |

The body of a listing — its description, its pictures, a price — is the application's: a classified ad, a job offer
and a search for partners do not describe themselves alike. The library holds what every kind shares and what its
rules need.

## Titles and places

`Title::of()` refuses input of more than 2048 bytes or invalid UTF-8 before any pattern runs, folds every run of white
space — no-break spaces and line separators included — into one space, trims, then refuses any control or format
character left, such as a zero-width space or a soft hyphen, and anything empty or longer than 120 characters.

`Location::online()` is a listing without a place. `Location::area($area, $exact)` has an area shown to everyone — a
city, a neighbourhood — of up to 120 characters, and an optional exact place of up to 255, folded the same way.
`approximate()` drops the exact place, and `Listing::locationFor($viewer, $acceptedRespondent)` gives the whole
location to the author and to a respondent the author accepted, the area alone to anyone else.

## The minimum age

`Listing::draft()` and `edit()` take the listing's minimum age, from 0 to 120 or null; the listing keeps the older of
it and the kind's. A kind open to members from 13 lets a listing ask for 18; a listing can never go below its kind.
`admitsAge($age)` admits every age when there is no minimum, and never an unknown one when there is.

## Statuses

| Status | Visible | Leaves by |
|---|---|---|
| `draft` | no | `submit()`, `edit()`, `close()` |
| `pending_review` | no | `approve()`, `reject()`, `remove()`, `edit()`, `close()` |
| `published` | until `expiresAt` | `report()`, `edit()`, `extend()`, `close()`, `remove()`, `at()` |
| `rejected` | no | `edit()`, `submit()`, `close()` |
| `hidden` | no | `approve()` (restores it), `remove()`, `close()` |
| `expired` | no | `extend()`, `edit()`, `remove()`, `close()` |
| `closed` | no | nothing |
| `removed` | no | nothing |

A listing is visible — `isVisibleAt($at)` — while it is published and `$at` is before its expiry. The expiry itself
needs no transition: a published listing past it is invisible already. `at($at)` turns it into an expired one, for a
store that keeps its statuses current or a scheduler that sweeps them.

## Transitions

- `Listing::draft($id, $policy, $author, $title, $criteria, $location, $minimumAge, $at)` — a new draft.
- `submit($policy, $at)` — a draft or a rejected listing goes to `pending_review` when the kind is moderated before
  publication, to `published` otherwise.
- `approve($policy, $at)` — a pending listing is published; a hidden one is restored and its reports cleared. A
  listing published before keeps its expiry while it lies ahead; otherwise it gets a fresh one from `$at`, and its
  reminder is due again.
- `reject($policy, $reason)` — a pending listing is rejected, with its reason.
- `remove($policy, $reason)` — a pending, published, hidden or expired listing is removed for good.
- `report($policy, $reporter, $at)` — see [Moderation and expiry](moderation-and-expiry.md).
- `edit($policy, $title, $criteria, $location, $minimumAge)` — the author rewrites a draft, a pending, rejected,
  published or expired listing. A published listing of a kind moderated before publication goes back to
  `pending_review`, keeping its expiry; any other keeps its status.
- `extend($policy, $at)` and `markReminded($at)` — see [Moderation and expiry](moderation-and-expiry.md).
- `close($policy)` — the author closes any listing that is not closed or removed already.

## Storing a listing

`Listing::restore()` rebuilds a stored listing from its fields; it refuses a negative extension count and more
reporters than any policy could count. Every field is a plain value or a value object with a `value`, so a table,
a document or a JSON column holds it as it is.
