<!-- phil-dev source-sha256=e070e2666a28b8f5ecc9951d388495743c8252fcdec2bea5771fb154be6c9c5a -->
# Référence

## `Phil\ShiftsBundle`

| Type | Rôle |
|---|---|
| `PhilShiftsBundle` | Le bundle et sa configuration `phil_shifts`. |

## `Phil\ShiftsBundle\Application`

| Type | Rôle |
|---|---|
| `Rosters` | Les quarts et leurs listes : `plan()`, `correct()`, `remove()`, `signUp()`, `expressInterest()`, `assign()`, `withdraw()`, `allocate()`, `find()`, `upcoming()`, `ofVolunteer()`, `served()`, `isOpen()`, `dispatch()`. |
| `SwapBoard` | Le tableau d'échange : `offer()`, `take()`, `approve()`, `refuse()`, `cancel()`, `find()`, `board()`. |
| `Minors` | Les bénévoles mineurs et leurs tuteurs : `isEnabled()`, `isMinor()`, `mayJoin()`, `assertMayJoin()`, `record()`, `consent()`, `withdrawConsent()`, `contactOf()`, `forget()`. |
| `Absences` | Les absences non annoncées : `record()`, `hasStarted()`, `forgive()`, `count()`, `isBlocked()`, `assertMayJoinFreely()`, `lift()`, `ofVolunteer()`, `absentFrom()`. |
| `Contract\BirthDates` | Les dates de naissance que l'application connaît de ses bénévoles. |
| `Contract\CurrentPerson` | La personne connectée et ses rôles. |
| `Contract\Transaction` | Des écritures exécutées toutes ensemble ou pas du tout. |
| `Exception\RequestRefused` | Une opération refusée par le bundle : `shifts.unknown_shift`, `shifts.unknown_offer`, `shifts.exists`, `shifts.busy`, `shifts.untrusted_request`, `shifts.guardian_consent`, `shifts.no_guardian`, `shifts.absences`, `shifts.not_started`. |
| `Exception\StaleWrite` | Interne : une autre requête a écrit avant. |
| `Message\AllocateInterest` | Demande à un *worker* d'attribuer les places d'un quart, par son identifiant. |
| `View\BoardEntry` | Une offre du tableau d'échange avec la liste de son quart. |

## `Phil\ShiftsBundle\Domain`

| Type | Rôle |
|---|---|
| `Model\StoredRoster` | Une liste avec la révision de son stockage. |
| `Model\StoredSwap` | Une offre avec la révision de son stockage. |
| `Model\GuardianContact` | Le contact d'un tuteur et son consentement ; `hasConsent()`, `withConsent()`, `withoutConsent()`. |
| `Model\Absence` | Une absence non annoncée ; `isLifted()`. |
| `Exception\InvalidGuardianContact` | Un contact refusé, avec sa raison : `shifts.guardian.invalid_name`, `invalid_phone`, `invalid_email`, `no_way_to_reach`, `invalid_person`. |
| `Repository\RosterRepository` | Les quarts et leurs listes. |
| `Repository\SwapRepository` | Les offres du tableau d'échange. |
| `Repository\GuardianRepository` | Les contacts des tuteurs. |
| `Repository\AbsenceRepository` | Les absences non annoncées. |

## `Phil\ShiftsBundle\Infrastructure`

| Type | Rôle |
|---|---|
| `Doctrine\ShiftsTables` | Les tables ; `configure()`. |
| `Doctrine\ShiftsSchemaListener` | Ajoute les tables au schéma que génère l'ORM. |
| `Doctrine\DbalRosterRepository`, `Doctrine\DbalSwapRepository`, `Doctrine\DbalGuardianRepository`, `Doctrine\DbalAbsenceRepository` | Les dépôts par Doctrine DBAL. |
| `Doctrine\DbalTransaction` | Une transaction de la connexion du bundle. |
| `Doctrine\Json` | Interne : le JSON des colonnes stockées. |
| `Form\ShiftType` | Le formulaire d'un quart ; `data()`, `shift()`. |
| `Http\VolunteerController`, `Http\SwapController`, `Http\CoordinatorController` | Les pages. |
| `Http\Pages`, `Http\Access`, `Http\ShiftsGuard` | Ce que les pages partagent : le rendu, le voter et la personne, le jeton CSRF. |
| `Messenger\AllocateInterestHandler` | Traite `AllocateInterest`. |
| `Security\ShiftsVoter` | `phil_shifts.volunteer` et `phil_shifts.coordinate`. |
| `Security\TokenCurrentPerson` | La personne et les rôles du jeton de Symfony Security. |
| `Twig\Components\ShiftCardComponent` | `<twig:PhilShifts:ShiftCard>`. |
| `Twig\Components\MyShiftsComponent` | `<twig:PhilShifts:MyShifts>`. |
| `Console\ImportPlanCommand` | `phil:shifts:import <file> [--update]`. |
| `Privacy\ShiftsPrivacy` | L'export et l'effacement de phil/privacy-bundle, module `shifts`. |
| `DependencyInjection\ShiftsServices`, `DependencyInjection\CsrfProtectionPass` | Les services, et la vérification de CSRF, de Security et de Forms. |

## Routes

| Route | Méthode et chemin |
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

## Traductions

Le domaine `phil_shifts`, dans `translations/phil_shifts.en.php` et `translations/phil_shifts.fr.php`.
