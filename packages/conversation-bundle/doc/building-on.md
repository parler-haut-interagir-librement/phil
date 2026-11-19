# Building on the bundle

A package built on the conversations — a help desk whose requests are conversations, `phil/live-chat` with visitors —
uses three things the bundle gives it, and decides access itself.

## Open conversations

`startOpen()` starts a group conversation with its author alone: a request to a help desk, a chat with a visitor. No
reception setting applies, since nobody else is in it yet.

```php
$request = $conversations->startOpen(ParticipantId::fromString('visitor-31'), 'Where is my order?', 'Ordered on Monday.');
```

## Joining

`join()` brings a participant into a group conversation, or back into it. Called without `$by`, it is the package's
own decision — an agent taking a request — and no reception setting applies; called with an active participant as
`$by`, the newcomer's settings apply, as when a member adds a friend to a group.

```php
$conversations->join($request->id, ParticipantId::fromString('agent-4'));
$conversations->reply($request->id, ParticipantId::fromString('agent-4'), 'It left the warehouse this morning.');
```

Once in, the agent is a participant like any other: they write, read, mute and leave, and receive the notifications and
Mercure updates of the conversation.

## Reading without being in it

`ConversationReader`, which `Conversations` implements, reads a conversation, its messages by page and its participants
without checking that the reader takes part in it: an agent who reads the requests of their queue before taking one. The
package checks access before it calls it.

```php
use Phil\ConversationBundle\Application\Contract\ConversationReader;

$conversation = $reader->conversation($id);
$messages = $reader->messages($id, 20);
$participants = $reader->participants($id);
```

## Following the messages

`MessageSent` and `ConversationStarted` tell a package what happened, by identifiers: a help desk moves a request back
to its agents when the requester answers, a live chat tells the staff that a visitor wrote.
