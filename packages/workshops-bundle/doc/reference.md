# Reference

| Type | Role |
|---|---|
| `PhilWorkshopsBundle` | Wires the services, maps the entities, configures `phil_workshops`, and adds the waiting lists when phil/waitlist-bundle is enabled |
| `Domain\Model\Workshop` | What is taught: its title, whether it takes new sessions |
| `Domain\Model\WorkshopSession` | One time a workshop is given: span, time zone, capacity, registration window, status |
| `Domain\Model\Registration` | A participant's seats in a session |
| `Domain\Status\SessionStatus` | Scheduled, cancelled |
| `Domain\Status\RegistrationStatus` | Confirmed, cancelled |
| `Domain\Status\RefusalReason` | Why a session refuses a registration or a place on its waiting list |
| `Domain\Contract\WorkshopRepository` | The port of the workshops |
| `Domain\Contract\SessionRepository` | The port of the sessions |
| `Domain\Contract\RegistrationRepository` | The port of the registrations and of the session's lock |
| `Domain\Event\Registration*`, `Domain\Event\Session*` | The events of the changes, with identifiers only |
| `Domain\Exception\InvalidWorkshop` | A value or a change refused |
| `Application\Workshops` | Creates workshops; schedules, moves, resizes and cancels sessions; registers and cancels; counts seats |
| `Application\SeatCount` | A session's seats: capacity, taken, free, claimed, available |
| `Application\Contract\SeatClaims` | The port that says which free seats a waiting list claims, and how many it offered |
| `Application\Exception\RegistrationRefused` | A refusal, with its reason and its parameters |
| `Application\Exception\NotFound` | A workshop, session or registration missing |
| `Infrastructure\Doctrine\DoctrineWorkshopRepository` | The workshops with Doctrine |
| `Infrastructure\Doctrine\DoctrineSessionRepository` | The sessions with Doctrine |
| `Infrastructure\Doctrine\DoctrineRegistrationRepository` | The registrations and the lock with Doctrine |
| `Infrastructure\Translation\RefusalMessages` | The refusals and the statuses in the reader's language |
| `Integration\Waitlist\SessionWaitlists` | Each session's waiting list in phil/waitlist-bundle: join, accept an offer, balance, close |
| `Integration\Waitlist\WaitlistSeatClaims` | `SeatClaims` read from the session's waiting list |
| `Integration\Waitlist\SessionWaitlistListener` | Balances a session's list when seats free up or an entry leaves, closes it when the session is cancelled |

| Service | Public | Arguments |
|---|---|---|
| `Application\Workshops` | yes | The three ports, `clock`, `event_dispatcher` if any, `max_seats`, `SeatClaims` if any |
| `Infrastructure\Translation\RefusalMessages` | yes | `translator` |
| `Domain\Contract\*Repository` | yes, aliases | The Doctrine adapters, on `doctrine.orm.entity_manager` |
| `Integration\Waitlist\SessionWaitlists` | yes, with the waiting lists | `SessionRepository`, `RegistrationRepository`, `Waitlists`, `clock`, `event_dispatcher` if any, `max_seats` |
| `Application\Contract\SeatClaims` | alias, with the waiting lists | `WaitlistSeatClaims`, on `Waitlists` |
| `Integration\Waitlist\SessionWaitlistListener` | no, with the waiting lists | `SessionWaitlists`; listens to `RegistrationCancelled`, `SessionResized`, `SessionCancelled` and phil/waitlist-bundle's `EntryLeft` |

The services marked "with the waiting lists" exist when phil/waitlist-bundle is installed and enabled and the
`waitlist` option is on.
