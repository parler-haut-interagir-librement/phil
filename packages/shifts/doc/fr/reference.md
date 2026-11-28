<!-- phil-dev source-sha256=86d531a0e7ef3e31b228ab24e0ec7d492399bcc65bc11acb5eab717ac1fafbd2 -->
# Référence

## `Phil\Shifts\Shift`

| Type | Rôle |
|---|---|
| `Shift` | Un quart : identifiant, titre, type, fenêtre, minutes, places, mode, réserve, rôle, critique, pondération, approbation des échanges, lieu, description ; `weightedMinutes()`, `overlaps()`, `isOpenAt()`, `isOverAt()`, `admits()`. |
| `ShiftKind` | `Fixed`, `Task`, `Duration`. |
| `AssignmentMode` | `SelfSignUp`, `Assigned`, `Interest`. |
| `Identifier` | Les règles des identifiants des quarts et des offres, des bénévoles, des rôles et des textes. |

## `Phil\Shifts\Roster`

| Type | Rôle |
|---|---|
| `Roster` | Les bénévoles d'un quart, immuable : `open()`, `signUp()`, `expressInterest()`, `assign()`, `withdraw()`, `allocate()`, `transfer()`, `withShift()`, `statusOf()`, `confirmed()`, `reserve()`, `interested()`, `placesLeft()`, `isUnderstaffed()` ; les événements du changement dans `$changes`. |
| `Place` | Un bénévole sur une liste : état, instant, coordonnateur qui l'a affecté ; `with()`. |
| `PlaceStatus` | `Confirmed`, `Reserve`, `Interested`. |
| `Conflicts` | Les quarts fixes qu'un nouveau chevauche, avec un repos entre eux ; `of()`. |

## `Phil\Shifts\Allocation`

| Type | Rôle |
|---|---|
| `InterestAllocator` | Choisit qui reçoit les places libres parmi les intéressés ; `choose()`. |
| `FairAllocator` | D'abord ceux qui détiennent le moins de minutes, puis le plus ancien intérêt, puis l'identifiant. |

## `Phil\Shifts\Swap`

| Type | Rôle |
|---|---|
| `SwapOffer` | Une place sur le tableau d'échange : `offer()`, `take()`, `approve()`, `refuse()`, `cancel()`, `apply()`, `isStale()`. |
| `SwapStatus` | `Open`, `Pending`, `Completed`, `Cancelled` ; `isActive()`. |

## `Phil\Shifts\Hours`

| Type | Rôle |
|---|---|
| `ServedHours` | Les minutes servies et pondérées sur les quarts terminés ; `of()`, et les minutes que détient chaque bénévole, `held()`. |

## `Phil\Shifts\Event`

| Type | Rôle |
|---|---|
| `ShiftEvent` | Ce qui a changé, par les identifiants du quart et du bénévole. |
| `VolunteerConfirmed` | Une place prise ; le coordonnateur qui l'a affectée dans `assignedBy`. |
| `VolunteerReserved` | Une inscription attend dans la réserve. |
| `InterestExpressed` | Un bénévole est intéressé. |
| `VolunteerWithdrew` | Un bénévole a quitté le quart. |
| `ReservePromoted` | Le premier de la réserve a pris une place libérée. |
| `PlaceTransferred` | Une place est passée par le tableau d'échange ; le bénévole qui l'a donnée dans `fromVolunteerId`. |
| `SwapOffered` | Une place mise sur le tableau d'échange ; l'offre dans `offerId`. |
| `SwapTaken` | Une offre prise, en attente d'un coordonnateur ; l'offre dans `offerId`. |

## `Phil\Shifts\Format`

| Type | Rôle |
|---|---|
| `ShiftPlan` | Les quarts d'un événement dans le format JSON `phil-shifts/1` ; `fromJson()`, `toJson()`. |
| `ShiftFormat` | Un quart en champs, et inversement. |
| `RosterFormat` | Les places d'une liste en champs, et inversement avec leur quart. |
| `SwapFormat` | Une offre en champs, et inversement. |
| `Fields` | Interne : le lecteur des champs d'un document décodé. |

## `Phil\Shifts\Exception`

| Type | Rôle |
|---|---|
| `ShiftsException` | Toute exception que lance la bibliothèque. |
| `InvalidShift` | Une valeur hors de ses limites, nommée par son champ. |
| `ShiftRefused` | Une opération refusée dans son état, avec une `reason` qu'un écran traduit. |
| `InvalidDocument` | Un document qui ne peut pas être lu, nommé par son chemin. |
