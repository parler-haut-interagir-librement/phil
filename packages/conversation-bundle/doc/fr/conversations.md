<!-- phil-dev source-sha256=bf57bdba6e36d253cdb91b2d4e4d356a441129b7eacd4b12077bc41befac2eb0 -->
# Conversations

## Participants

Un participant est nommé par l'identifiant stable que l'application donne à ses membres : `ParticipantId::fromString()`
accepte de 1 à 190 caractères, sans espaces ni caractères de contrôle, et refuse un identifiant contenant `@` — une
adresse courriel change, et dit qui est la personne. Le même identifiant nomme la personne concernée de
phil/privacy-bundle.

## Démarrer une conversation

`Conversations` est le point d'entrée :

```php
use Phil\ConversationBundle\Application\Conversations;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;

$ada = ParticipantId::fromString('member-7');
$bob = ParticipantId::fromString('member-42');

// Direct: the two share one conversation, started the first time, taken up again afterwards.
$direct = $conversations->startDirect($ada, $bob, 'Is the lamp still for sale?');

// A group, its subject optional.
$group = $conversations->startGroup($ada, [$bob, ParticipantId::fromString('member-9')], 'Friday game', 'Who comes?');
```

Une conversation directe entre deux participants est unique : la personne qui écrit en premier la démarre, et un
`startDirect()` ultérieur, dans un sens ou dans l'autre, y écrit et ramène un participant qui l'avait quittée. Quand les deux
écrivent leur premier message au même instant, le dépôt refuse la seconde conversation, la supprime et `startDirect()`
écrit dans celle que l'autre a démarrée ; un seul `ConversationStarted` est émis. Une conversation de groupe contient de 2 à `limits.group_size` participants, son auteur compris ; les autres sont donnés une
seule fois chacun, l'auteur omis.

Un corps est rogné, ne peut pas être vide ni plus long que `limits.body_length` caractères ; un sujet est rogné, vide
signifie aucun, et il ne peut pas être plus long que `limits.subject_length`. Chaque refus lève `InvalidConversation`.

## Qui peut écrire à qui

Les `ReceptionSettings` de chaque participant disent qui peut démarrer une conversation avec lui — `WhoCanWrite::Everyone`,
la valeur par défaut, `Contacts`, tels que le `ContactRelation` de l'application les connaît, ou `Nobody` — et qui il a
bloqué, jusqu'à 1 000 personnes :

```php
use Phil\ConversationBundle\Application\Reception;
use Phil\ConversationBundle\Domain\Reception\WhoCanWrite;

$reception->allow($bob, WhoCanWrite::Contacts);
$reception->block($bob, $ada);
$reception->unblock($bob, $ada);
```

Un blocage, dans un sens ou dans l'autre, refuse une nouvelle conversation entre les deux et tout message dans leur
conversation directe. Dans un groupe, l'auteur bloqué écrit encore, mais celui qui l'a bloqué n'est pas averti. Un
message que les réglages refusent lève `WritingRefused`, dont le message ne dit pas pourquoi, afin que personne
n'apprenne qu'on l'a bloqué. Les réglages s'appliquent au démarrage d'une conversation et quand un participant fait
entrer quelqu'un dans un groupe ; une conversation déjà en cours n'est pas fermée par un changement de réglages.

## Écrire et lire

```php
$message = $conversations->reply($direct->id, $bob, 'It is!');

$page = $conversations->thread($direct->id, $ada);                  // the latest messages, oldest first
$older = $conversations->thread($direct->id, $ada, 20, $page[0]->sequence());
$conversations->markRead($direct->id, $ada);
```

Seuls les participants actifs d'une conversation la lisent et y écrivent ; toute autre personne, comme une conversation
qui n'existe pas, rencontre `NotAParticipant`, la même pour les deux. Chaque message a une séquence, donnée quand il est
conservé, qui ordonne la conversation et sert de curseur aux pages. Un message dont les données de l'auteur ont été
effacées garde sa place, avec un auteur et un corps nuls.

## La boîte de réception

```php
$inbox = $conversations->inbox($ada);                                // latest message first
$next = $conversations->inbox($ada, 20, end($inbox)->conversation->lastSequence());
$count = $conversations->unreadConversations($ada);
```

Chaque `InboxEntry` contient la conversation, la place du participant dans celle-ci, et le nombre de messages des autres
arrivés depuis sa dernière lecture. Le message d'un participant marque la conversation comme lue jusqu'à lui.

## Sourdine et départ

`mute()` garde la conversation dans la boîte de réception mais n'en notifie rien ; `mute($id, $participant, false)` la
refait entendre. `leave()` la retire de la boîte de réception, et ses nouveaux messages ne sont pas reçus ; une
conversation directe revient quand l'autre participant écrit de nouveau, un groupe non.

## Événements

Chaque conversation démarrée émet `ConversationStarted`, et chaque message conservé `MessageSent`, par le répartiteur
d'événements de l'application. Les deux ne portent que des identifiants, jamais un sujet ni un corps. `MessageSent`
nomme les participants actifs qui reçoivent le message, et parmi eux ceux à notifier : qui n'ont pas mis la
conversation en sourdine et n'ont pas bloqué l'auteur.

```php
use Phil\ConversationBundle\Application\Event\MessageSent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class CountMessages
{
    public function __invoke(MessageSent $event): void
    {
        // $event->conversation, $event->message, $event->sequence, $event->author, $event->recipients, $event->notified
    }
}
```

## Les pages

Une fois les routes importées, le bundle sert, pour le participant connecté :

| Route | Chemin | Ce qu'elle montre |
|---|---|---|
| `phil_conversation_inbox` | `/` | La boîte de réception, son compte de non lus et le lien vers les conversations plus anciennes |
| `phil_conversation_start` | `/new/{participant}` | Le formulaire qui écrit à un membre, sa conversation directe démarrée ou reprise |
| `phil_conversation_thread` | `/{id}` | Une conversation, lue comme elle est montrée, son formulaire de réponse, et les formulaires qui la mettent en sourdine, la quittent et bloquent les autres |
| `phil_conversation_messages` | `/{id}/messages` | Les derniers messages seuls, que le temps réel recharge |
| `phil_conversation_reply` | `/{id}/reply` | Soumise par le formulaire de réponse |
| `phil_conversation_mute` | `/{id}/mute` | Soumise par le formulaire qui met en sourdine |
| `phil_conversation_leave` | `/{id}/leave` | Soumise par le formulaire qui quitte |
| `phil_conversation_settings` | `/settings` | Les réglages de réception, et les formulaires qui les changent et débloquent |

Un lien vers le profil d'un membre lui écrit par `path('phil_conversation_start', {participant: member.id})`. Chaque
formulaire est soumis avec le jeton CSRF sans état de Symfony `phil_conversation` ; une requête que le navigateur dit
provenir d'un autre site est refusée. Un message refusé est dit sur la page, avec le statut 422 ; une conversation à
laquelle le participant ne participe pas est introuvable. Les pages ne sont pas mises en cache. Leurs libellés existent
en anglais et en français, dans le domaine de traduction `phil_conversation`.
