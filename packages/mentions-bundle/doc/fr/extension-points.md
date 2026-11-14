<!-- phil-dev source-sha256=b664347cd03af9069b179dffa2522db34290015cab7aff650fc524e80c2f97ea -->
# Points d'extension

Ce qu'une application implémente, configure, remplace ou utilise. Les membres, les sujets et les contextes sont des
références de `phil/resource`.

## Ports écrits par l'application

### `MemberDirectory`

`Phil\MentionsBundle\Application\Contract\MemberDirectory` (`member_directory`, obligatoire) — les membres de
l'application. Chaque méthode répond en une seule requête.

| Méthode | Contrat |
|---|---|
| `resolve(non-empty-list<string> $handles): iterable<Member>` | Les membres de ces identifiants, comparés sans tenir compte de la casse ; les identifiants inconnus sont écartés |
| `members(non-empty-list<ResourceReference> $references): iterable<Member>` | Les membres de ces références ; les membres supprimés sont écartés |
| `search(string $query, int $limit): iterable<Member>` | Les membres qu'une personne peut mentionner et dont l'identifiant ou le nom commence par la requête, les meilleurs d'abord |
| `maySee(ResourceReference $member, ResourceReference $content): bool` | Indique si le membre peut voir le contenu |

`Member` est une référence, un identifiant, un nom, une adresse de profil ou aucune, et l'identifiant auquel vont les
notifications, la référence par défaut. La référence et le destinataire sont des identifiants stables que donne
l'application, `user:42`, jamais des adresses de courriel : un `Member` dont la référence ou le destinataire contient
`@` est refusé avec `InvalidMention`, tout comme une mention enregistrée pour un tel membre ou auteur.

## Ports avec adaptateurs fournis

### `MentionPreferences`

`Phil\MentionsBundle\Application\Contract\MentionPreferences` (`preferences`) — `SettingsMentionPreferences` avec
`phil/settings-bundle`, `AlwaysNotify` sinon.

| Méthode | Contrat |
|---|---|
| `notifies(string $recipient): bool` | Indique si le membre veut être notifié de ses mentions ; rapide |

### `MentionRepository`

`Phil\MentionsBundle\Domain\Contract\MentionRepository` (`store`) — `DoctrineMentionRepository` ou
`InMemoryMentionRepository`.

| Méthode | Contrat |
|---|---|
| `ofSubject(ResourceReference $subject): list<RecordedMention>` | Les plus anciennes d'abord |
| `ofMember(ResourceReference $member, int $limit): list<RecordedMention>` | Les plus récentes d'abord |
| `change(list<RecordedMention> $added, list<RecordedMention> $removed): list<RecordedMention>` | En un seul changement : retire, puis ajoute chaque mention dont le membre n'est pas déjà mentionné par le sujet, en gardant la ligne trouvée ; retourne les mentions ajoutées, dans l'ordre donné |
| `forget(ResourceReference $subject): void` | Chaque mention du sujet |

## Services qu'appelle l'application

| Service | Usage |
|---|---|
| `Mentions` | `inText()`, `inDocument()`, `mentionedIn()`, `mentioning()`, `forget()` |
| `MentionRecorder` | `record()` avec des mentions trouvées ailleurs |
| `TextMentions`, `UjoMentions` | Trouver des mentions sans les enregistrer, pour un aperçu |
| `MentionLinker` | Changer les identifiants en liens hors de Twig |
| `CommentMentions` | Les mentions des commentaires de `phil/comments-bundle` |

## Événements

`MemberMentioned`, sur le répartiteur PSR-14, une fois par membre nouvellement mentionné dans un sujet : voir
[Enregistrement](recording.md#lévénement).

## Configuration

`pattern`, `limits`, `notification_type` et `comments.markdown` — voir [Configuration](configuration.md). Le type de
notification se configure plus en détail sous `phil_notifications.types`.

## Gabarits, styles et traductions

Le bundle n'a aucun gabarit. Les règles propres de l'application sur `.mention` et `.phil-mentions-menu`, ou les jetons
de PHIL UX, remplacent `mentions.css`. Les traductions de l'application remplacent les domaines `phil_notifications`
et `phil_mentions`.
