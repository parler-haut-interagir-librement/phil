<!-- phil-dev source-sha256=85724f8b779ee25bce87c79b9abb721285b1669e00af503621b7770933a74463 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Genre | Rôle |
|---|---|---|
| `SafetyInfoVault` | `final readonly class` | Tout chemin vers l'information : soumettre, lire, briser la glace, copier, effacer, journaliser, purger |
| `SafetyInfo` | `final readonly class` | Ce qu'un participant dit : jusqu'à trois contacts, allergies, besoins, notes |
| `EmergencyContact` | `final readonly class` | Une personne à appeler : un nom, un lien, un numéro de téléphone |
| `SafetyInfoCodec` | `final class` | La forme en clair de l'information, JSON de version 1, décodé strictement |
| `SealedSafetyInfo` | `final readonly class` | Ce que garde le magasin : le texte scellé, le moment du scellement, la date jusqu'à laquelle il est gardé |
| `AccessWindow` | `final readonly class` | Quand les responsables d'un événement peuvent lire |
| `AccessRecord` | `final readonly class` | Une lecture par quelqu'un d'autre : qui, pourquoi, quand, la raison d'un bris de glace |
| `AccessReason` | `enum` | Pourquoi une lecture a été permise : responsable, ou bris de glace |
| `Role` | `enum` | Ce qu'une personne est pour un événement : responsable, équipe ou aucun |
| `Contract\SafetyInfoStore` | interface | Les enregistrements scellés |
| `Contract\SafetyInfoSealer` | interface | Scelle et ouvre un texte sous un contexte |
| `Contract\EventRoles` | interface | Le rôle d'une personne pour un événement |
| `Contract\AccessWindows` | interface | La fenêtre d'accès d'un événement |
| `Contract\AccessJournal` | interface | Le journal des lectures |
| `KeyManagement\KeyManagementSealer` | `final readonly class` | Le scelleur sur le chiffrement d'enveloppe de KeyManagement de Symfony |
| `Memory\InMemorySafetyInfoStore` | `final class` | Le magasin en mémoire, pour les tests |
| `Memory\InMemoryAccessJournal` | `final class` | Le journal en mémoire, pour les tests |
| `Event\SafetyInfoSubmitted` | `final readonly class` | Un participant a soumis ou remplacé son information |
| `Event\SafetyInfoRead` | `final readonly class` | Quelqu'un d'autre l'a lue, et pourquoi |
| `Event\SafetyInfoErased` | `final readonly class` | Un participant l'a effacée |
| `Exception\ParticipantSafetyInfoException` | interface | Toute exception du paquet |
| `Exception\InvalidSafetyInfo` | `final class`, `InvalidArgumentException` | Une valeur hors de ses bornes |
| `Exception\AccessDenied` | `final class`, `RuntimeException` | Une lecture ou une soumission refusée, avec le même message quelle que soit la raison |
| `Exception\UnreadableSafetyInfo` | `final class`, `RuntimeException` | Un enregistrement qui ne s'ouvre pas ou ne se décode pas |

`Identifier` est interne : la règle que suit chaque identifiant.
