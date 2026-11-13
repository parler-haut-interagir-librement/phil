<!-- phil-dev source-sha256=fc3c963815f898a1581a91589d9a6702ada87d76753f85a098d1c08d0450b9ae -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

La bibliothèque est du PHP simple et ne déclare aucun service. `AccessLinks` reçoit un magasin, un calculateur
d'empreintes, un format de lien, une horloge et, facultativement, un calendrier de rappels et un générateur aléatoire.

## `InvitationStore`

`Phil\AccessLinks\Contract\InvitationStore` — là où vivent les invitations et les empreintes de leurs liens.
`InMemoryInvitationStore` et `Dbal\DbalInvitationStore` sont fournis ; voir [Stockage](storage.md).

| Méthode | Contrat |
|---|---|
| `add(Invitation $invitation, string $tokenHash): bool` | Ajoute l'invitation et son premier lien ; `false`, sans rien ajouter, pour un destinataire déjà invité à la campagne |
| `addLink(string $invitationId, string $tokenHash, int $maxLinks): bool` | Ajoute un lien sauf si l'invitation a `$maxLinks` liens ou est inconnue |
| `get(string $invitationId): ?Invitation` | L'invitation, ou `null` |
| `findByToken(array $candidates): ?Invitation` | L'invitation du lien que représente l'une de ces empreintes |
| `respond(string $invitationId, string $answer, DateTimeImmutable $now): bool` | Enregistre la réponse de façon atomique, seulement tant que l'invitation n'est ni révoquée, ni invalide, ni close ; fixe la première et la dernière réponse, arrête les rappels |
| `claimReminder(string $invitationId, int $sent, ?DateTimeImmutable $next, DateTimeImmutable $now): bool` | Réclame le rappel échu de façon atomique quand `$sent` rappels ont été envoyés et qu'il n'y a ni réponse, ni révocation, ni clôture |
| `due(DateTimeImmutable $now, int $limit): array` | Les identifiants des invitations dont le rappel est échu, les plus en retard d'abord |
| `revoke(string $invitationId, DateTimeImmutable $now): bool` | Révoque ; `false` quand l'invitation était déjà révoquée ou est inconnue |
| `counts(string $campaign): CampaignCounts` | Les invitations envoyées, répondues, révoquées avant d'avoir reçu une réponse |

Un magasin propre à l'application reçoit des empreintes, jamais des jetons, et garde chaque écriture conditionnelle : de
deux appels au même moment, un seul l'emporte.

## `ReminderSender`

`Phil\AccessLinks\Contract\ReminderSender` — comment l'application joint un destinataire ; voir [Rappels](reminders.md).

| Méthode | Contrat |
|---|---|
| `sendReminder(IssuedLink $link): void` | Trouve comment joindre `$link->invitation->recipient` et envoie `$link->url` |

## Les autres arguments

| Argument | Quoi passer |
|---|---|
| `TokenHasher` | Les clés ordonnées, lues dans le coffre de secrets |
| `LinkFormat` | L'adresse de la page d'invitation et le nom du paramètre |
| `Psr\Clock\ClockInterface` | L'horloge de l'application ; une `MockClock` dans les tests |
| `ReminderSchedule` | Les délais des rappels ; aucun par défaut |
| `Random\Randomizer` | Le moteur `Secure` par défaut ; un moteur à germe dans les tests seulement |
