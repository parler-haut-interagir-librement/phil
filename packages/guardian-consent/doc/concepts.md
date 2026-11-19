# Concepts

## Members and guardians

A member and a guardian are named by stable identifiers the application gives, such as the UUIDs of their accounts:
`MemberId` and `GuardianId`. An identifier outlives an address and is copied into every record, so an identifier
holding an `@` is refused, as are an empty one, one longer than 255 bytes, and one holding whitespace or a control
character.

A `Guardian` is the holder of parental authority, or the tutor, asked to consent: their identifier and the address the
confirmation is sent to. The address stays on the pending request only.

## Registering a member

`register($member, $birthDate, $jurisdiction)` reads the member's age from their date of birth, a `CalendarDate`,
against the rules of the jurisdiction — the default one when none is named — and returns an `AgeAssessment`: the
whole years completed, the `AgeBracket`, and the days the age of consent and majority are reached.

| Bracket | When | Who consents |
|---|---|---|
| `BelowConsentAge` | Below the age of consent | The guardian, feature by feature |
| `MinorAtConsentAge` | From the age of consent to majority | The member |
| `Adult` | From majority | The member |

A member below the age of consent is kept as a `Minor`: their jurisdiction and the day they reach the age of consent,
never their date of birth. A member already kept has that day corrected when they are registered again with another
date. A member at or above the age of consent is not kept: the library has nothing to say about them, and their
consents are the site's ordinary ones.

## Features

A `Feature` is a part of the site a guardian consents to on its own, named by the site: `messages`,
`public-profile`, `classifieds`. The list is open; the library knows none of them. A name is 1 to 64 lowercase
letters, digits, `.`, `_` or `-`, beginning with a letter or a digit.

## Statuses

`status($member, $feature)` says where a member's consent to a feature stands; `allows()` says whether the feature
may be used.

| Status | Means | Allows |
|---|---|---|
| `NotRequired` | The member is not kept as a minor | Yes |
| `Granted` | Granted by the guardian below the age of consent, by the member from it | Yes |
| `Pending` | A request covering the feature waits for the guardian, and has not expired | No |
| `Refused` | Nothing granted, or refused, or withdrawn | No |
| `HandoverRequired` | The member has reached the age of consent and has not confirmed their own choices | No |

Refusal is the default: a feature nobody decided on is refused.

## Requests

`request($member, $guardian, $features, $notice)` asks a guardian to consent to 1 to 64 features for a member below
the age of consent, on a version of the text, a `NoticeVersion` the site names. It keeps a `ConsentRequest`, hands
the request and its `ConfirmationToken` to the notifier, which sends the e-mail, and dispatches `ConsentRequested`.

A request may be answered for a configurable number of days, seven by default. A member has at most five requests
waiting at once, so that a guardian's mailbox cannot be flooded.

## The confirmation link

The notifier builds the link from the token's text. The link leads to a page that calls `open($token)` and shows the
request — the member, the features, the version of the text — with a form; following the link alone decides nothing,
so that a mail scanner that opens it changes nothing. The form posts to a controller that calls
`decide($token, $granted)`: the features granted, the others of the request refused.

`decide()` uses the token up, records one proof per feature of the request, and dispatches `ConsentDecided`. A token
that is malformed, unknown, used or forged throws `InvalidToken`, with the same message in every case; a token of an
expired request throws `ExpiredRequest`. A request answered after the member has reached the age of consent is used
up without a proof, and `ConsentNotRequired` is thrown.

## Withdrawal

`revoke($member, $feature, $guardian)` records a withdrawal: the feature is refused from then on, and
`ConsentRevoked` is dispatched. A guardian may withdraw when they decided on one of the member's consents before, or
when the guardian link names them as managing the member's account; anyone else gets `NotAGuardian`. The application
authenticates the guardian before it calls it.

A new request may be sent after a refusal or a withdrawal: the feature is `Pending` while it waits.

## Proofs

A `ConsentProof` records one decision on one feature: the member, the feature, the `Decision` (`Granted`, `Refused`,
`Revoked`), the `Actor` (`Guardian` or `Member`), the guardian's identifier, the version of the text, the request, and
the instant, in UTC. Proofs are only ever added: the latest proof of a feature, by the actor whose consent applies,
says where it stands.

## The handover

On the day a member reaches the age of consent, in their jurisdiction's time zone, the guardian's consents stop
applying: every feature is `HandoverRequired` until the member confirms their own choices.
`handOver($member, $granted, $notice)` records the member's own proofs for the features they grant, marks the record
as handed over, drops the requests still waiting for a guardian, and dispatches `HandoverCompleted`. Called again, it
records the changes: a feature granted before and left out is withdrawn.

Neither a request, nor an answer, nor a withdrawal by a guardian is accepted from that day.

## The sweep

`sweep($limit)` runs once a day, from a scheduler. It erases the requests left unanswered past their retention — 30
days after they expire, by default — dispatching `RequestErased` for each, and tells the members who reached the age
of consent: the notifier sends the notice, the record keeps when, and `HandoverDue` is dispatched. Each part handles
at most `$limit` records, 100 by default and 1000 at most; a larger backlog is handled by the next runs.

## Events

| Event | When | Carries |
|---|---|---|
| `ConsentRequested` | A request was sent | The request, the member |
| `ConsentDecided` | A guardian answered | The request, the member |
| `ConsentRevoked` | A guardian withdrew a consent | The member, the feature |
| `RequestErased` | An unanswered request was erased | The request, the member |
| `HandoverDue` | A member reached the age of consent and was told | The member |
| `HandoverCompleted` | A member confirmed their own choices | The member |

Events carry identifiers only, never an address or a date of birth: they can travel on a message queue as they are,
and a listener reads the rest from the stores.
