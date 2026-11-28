<!-- phil-dev source-sha256=3188adf58c4fe429d8195c6cce9bbc8596cc597df6729a0ab0fe4885da14c538 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Genre | Rôle |
|---|---|---|
| `CodeOfConduct` | `final readonly class` | Publie les versions d'un code, enregistre les acceptations, répond à l'état d'un membre |
| `CodeVersion` | `final readonly class` | Une version publiée : portée, numéro, titre, texte, genre, date, empreinte |
| `ChangeKind` | `enum` | Majeure, qui demande une nouvelle acceptation, ou mineure |
| `Acceptance` | `final readonly class` | Un membre a accepté une version : son numéro, son empreinte, le moment |
| `AcceptanceStatus` | `enum` | Où en est un membre : aucun code, non accepté, périmé, accepté |
| `Reports` | `final readonly class` | Dépose, liste, ouvre, annote, révise, règle et rejette des signalements |
| `Report` | `final readonly class` | Un signalement : portée, auteur, personnes nommées, texte scellé, version, statut, issue, personnes qui se sont retirées |
| `ReportStatus` | `enum` | Reçu, en révision, réglé, rejeté, et les passages de l'un à l'autre |
| `Outcome` | `enum` | Ce qui a été décidé, de l'absence de mesure à l'exclusion définitive |
| `ReportNote` | `final readonly class` | Une note d'un responsable, scellée |
| `ReportView` | `final readonly class` | Un signalement ouvert : son texte et ses notes |
| `NoteView` | `final readonly class` | Une note ouverte |
| `ReportReceipt` | `final readonly class` | Ce que voit l'auteur : le statut et les dates |
| `ReportPage` | `final readonly class` | Une page des signalements qu'une personne peut traiter, et où la suivante commence |
| `Contract\CodeVersionStore` | interface | Les versions des codes |
| `Contract\AcceptanceStore` | interface | Les acceptations |
| `Contract\ReportStore` | interface | Les signalements et leurs notes |
| `Contract\ResponsiblePeople` | interface | Les responsables d'une portée |
| `Contract\TextSealer` | interface | Scelle et ouvre un texte sous un contexte |
| `KeyManagement\KeyManagementTextSealer` | `final readonly class` | Le scelleur sur le chiffrement d'enveloppe de KeyManagement de Symfony |
| `Memory\InMemoryCodeVersionStore` | `final class` | Les versions en mémoire, pour les tests |
| `Memory\InMemoryAcceptanceStore` | `final class` | Les acceptations en mémoire, pour les tests |
| `Memory\InMemoryReportStore` | `final class` | Les signalements en mémoire, pour les tests |
| `Event\CodeVersionPublished` | `final readonly class` | Une version a été publiée |
| `Event\CodeAccepted` | `final readonly class` | Un membre a accepté une version |
| `Event\ReportFiled` | `final readonly class` | Un signalement a été déposé |
| `Event\ReportViewed` | `final readonly class` | Un responsable a ouvert un signalement |
| `Event\ReportNoteAdded` | `final readonly class` | Une note a été ajoutée |
| `Event\ReportStatusChanged` | `final readonly class` | Un signalement a changé de statut, avec son issue une fois réglé |
| `Event\ReportRecused` | `final readonly class` | Un responsable s'est retiré |
| `Exception\CodeOfConductException` | interface | Toute exception du paquet |
| `Exception\InvalidCodeOfConduct` | `final class`, `InvalidArgumentException` | Une valeur hors de ses bornes |
| `Exception\ActionRefused` | `final class`, `RuntimeException` | Une action que l'état refuse |
| `Exception\ReportUnavailable` | `final class`, `RuntimeException` | Un signalement qui n'existe pas ou qu'on ne peut pas voir, avec le même message |
| `Exception\UnreadableText` | `final class`, `RuntimeException` | Un texte scellé qui ne s'ouvre pas |

`Identifier` et `Text` sont internes : les règles que suivent chaque identifiant et chaque texte libre.
