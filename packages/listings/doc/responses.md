# Responses

A `Response` is one member's answer to a listing. It carries no message: sending it is the respondent's consent to be
put in touch with the author, accepting it is the author's, and only an accepted response — `permitsContact()` —
lets the application open a conversation between them, in its own messaging. Until then, neither learns anything
about the other beyond what the listing and the member's public profile show, and the exact place of the listing
stays hidden ([Listings](listings.md#titles-and-places)).

| Status | Open | Permits contact | Leaves by |
|---|---|---|---|
| `pending` | yes | no | `accept()`, `decline()`, `withdraw()` |
| `accepted` | yes | yes | `withdraw()` |
| `declined` | no | no | nothing |
| `withdrawn` | no | no | nothing |

A response that is open counts against its listing's open responses. The respondent may withdraw it, pending or
accepted: their consent ends with it, and the application closes the conversation it opened.

## The responder

`Responder` holds the rules that span a listing and its responses, over the kind policies, the
[ports](extension-points.md) and a clock.

`refusal($listing, $respondent)` answers why the member may not respond, or null. The rules are asked from the
cheapest to the costliest, and the first that refuses answers:

| Refusal | When |
|---|---|
| `not_open` | The listing is not visible: not published, or past its expiry. |
| `responses_closed` | Its kind takes no responses. |
| `own_listing` | The member wrote it. |
| `blocked` | The author or the member blocked the other. |
| `age_unknown` | The listing has a minimum age and the member's age is not known. |
| `too_young` | The member is younger than the listing's minimum age. |
| `already_responded` | The member has an open response to it. |
| `full` | It has as many open responses as its kind allows. |

The age is asked only of a listing with a minimum age. An application shows the refusal before a member tries — a
disabled button with its reason — and `respond()` asks it again.

- `respond($listing, $id, $respondent)` — throws `ResponseRefused`, with its `refusal`, or saves and returns a
  pending response.
- `accept($listing, $response)` — the author accepts a pending response to their listing; refused with
  `other_listing` when the response is not one of this listing's, with `not_open` when the listing is no longer
  visible — hidden, removed, closed, back in review, rejected or past its expiry —, with `blocked` when one of them
  blocked the other since the response, and with `age_unknown` or `too_young` when the respondent no longer meets
  the listing's minimum age, an edit or the kind having raised it since. A refused response stays pending; the
  application declines the ones it no longer wants.
- `decline($listing, $response)` — the author declines a pending response to their listing.
- `withdraw($response)` — the respondent takes their response back.

A response that is not pending cannot be accepted nor declined, and one that is not open cannot be withdrawn:
`ForbiddenTransition`. As for listings, who may call which is the application's authorization: the author decides,
the respondent withdraws.

## Without the responder

`Response::pending()`, `accept()`, `decline()`, `withdraw()` and `restore()` are pure, like the listing's
transitions; an application that checks its own rules may use them directly. The responder is where the rules of
consent, blocks and age are kept together, and the place to call them from.
