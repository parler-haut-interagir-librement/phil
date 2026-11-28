# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | How |
|---|---|
| Who is offered the places held | Implement `Application\Contract\OfferPolicy` and alias it; it gets the places held and the waiting entries by rank, and returns those to offer places to. Priorities, groups of friends or members first go there |
| Where lists and entries are kept | Implement `Domain\Contract\WaitlistRepository` and `EntryRepository` and alias them; `exclusively()` must make the requests on one list run one at a time, with the list read again, and the reads made inside it must give the entries as stored |
| Reacting to a change | Listen to `EntryJoined`, `PlaceOffered`, `OfferAccepted`, `OfferDeclined`, `OfferLapsed`, `EntryLeft` and `WaitlistDrawn`; give the places on `OfferAccepted` |
| Telling participants | phil/notifications-bundle and the `notifications` options; see [Notifications](notifications.md) |
| The words of the refusals | The values of `RefusalReason`, in the application's translations |
| Rules of the application's own | Check them before calling `Waitlists::join()`: an account in good standing, a ticket, an age |
| When the list opens, when places free up | The application calls `open()`, `release()` and `reclaim()`: the list never counts the capacity |
| The offer window, the places per entry, how much each request reads | The `offer_window`, `max_places` and `batch` options; a list's own window at `open()` or `changeOfferWindow()` |
| Lapsing the offers | The `phil:waitlist:lapse` command, or `Waitlists::lapseExpired()`, on the application's schedule |
| The clock | The `clock` service: the bundle never reads the time itself |

The policy is called under the list's lock, inside its transaction: it decides from what it is given and reads nothing
else. Its pick is checked; one that takes more places than are held, or an entry it was not given, rolls the change
back with an `UnexpectedValueException`.
