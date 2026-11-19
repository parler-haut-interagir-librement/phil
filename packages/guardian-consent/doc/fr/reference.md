<!-- phil-dev source-sha256=a0e7e4da52f17ab315e044c1d51660fae30432fb741e7cdd5835104bd6421b3a -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Genre | Rôle |
|---|---|---|
| `Phil\GuardianConsent\GuardianConsent` | `final readonly class` | Le point d'entrée : inscrire, statut, demander, ouvrir, décider, retirer, passer le relais, balayer |
| `Phil\GuardianConsent\GuardianConsentRights` | `final readonly class` | Accès et effacement de ce que la bibliothèque garde sur une personne |
| `Phil\GuardianConsent\MemberId`, `GuardianId` | `final readonly class` | Des identifiants stables, jamais des adresses de courriel |
| `Phil\GuardianConsent\Guardian` | `final readonly class` | L'identifiant d'un tuteur et l'adresse à laquelle la confirmation est envoyée |
| `Phil\GuardianConsent\Feature` | `final readonly class` | Une fonctionnalité du site, nommée par lui |
| `Phil\GuardianConsent\NoticeVersion` | `final readonly class` | La version du texte montré à la personne qui consent |
| `Phil\GuardianConsent\CalendarDate` | `final readonly class` | Un jour du calendrier : `fromString()`, `of()`, `anniversary()`, `yearsUntil()`, comparaisons |
| `Phil\GuardianConsent\LeapDayRule` | enum | `FirstOfMarch`, `TwentyEighthOfFebruary` |
| `Phil\GuardianConsent\Jurisdiction` | `final readonly class` | Les règles d'un lieu : âge du consentement, majorité, fuseau horaire, jour bissextile ; `quebec()`, `assess()` |
| `Phil\GuardianConsent\Jurisdictions` | `final readonly class` | Les juridictions servies, par code, avec celle par défaut |
| `Phil\GuardianConsent\AgeAssessment` | `final readonly class` | Un âge un jour donné, sa tranche, et les jours où les seuils sont atteints |
| `Phil\GuardianConsent\AgeBracket` | enum | `BelowConsentAge`, `MinorAtConsentAge`, `Adult` |
| `Phil\GuardianConsent\Minor` | `final readonly class` | Un membre sous l'âge du consentement, sans sa date de naissance |
| `Phil\GuardianConsent\ConsentRequest` | `final readonly class` | Une demande envoyée à un tuteur, avec l'empreinte de son jeton |
| `Phil\GuardianConsent\ConfirmationToken` | `final readonly class` | Le secret d'un lien de confirmation : `generate()`, `parse()`, `toString()` |
| `Phil\GuardianConsent\TokenHasher` | `final readonly class` | L'empreinte à clé d'un jeton, avec rotation des clés |
| `Phil\GuardianConsent\ConsentProof` | `final readonly class` | L'enregistrement daté d'une décision sur une fonctionnalité |
| `Phil\GuardianConsent\Decision` | enum | `Granted`, `Refused`, `Revoked` |
| `Phil\GuardianConsent\Actor` | enum | `Guardian`, `Member` |
| `Phil\GuardianConsent\ConsentStatus` | enum | `NotRequired`, `Granted`, `Pending`, `Refused`, `HandoverRequired` ; `allows()` |
| `Phil\GuardianConsent\SweepReport` | `final readonly class` | Ce qu'un balayage a effacé et communiqué |
| `Phil\GuardianConsent\NoGuardianLink` | `final readonly class` | Le lien de tuteur qui ne nomme personne |
| `Phil\GuardianConsent\Contract\MinorStore` | interface | Où les mineurs sont gardés |
| `Phil\GuardianConsent\Contract\RequestStore` | interface | Où les demandes sont gardées |
| `Phil\GuardianConsent\Contract\ProofStore` | interface | Où les preuves sont gardées |
| `Phil\GuardianConsent\Contract\Notifier` | interface | Ce qui envoie la confirmation et l'avis de passage de relais |
| `Phil\GuardianConsent\Contract\GuardianLink` | interface | Si un adulte gère le compte d'un membre |
| `Phil\GuardianConsent\Contract\SubjectRights` | interface | Accès et effacement, pour les outils de vie privée |
| `Phil\GuardianConsent\Memory\InMemoryMinorStore`, `InMemoryRequestStore`, `InMemoryProofStore` | `final class` | Les dépôts en mémoire, avec `reset()` |
| `Phil\GuardianConsent\Event\ConsentRequested`, `ConsentDecided`, `ConsentRevoked`, `RequestErased`, `HandoverDue`, `HandoverCompleted` | `final readonly class` | Les événements, qui ne portent que des identifiants |
| `Phil\GuardianConsent\Exception\GuardianConsentException` | interface | Toute exception du paquet |
| `Phil\GuardianConsent\Exception\InvalidIdentifier` | `final class` | Un identifiant qui ne peut pas être gardé |
| `Phil\GuardianConsent\Exception\InvalidConsent` | `final class` | Une valeur de consentement hors des bornes |
| `Phil\GuardianConsent\Exception\InvalidConfiguration` | `final class` | Un réglage hors des bornes |
| `Phil\GuardianConsent\Exception\UnknownJurisdiction` | `final class` | Aucune juridiction n'a ce code |
| `Phil\GuardianConsent\Exception\InvalidToken` | `final class` | Un jeton de confirmation qui n'ouvre rien |
| `Phil\GuardianConsent\Exception\ExpiredRequest` | `final class` | Une demande qui ne peut plus recevoir de réponse |
| `Phil\GuardianConsent\Exception\ConsentNotRequired` | `final class` | Un consentement de tuteur là où aucun ne s'applique |
| `Phil\GuardianConsent\Exception\NotAGuardian` | `final class` | Un retrait par quelqu'un sans qualité pour le faire |

`Phil\GuardianConsent\Internal\Identifier` et `RandomId` sont internes.
