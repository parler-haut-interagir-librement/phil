# Reference

Every public type of the package, with its role.

| Type | Kind | Role |
|---|---|---|
| `Phil\GuardianConsent\GuardianConsent` | `final readonly class` | The entry point: register, status, request, open, decide, revoke, hand over, sweep |
| `Phil\GuardianConsent\GuardianConsentRights` | `final readonly class` | Access and erasure of what the library keeps on a person |
| `Phil\GuardianConsent\MemberId`, `GuardianId` | `final readonly class` | Stable identifiers, never e-mail addresses |
| `Phil\GuardianConsent\Guardian` | `final readonly class` | A guardian's identifier and the address the confirmation is sent to |
| `Phil\GuardianConsent\Feature` | `final readonly class` | A feature of the site, named by it |
| `Phil\GuardianConsent\NoticeVersion` | `final readonly class` | The version of the text shown to the person who consents |
| `Phil\GuardianConsent\CalendarDate` | `final readonly class` | A day of the calendar: `fromString()`, `of()`, `anniversary()`, `yearsUntil()`, comparisons |
| `Phil\GuardianConsent\LeapDayRule` | enum | `FirstOfMarch`, `TwentyEighthOfFebruary` |
| `Phil\GuardianConsent\Jurisdiction` | `final readonly class` | The rules of a place: age of consent, majority, time zone, leap day; `quebec()`, `assess()` |
| `Phil\GuardianConsent\Jurisdictions` | `final readonly class` | The jurisdictions served, by code, with the default one |
| `Phil\GuardianConsent\AgeAssessment` | `final readonly class` | An age on a day, its bracket, and the days the thresholds are reached |
| `Phil\GuardianConsent\AgeBracket` | enum | `BelowConsentAge`, `MinorAtConsentAge`, `Adult` |
| `Phil\GuardianConsent\Minor` | `final readonly class` | A member below the age of consent, without their date of birth |
| `Phil\GuardianConsent\ConsentRequest` | `final readonly class` | A request sent to a guardian, with the hash of its token |
| `Phil\GuardianConsent\ConfirmationToken` | `final readonly class` | The secret of a confirmation link: `generate()`, `parse()`, `toString()` |
| `Phil\GuardianConsent\TokenHasher` | `final readonly class` | The keyed hash of a token, with key rotation |
| `Phil\GuardianConsent\ConsentProof` | `final readonly class` | The dated record of one decision on one feature |
| `Phil\GuardianConsent\Decision` | enum | `Granted`, `Refused`, `Revoked` |
| `Phil\GuardianConsent\Actor` | enum | `Guardian`, `Member` |
| `Phil\GuardianConsent\ConsentStatus` | enum | `NotRequired`, `Granted`, `Pending`, `Refused`, `HandoverRequired`; `allows()` |
| `Phil\GuardianConsent\SweepReport` | `final readonly class` | What a sweep erased and told |
| `Phil\GuardianConsent\NoGuardianLink` | `final readonly class` | The guardian link that names nobody |
| `Phil\GuardianConsent\NoTransactions` | `final readonly class` | Runs the work without a transaction, for the stores in memory |
| `Phil\GuardianConsent\Contract\MinorStore` | interface | Where minors are kept |
| `Phil\GuardianConsent\Contract\RequestStore` | interface | Where requests are kept |
| `Phil\GuardianConsent\Contract\ProofStore` | interface | Where proofs are kept |
| `Phil\GuardianConsent\Contract\Notifier` | interface | What sends the confirmation and the handover notice |
| `Phil\GuardianConsent\Contract\GuardianLink` | interface | Whether an adult manages a member's account |
| `Phil\GuardianConsent\Contract\Transactions` | interface | The transactions of the stores' database |
| `Phil\GuardianConsent\Contract\SubjectRights` | interface | Access and erasure, for the privacy tooling |
| `Phil\GuardianConsent\Memory\InMemoryMinorStore`, `InMemoryRequestStore`, `InMemoryProofStore` | `final class` | The stores in memory, with `reset()` |
| `Phil\GuardianConsent\Event\ConsentRequested`, `ConsentDecided`, `ConsentRevoked`, `RequestErased`, `HandoverDue`, `HandoverCompleted` | `final readonly class` | The events, carrying identifiers only |
| `Phil\GuardianConsent\Exception\GuardianConsentException` | interface | Every exception of the package |
| `Phil\GuardianConsent\Exception\InvalidIdentifier` | `final class` | An identifier that cannot be kept |
| `Phil\GuardianConsent\Exception\InvalidConsent` | `final class` | A value of a consent out of bounds |
| `Phil\GuardianConsent\Exception\InvalidConfiguration` | `final class` | A setting out of bounds |
| `Phil\GuardianConsent\Exception\UnknownJurisdiction` | `final class` | No jurisdiction has the code |
| `Phil\GuardianConsent\Exception\InvalidToken` | `final class` | A confirmation token that opens nothing |
| `Phil\GuardianConsent\Exception\ExpiredRequest` | `final class` | A request that may no longer be answered |
| `Phil\GuardianConsent\Exception\ConsentNotRequired` | `final class` | A guardian's consent where none applies |
| `Phil\GuardianConsent\Exception\NotAGuardian` | `final class` | A withdrawal by someone with no standing |

`Phil\GuardianConsent\Internal\Identifier` and `RandomId` are internal.
