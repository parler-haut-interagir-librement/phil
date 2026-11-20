<!-- phil-dev source-sha256=513dd0c3d95922a80c5190837add6616d06e14d1d3b9fb42853cfa7cf788c163 -->
# Points d'extension

Ce qu'une application implémente, configure, remplace ou utilise.

## Ports

### `UserKeyPlatformFactory`

`Phil\AiBundle\Infrastructure\SymfonyAi\UserKeyPlatformFactory` construit une plateforme Symfony AI avec la clé
personnelle d'une personne. Elle est nommée pour chaque plateforme par `user_key_factory`. Voir
[Clés et quotas](keys-and-quotas.md#la-clé-de-la-personne).

| Méthode | Contrat |
|---|---|
| `create(string $apiKey): PlatformInterface` | Une plateforme qui appelle le fournisseur avec cette clé; elle ne garde la clé nulle part ailleurs et ne la journalise jamais |

### `Completions`

`Phil\AiBundle\Application\Port\Completions` représente les modèles. `SymfonyAiCompletions` appelle Symfony AI; un test
répond à sa place.

| Méthode | Contrat |
|---|---|
| `complete(ModelCall $call, ?string $userKey = null): Completion` | La réponse du modèle (texte, données structurées, appels d'outils proposés ou médias) et les jetons consommés, avec la clé de la personne si elle est fournie, sinon avec celle de l'application ; tout échec qu'elle lève est journalisé par `AiTasks`, rendu au quota et relevé sous forme de `TaskFailed` |
| `stream(ModelCall $call, ?string $userKey = null): Generator<int, string, mixed, Completion>` | Le texte par morceaux à mesure que le modèle l'écrit, puis la réponse entière comme valeur de retour du générateur ; un échec en cours de flux est traité comme un échec de `complete()` |

### `UserKeys`

`Phil\AiBundle\Application\Port\UserKeys` donne la clé d'une personne pour une plateforme. `UserSecretsKeys` la lit dans
`phil/user-secrets-bundle` quand ce bundle est enregistré; sinon, c'est `NoUserKeys`.

| Méthode | Contrat |
|---|---|
| `keyFor(string $owner, string $platform, string $purpose): ?string` | La clé, ou `null` quand la personne n'en a aucune pour cette plateforme ou l'a révoquée; le motif va à la piste d'audit |

### `JournalRepository`

`Phil\AiBundle\Domain\Repository\JournalRepository` est le journal de la sorte, des jetons, du coût et du sujet. Il ne
contient jamais d'invite ni de réponse. Il est en Doctrine ou en mémoire, selon `store`. Voir
[Le journal](journal.md).

| Méthode | Contrat |
|---|---|
| `get(string $id): ?JournalEntry` | Une entrée |
| `save(JournalEntry $entry): void` | La garde |
| `ofSubject(Subject $subject, DateTimeImmutable $from, DateTimeImmutable $before): list<JournalEntry>` | Les entrées d'un sujet sur une période |
| `totals(Subject $subject, DateTimeImmutable $from, DateTimeImmutable $before): JournalTotals` | Ce que ces entrées totalisent, additionné par le stockage ; `JournalTotals::fromGroups()` lit les lignes d'une requête groupée |
| `eraseSubject(Subject $subject): int` | Supprime les entrées d'un sujet; retourne leur nombre |

Chaque alias est public et pointe vers le service de l'application quand celle-ci le redéfinit.

## Configuration

| Option | Ce qu'elle étend |
|---|---|
| `platforms` | Les plateformes Symfony AI, chacune avec sa fabrique pour la clé d'une personne et le nom du secret de cette clé |
| `tasks` | Les sortes de tâches : plateforme, modèle, options, jetons à réserver, sortie, structure, outils et finalité |
| `prices`, `currency` | Le coût de chaque modèle, par jeton et par appel, pour le journal et les budgets |
| `key_policy` | Avec quelle clé une tâche s'exécute |
| `usage_meter`, `quota` | Le service de `phil/usage-meter` et les compteurs des quotas, des budgets et du plafond |
| `translation_domain` | Le domaine des invites |
| `store`, `entity_manager` | L'endroit où le journal est gardé |

Voir [Configuration](configuration.md).

## Avis

Les avis sont des traductions du domaine `phil_ai_notice` (`interaction`, `generated.text`, `generated.image`,
`generated.speech`) : une application les reformule, ou ajoute une langue, dans ses propres traductions. Voir
[Transparence et vie privée](transparency.md).

## Module de vie privée

Avec `phil/privacy-bundle`, `AiJournalPrivacy` porte les étiquettes `phil_privacy.exporter` et `phil_privacy.eraser` ;
une application qui garde davantage sur ses tâches enregistre son propre module à côté.

## Invites

Les invites d'une tâche sont des traductions de `translation_domain`, `phil_ai` par défaut. Une application les écrit
dans chaque langue où elle exécute des tâches, comme elle écrit n'importe quel message; voir
[Tâches et suggestions](tasks.md).
