# Reference

## `Phil\ShiftsBundle`

| Type | Role |
|---|---|
| `PhilShiftsBundle` | The bundle and its configuration `phil_shifts`. |

## `Phil\ShiftsBundle\Application`

| Type | Role |
|---|---|
| `Rosters` | The shifts and their rosters: `plan()`, `correct()`, `remove()`, `signUp()`, `expressInterest()`, `assign()`, `withdraw()`, `allocate()`, `find()`, `upcoming()`, `ofVolunteer()`, `served()`, `isOpen()`, `dispatch()`. |
| `SwapBoard` | The swap board: `offer()`, `take()`, `approve()`, `refuse()`, `cancel()`, `find()`, `board()`. |
| `Minors` | The volunteers who are minors and their guardians: `isEnabled()`, `isMinor()`, `mayJoin()`, `assertMayJoin()`, `record()`, `consent()`, `withdrawConsent()`, `contactOf()`, `forget()`. |
| `Absences` | The unannounced absences: `record()`, `hasStarted()`, `forgive()`, `count()`, `isBlocked()`, `assertMayJoinFreely()`, `lift()`, `ofVolunteer()`, `absentFrom()`. |
| `Contract\BirthDates` | The birth dates the application knows of its volunteers. |
| `Contract\CurrentPerson` | The person signed in and their roles. |
| `Contract\Transaction` | Writes run all at once or not at all. |
| `Exception\RequestRefused` | An operation refused by the bundle: `shifts.unknown_shift`, `shifts.unknown_offer`, `shifts.exists`, `shifts.busy`, `shifts.untrusted_request`, `shifts.guardian_consent`, `shifts.no_guardian`, `shifts.absences`, `shifts.not_started`. |
| `Exception\StaleWrite` | Internal: another request wrote first. |
| `Message\AllocateInterest` | Asks a worker to allocate the places of a shift, by its identifier. |
| `View\BoardEntry` | An offer of the swap board with the roster of its shift. |

## `Phil\ShiftsBundle\Domain`

| Type | Role |
|---|---|
| `Model\StoredRoster` | A roster with the revision of its storage. |
| `Model\StoredSwap` | An offer with the revision of its storage. |
| `Model\GuardianContact` | The contact of a guardian and their consent; `hasConsent()`, `withConsent()`, `withoutConsent()`. |
| `Model\Absence` | An unannounced absence; `isLifted()`. |
| `Exception\InvalidGuardianContact` | A contact refused, with its reason: `shifts.guardian.invalid_name`, `invalid_phone`, `invalid_email`, `no_way_to_reach`, `invalid_person`. |
| `Repository\RosterRepository` | The shifts and their rosters. |
| `Repository\SwapRepository` | The offers of the swap board. |
| `Repository\GuardianRepository` | The contacts of the guardians. |
| `Repository\AbsenceRepository` | The unannounced absences. |

## `Phil\ShiftsBundle\Infrastructure`

| Type | Role |
|---|---|
| `Doctrine\ShiftsTables` | The tables; `configure()`. |
| `Doctrine\ShiftsSchemaListener` | Adds the tables to the schema the ORM generates. |
| `Doctrine\DbalRosterRepository`, `Doctrine\DbalSwapRepository`, `Doctrine\DbalGuardianRepository`, `Doctrine\DbalAbsenceRepository` | The repositories through Doctrine DBAL. |
| `Doctrine\DbalTransaction` | A transaction of the bundle's connection. |
| `Doctrine\Json` | Internal: the JSON of the stored columns. |
| `Form\ShiftType` | The form of a shift; `data()`, `shift()`. |
| `Http\VolunteerController`, `Http\SwapController`, `Http\CoordinatorController` | The pages. |
| `Http\Pages`, `Http\Access`, `Http\ShiftsGuard` | What the pages share: rendering, the voter and the person, the CSRF token. |
| `Messenger\AllocateInterestHandler` | Handles `AllocateInterest`. |
| `Security\ShiftsVoter` | `phil_shifts.volunteer` and `phil_shifts.coordinate`. |
| `Security\TokenCurrentPerson` | The person and the roles of Symfony Security's token. |
| `Twig\Components\ShiftCardComponent` | `<twig:PhilShifts:ShiftCard>`. |
| `Twig\Components\MyShiftsComponent` | `<twig:PhilShifts:MyShifts>`. |
| `Console\ImportPlanCommand` | `phil:shifts:import <file> [--update]`. |
| `Privacy\ShiftsPrivacy` | The export and the erasure of phil/privacy-bundle, module `shifts`. |
| `DependencyInjection\ShiftsServices`, `DependencyInjection\CsrfProtectionPass` | The services, and the check of CSRF, Security and Forms. |

## Routes

| Route | Method and path |
|---|---|
| `phil_shifts_board` | `GET /` |
| `phil_shifts_mine` | `GET /mine` |
| `phil_shifts_show` | `GET /shift/{id}` |
| `phil_shifts_sign_up`, `phil_shifts_interest`, `phil_shifts_withdraw`, `phil_shifts_swap_offer` | `POST /shift/{id}/sign-up`, `/interest`, `/withdraw`, `/offer` |
| `phil_shifts_swaps` | `GET /swaps` |
| `phil_shifts_swap_take`, `phil_shifts_swap_cancel` | `POST /swaps/{offer}/take`, `/cancel` |
| `phil_shifts_manage` | `GET /manage` |
| `phil_shifts_manage_create` | `GET, POST /manage/new` |
| `phil_shifts_manage_roster` | `GET /manage/{id}` |
| `phil_shifts_manage_edit` | `GET, POST /manage/{id}/edit` |
| `phil_shifts_manage_assign`, `phil_shifts_manage_remove`, `phil_shifts_manage_allocate`, `phil_shifts_manage_delete` | `POST /manage/{id}/assign`, `/remove`, `/allocate`, `/delete` |
| `phil_shifts_manage_absent`, `phil_shifts_manage_forgive`, `phil_shifts_manage_lift` | `POST /manage/{id}/absent`, `/forgive`, `/lift` |
| `phil_shifts_manage_approve`, `phil_shifts_manage_refuse` | `POST /manage/swaps/{offer}/approve`, `/refuse` |

## Translations

The domain `phil_shifts`, in `translations/phil_shifts.en.php` and `translations/phil_shifts.fr.php`.
