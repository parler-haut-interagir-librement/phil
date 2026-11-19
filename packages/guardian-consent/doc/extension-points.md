# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Keep minors, requests and proofs in a database | Implement `Contract\MinorStore`, `Contract\RequestStore` and `Contract\ProofStore` |
| Send the confirmation and the handover notice | Implement `Contract\Notifier` |
| Let an adult who manages an account withdraw a consent | Implement `Contract\GuardianLink`, for instance on a delegation of accounts |
| Answer access and erasure requests | Call `GuardianConsentRights`, which implements `Contract\SubjectRights`, from the privacy tooling |
| Serve other jurisdictions | Pass a `Jurisdictions` of your own: see [Jurisdictions](jurisdictions.md) |
| Act after a request, a decision, a withdrawal, an erasure or a handover | Listen to the events: see [Concepts](concepts.md#events) |

## The stores

`MinorStore::dueForHandover($date, $limit)` lists the minors who reach the age of consent on `$date` or before and were
not told yet, the earliest first; `RequestStore::expiredBy($instant, $limit)` lists the requests expired by then, the
earliest first. A store in a database serves them from an index on the day of the age of consent and on the expiry.

`RequestStore::consume($id)` removes a request and says whether it was still there: a store shared by several
processes does it atomically — a `DELETE` whose count of affected rows it reads —, so that two answers to the same
request get one `true`. `ProofStore::append()` only adds; `forgetGuardian()` is the one change it makes to a proof.

A store keeps instants in UTC, the way the records give them, and dates as `YYYY-MM-DD`. A store holding state between
requests implements `ResetInterface`, as the stores in memory do.

## The notifier

`Notifier::sendConfirmation($request, $token)` writes to `$request->guardian->email`, in the application's words and
language, with a link built from `$token->toString()`: the library knows neither the site's addresses nor its mailer.
A notifier that queues the message carries the request's identifier, not the token or the address, and reads them
again when it sends — or sends at once.

`Notifier::sendHandoverNotice($member)` tells the member, at the address the application knows for them.

## The guardian link

`GuardianLink::manages($guardian, $member)` widens who may withdraw: a guardian who decided on one of the member's
consents always may. `NoGuardianLink`, the default, names nobody.
