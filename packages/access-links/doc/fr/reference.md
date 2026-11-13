<!-- phil-dev source-sha256=49ab155ba0c6f3d39266db427ee449393fd937f3114eb76be7f26d2b2b636b42 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Genre | Rôle |
|---|---|---|
| `Phil\AccessLinks\AccessLinks` | `final readonly class` | La bibliothèque en un objet : `invite()`, `reissue()`, `open()`, `respond()`, `revoke()`, `dueReminders()`, `remind()`, `invitation()`, `counts()` |
| `Phil\AccessLinks\Invitation` | `final readonly class` | Un destinataire invité à une campagne : moments, réponse, rappels, révocation |
| `Phil\AccessLinks\InvitationAccess` | `final readonly class` | Ce qu'un lien a ouvert : l'invitation et si elle est modifiable |
| `Phil\AccessLinks\IssuedLink` | `final readonly class` | Un lien qui vient d'être émis : invitation, jeton et adresse, jamais sérialisé |
| `Phil\AccessLinks\CampaignCounts` | `final readonly class` | Les invitations d'une campagne envoyées, répondues, révoquées et en attente |
| `Phil\AccessLinks\LinkToken` | `final class` | Tire et lit les jetons de 32 octets |
| `Phil\AccessLinks\TokenHasher` | `final readonly class` | Le HMAC-SHA-256 d'un jeton sous une liste ordonnée de clés |
| `Phil\AccessLinks\LinkFormat` | `final readonly class` | L'adresse de la page d'invitation avec un jeton |
| `Phil\AccessLinks\ReminderSchedule` | `final readonly class` | Quand les rappels échoient |
| `Phil\AccessLinks\Contract\InvitationStore` | interface | Là où vivent les invitations et les empreintes de leurs liens |
| `Phil\AccessLinks\Contract\ReminderSender` | interface | Comment l'application joint un destinataire |
| `Phil\AccessLinks\InMemoryInvitationStore` | `final class` | Les invitations d'un seul processus |
| `Phil\AccessLinks\Dbal\DbalInvitationStore` | `final readonly class` | Les invitations dans deux tables d'une connexion Doctrine DBAL |
| `Phil\AccessLinks\Messenger\SendInvitationReminder` | `final readonly class` | Le message d'un rappel échu : l'identifiant de l'invitation |
| `Phil\AccessLinks\Messenger\SendInvitationReminderHandler` | `final readonly class` | Envoie le rappel qu'un message demande |
| `Phil\AccessLinks\Messenger\ReminderDispatcher` | `final readonly class` | Envoie un message par rappel échu |
| `Phil\AccessLinks\Exception\AccessLinksException` | interface | Chaque exception de la bibliothèque |
| `Phil\AccessLinks\Exception\InvalidConfiguration` | `final class` | Une valeur avec laquelle la bibliothèque ne peut pas travailler |
| `Phil\AccessLinks\Exception\InvalidToken` | `final class` | Un texte qui n'est pas un jeton de lien |
| `Phil\AccessLinks\Exception\LinkNotUsable` | `final class` | Un lien qui n'ouvre rien |
| `Phil\AccessLinks\Exception\InvitationClosed` | `final class` | Une réponse après la clôture |
| `Phil\AccessLinks\Exception\AlreadyInvited` | `final class` | Un destinataire invité deux fois à une campagne |
| `Phil\AccessLinks\Exception\UnknownInvitation` | `final class` | Une invitation que le magasin ne contient pas |
