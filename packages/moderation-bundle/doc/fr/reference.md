<!-- phil-dev source-sha256=efea407fdf9d170158d78ab528f1f2d811f92b9fe5513e3355039b394646d1dd -->
# Référence

Chaque classe et chaque service du bundle, avec son rôle.

| Classe | Rôle |
|---|---|
| `PhilModerationBundle` | Le bundle et sa configuration `phil_moderation` |
| `Domain\ValueObject\MemberId` | Un membre par un identifiant stable, jamais une adresse |
| `Domain\ValueObject\TargetReference` | Un type et l'identifiant d'un contenu |
| `Domain\ValueObject\Report`, `Decision`, `MemberNote`, `QueueItem` | Un signalement, une décision, une note sur un membre, une ligne de la file |
| `Domain\ValueObject\StatementOfReasons` | L'exposé des motifs adressé à l'auteur d'un contenu restreint |
| `Domain\Status\ReportSource`, `DecisionAction`, `Ground` | D'où vient un signalement ; ce que fait une décision et si elle restreint ; sur quoi repose une restriction |
| `Domain\Contract\ReportRepository`, `DecisionRepository`, `MemberNoteRepository`, `SandboxRepository`, `StatementRepository` | Les magasins |
| `Domain\Identifiers` | Les identifiants, les codes et les textes bornés |
| `Application\Reports` | Dépose les signalements des membres et ceux du bundle |
| `Application\Decisions` | Décide sur une cible ; l'historique |
| `Application\StatementsOfReasons`, `Grounds`, `ReasonGrounds`, `Redress` | Les exposés des motifs ; ce que donne un modérateur ; les motifs de chaque raison ; les moyens de contester |
| `Application\ModerationQueue`, `QueueEntry` | La file unique ; une cible à l'examen |
| `Application\Submissions`, `Submission` | Le crochet d'un nouveau contenu |
| `Application\TemporaryHides` | Le masquage par des membres de confiance |
| `Application\MemberNotes` | Les notes de la modération sur les membres |
| `Application\PublicJournal`, `JournalEntry` | Le journal public |
| `Application\ReportSurge` | Si les signalements montent en flèche |
| `Application\Role`, `Roles`, `Moderator`, `Reasons` | Les rôles, un modérateur, les raisons |
| `Application\ModerationTargets`, `TargetView`, `ProfileTarget` | Les cibles enregistrées, ce que montre la file, les profils comme cible |
| `Application\Screening`, `ScreeningVerdict` | Ce que les mots surveillés font d'un texte |
| `Application\FirstPostSandbox`, `NoWordScreen`, `NoTrustedHiders`, `NoCurrentMember` | Les valeurs par défaut des ports |
| `Application\Contract\*` | Les ports : `ModerationTarget`, `MemberProfiles`, `CurrentMember`, `CurrentModerator`, `ModerationMember`, `WordScreen`, `TrustedHiders`, `SandboxPolicy` |
| `Application\Event\*` | `ReportFiled`, `DecisionTaken`, `ContentHeld`, `ContentHiddenByMember`, `StatementIssued` |
| `Application\Exception\UnknownTarget`, `ModerationRefused` | Les refus des services |
| `Infrastructure\Doctrine\*` | Les magasins DBAL, `ModerationTables`, `ModerationSchemaListener`, `Rows` |
| `Infrastructure\Security\TokenCurrentMember`, `TokenCurrentModerator` | Le membre et le modérateur du jeton de sécurité |
| `Infrastructure\Http\AdminController`, `ReportController`, `JournalController`, `StatementController` | Les pages |
| `Infrastructure\EasyAdmin\ModerationAdmin` | Les éléments de menu et les routes dans EasyAdmin |
| `Infrastructure\IncidentMode\IncidentProposal` | Le mode d'incident de `phil/incident-mode-bundle` dans la file : `isOffered()` au rôle de son administration, `isProposed()` quand les signalements affluent et qu'aucun incident n'est en vigueur ; `ROUTE` |
| `Infrastructure\Notifications\StatementNotifier`, `StatementSubjects` | La notification d'un exposé, et ce que son sujet désigne, avec `phil/notifications-bundle` |
| `Infrastructure\Privacy\ModerationPrivacy` | L'export et l'effacement |
| `Infrastructure\Twig\ModerationExtension`, `KitComponents` | Les fonctions Twig ; les noms des recettes |
| `Infrastructure\DependencyInjection\ModerationServices`, `ModerationPass`, `KitSubdirectoryPass` | Les services et les passes |
