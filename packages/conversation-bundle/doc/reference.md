# Reference

## Bundle

| Class or file | Role |
|---|---|
| `PhilConversationBundle` | The bundle: its configuration `phil_conversation`, the notification type declared to phil/notifications-bundle, the stateless CSRF token, the controller served by AssetMapper |
| `config/routes.php` | The routes of the pages |
| `recipe/0.1` | The Flex recipe: the bundle, its configuration and its routes |

## Domain

| Class | Role |
|---|---|
| `Domain\ValueObject\ParticipantId` | A participant's stable identifier, 1 to 190 characters, never with `@` |
| `Domain\ValueObject\ConversationId`, `MessageId` | A UUID, version 7 when the bundle makes it |
| `Domain\ValueObject\InboxEntry` | A line of the inbox: the conversation, the participation, the unread count |
| `Domain\Model\Conversation` | A conversation: its kind, subject, start, and last message |
| `Domain\Model\Message` | A message: its author and body, null once erased, its moment and sequence |
| `Domain\Model\Participation` | A participant's place in a conversation: reading, muting, departure |
| `Domain\Model\ReceptionSettings` | Who may start a conversation with a participant, and whom they blocked |
| `Domain\Conversation\Kind` | `Direct` or `Group` |
| `Domain\Reception\WhoCanWrite` | `Everyone`, `Contacts` or `Nobody` |
| `Domain\Repository\ConversationRepository`, `ReceptionRepository` | The stores |
| `Domain\Exception\ConversationException` | What every failure of the domain is |
| `Domain\Exception\InvalidParticipant`, `InvalidConversation` | An identifier, a text or a group refused |
| `Domain\Exception\NotAParticipant` | A conversation read or written by someone outside it, or that does not exist |
| `Domain\Exception\WritingRefused` | A message the reception settings refuse |
| `Domain\Exception\DirectConversationExists` | A direct conversation started while its two participants already share one; `Conversations` catches it and writes in that one |
| `Domain\Exception\UnreadableMessage` | A sealed text that does not open |

## Application

| Class | Role |
|---|---|
| `Application\Conversations` | The entry point: start, reply, join, open, thread, mark read, mute, leave, inbox, unread; implements `ConversationReader` |
| `Application\Reception` | The reception settings, and whether one may start a conversation with another |
| `Application\ConversationLimits` | The bounds of a message, a subject, a group and a page |
| `Application\Contract\CurrentParticipant` | Who reads and writes in the request |
| `Application\Contract\ParticipantDirectory` | The name of a participant on the pages |
| `Application\Contract\ContactRelation` | Whether two participants are contacts |
| `Application\Contract\MessageSealer` | Seals and opens the texts at rest |
| `Application\Contract\ConversationReader` | The conversations read without the participant check |
| `Application\IdentifierDirectory`, `NoContacts` | The defaults of the directory and of the contacts |
| `Application\Event\ConversationStarted`, `MessageSent` | The events, by identifiers |

## Infrastructure

| Class | Role |
|---|---|
| `Infrastructure\KeyManagement\KeyManagementMessageSealer` | The sealer over Symfony's KeyManagement component, `pcv1.a256gcm.` |
| `Infrastructure\Doctrine\ConversationTables` | The five tables, added to a `Schema` |
| `Infrastructure\Doctrine\DbalConversationRepository`, `DbalReceptionRepository` | The DBAL stores |
| `Infrastructure\Doctrine\SchemaListener` | Adds the tables to the schema the ORM generates |
| `Infrastructure\Doctrine\KeyRotation` | Seals the texts of a previous master key again |
| `Infrastructure\Console\RotateKeysCommand` | `phil:conversation:rotate-keys` |
| `Infrastructure\Http\ConversationController` | The pages |
| `Infrastructure\Http\ConversationRequest` | The stateless CSRF check of the forms |
| `Infrastructure\Twig\ConversationExtension` | `phil_conversation_kit()` and `phil_conversation_name()` |
| `Infrastructure\Twig\KitComponents` | The names of the recipes of the kit |
| `Infrastructure\Security\SecurityCurrentParticipant` | The participant from Symfony Security's user |
| `Infrastructure\Notifications\NotifyParticipants` | Notifies the recipients of a message |
| `Infrastructure\Notifications\ConversationSubjects` | Names a notified conversation when it is shown |
| `Infrastructure\Mercure\MercureMessagePublisher` | Publishes the update of a message |
| `Infrastructure\Privacy\ConversationPrivacy` | The export and the erasure |
| `Infrastructure\DependencyInjection\ConversationServices` | The services of the configuration |
| `Infrastructure\DependencyInjection\EncryptionPass` | Stops the container without the key management client |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Reads the components subdirectory of the Toolkit |

## Templates, translations and assets

| File | Role |
|---|---|
| `templates/layout.html.twig` | The default layout, with the blocks `title` and `main` |
| `templates/inbox.html.twig`, `thread.html.twig`, `_messages.html.twig`, `start.html.twig`, `settings.html.twig` | The pages |
| `templates/_realtime.html.twig` | The attributes of the controller of the real time |
| `translations/phil_conversation.en.php`, `phil_conversation.fr.php` | The labels |
| `assets/src/thread_controller.js` | The Stimulus controller that loads the new messages |
