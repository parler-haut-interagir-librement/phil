# Conversations

## Participants

A participant is named by the stable identifier the application gives its members: `ParticipantId::fromString()`
takes 1 to 190 characters, without spaces nor control characters, and refuses an identifier with `@` — an email address
changes, and says who the person is. The same identifier names the data subject of phil/privacy-bundle.

## Starting a conversation

`Conversations` is the entry point:

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

A direct conversation between two participants is unique: whoever writes first starts it, and a later
`startDirect()`, either way, writes in it and brings back a participant who left. When both write their first message
at once, the store refuses the second conversation, removes it and `startDirect()` writes in the one the other started;
one `ConversationStarted` is dispatched. A group conversation holds 2 to
`limits.group_size` participants, its author included; the others are given once each, the author left out.

A body is trimmed and may not be empty nor longer than `limits.body_length` characters; a subject is trimmed, empty
means none, and may not be longer than `limits.subject_length`. Each refusal throws `InvalidConversation`.

## Who may write to whom

Each participant's `ReceptionSettings` say who may start a conversation with them — `WhoCanWrite::Everyone`, the
default, `Contacts`, as the application's `ContactRelation` knows them, or `Nobody` — and whom they blocked, up to
1 000 people:

```php
use Phil\ConversationBundle\Application\Reception;
use Phil\ConversationBundle\Domain\Reception\WhoCanWrite;

$reception->allow($bob, WhoCanWrite::Contacts);
$reception->block($bob, $ada);
$reception->unblock($bob, $ada);
```

A block, either way, refuses a new conversation between the two and any message in their direct conversation. In a
group, the blocked author still writes, but the one who blocked them is not notified. A message the settings refuse
throws `WritingRefused`, whose message does not say why, so that nobody learns they are blocked. The settings apply
when a conversation starts and when a participant brings someone into a group; a conversation already going on is not
closed by a change of settings.

## Writing and reading

```php
$message = $conversations->reply($direct->id, $bob, 'It is!');

$page = $conversations->thread($direct->id, $ada);                  // the latest messages, oldest first
$older = $conversations->thread($direct->id, $ada, 20, $page[0]->sequence());
$conversations->markRead($direct->id, $ada);
```

Only the active participants of a conversation read and write it; anyone else, and a conversation that does not exist,
meet `NotAParticipant`, the same for both. Each message has a sequence, given when it is kept, which orders the
conversation and serves as the cursor of the pages. A message whose author's data was erased keeps its place with a
null author and body.

## The inbox

```php
$inbox = $conversations->inbox($ada);                                // latest message first
$next = $conversations->inbox($ada, 20, end($inbox)->conversation->lastSequence());
$count = $conversations->unreadConversations($ada);
```

Each `InboxEntry` holds the conversation, the participant's place in it, and how many messages of the others came
since they last read it. A participant's own message reads the conversation up to it.

## Muting and leaving

`mute()` keeps the conversation in the inbox but notifies nothing of it; `mute($id, $participant, false)` lets it be
heard again. `leave()` takes it out of the inbox, and its new messages are not received; a direct conversation comes
back when the other participant writes again, a group does not.

## Events

Each conversation started dispatches `ConversationStarted`, and each message kept `MessageSent`, through the
application's event dispatcher. Both carry identifiers only, never a subject nor a body. `MessageSent` names the active
participants who receive the message, and among them those to notify: not having muted the conversation, not having
blocked the author.

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

## The pages

With the routes imported, the bundle serves, for the participant signed in:

| Route | Path | What it shows |
|---|---|---|
| `phil_conversation_inbox` | `/` | The inbox, its unread count and the link to the older conversations |
| `phil_conversation_start` | `/new/{participant}` | The form that writes to a member, their direct conversation started or taken up again |
| `phil_conversation_thread` | `/{id}` | A conversation, read as it is shown, its reply form, and the forms that mute or leave it and block the others |
| `phil_conversation_messages` | `/{id}/messages` | The latest messages alone, which the real time loads again |
| `phil_conversation_reply` | `/{id}/reply` | Posted by the reply form |
| `phil_conversation_mute` | `/{id}/mute` | Posted by the form that mutes |
| `phil_conversation_leave` | `/{id}/leave` | Posted by the form that leaves |
| `phil_conversation_settings` | `/settings` | The reception settings, and the forms that change them and unblock |

A link to a member's profile writes to them through `path('phil_conversation_start', {participant: member.id})`. Every
form is posted with Symfony's stateless CSRF token `phil_conversation`; a request a browser says comes from another
site is refused. A refused message is said on the page, with the status 422; a conversation the participant does not
take part in is not found. The pages are not cached. Their labels come in English and French, in the translation domain
`phil_conversation`.
