# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | How |
|---|---|
| A waiting list per session | Install and enable phil/waitlist-bundle: `SessionWaitlists` joins its lists and accepts their offers, and the listener keeps them in step with the seats |
| Who is offered the freed seats, and for how long | phil/waitlist-bundle's options and its `OfferPolicy` port: `first_in_line`, `first_that_fits`, or a policy of the application's own; an offer window per list |
| Which free seats a new registration does not take | Implement `Application\Contract\SeatClaims` and alias it, for a waiting list kept elsewhere; it is asked under the session's lock, inside its transaction |
| Where workshops, sessions and registrations are kept | Implement `Domain\Contract\WorkshopRepository`, `SessionRepository` and `RegistrationRepository` and alias them; `exclusively()` must make the requests on one session run one at a time, with the session read again |
| Reacting to a change | Listen to `RegistrationConfirmed`, `RegistrationCancelled`, `SessionResized`, `SessionRescheduled` and `SessionCancelled`, and to phil/waitlist-bundle's events for the entries; send the e-mails, the notifications and the receipts there |
| The words of the refusals and the statuses | The messages of the `phil_workshops` domain, in the application's translations |
| Rules of the application's own | Check them before calling `Workshops::register()` or `SessionWaitlists::join()`: an age, a level, a payment, a ticket |
| What a workshop or a session adds | The application's own tables, by the identifiers of the workshop and the session |
| The seats per registration, the waiting list on or off | The `max_seats` and `waitlist` options |
| The clock | The `clock` service: the bundle never reads the time itself |

`SeatClaims` decides from what the waiting list holds and reads nothing of the session's registrations: `Workshops`
counts the seats taken itself, and gives a new registration the free seats minus those claimed.
