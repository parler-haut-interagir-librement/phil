# Notifications, real time and privacy

## Notifications

With phil/notifications-bundle registered, the bundle declares the notification type `phil_conversation.message`, or
the one `notifications.type` names, with the channels `site` and `email`, the unread notifications about a same
conversation collapsed into one. Each message kept is notified to its recipients who did not mute the conversation nor
block the author, the author as the actor.

The notification carries the conversation and the message identifiers, never the body. When it is shown,
`ConversationSubjects` names the conversation — its subject, or the names of its active participants — and links to its
page when the bundle's routes are imported; a conversation erased since has no label. The titles and bodies of the type
are the application's translations, `phil_conversation.message.title` and `phil_conversation.message.body`, as for any
type of the notifications bundle.

## Real time

With MercureBundle and `realtime.enabled: true`, each message kept is published on a Mercure hub as one private update,
on the topics of its author and of each active recipient — `urn:phil:conversation:<identifier>`, or `topic_prefix`
followed by the identifier:

```json
{"conversation": "0192f0c1-5c5e-7a3b-9e1d-0123456789ab", "message": "0192f0c1-6a00-7b3b-9e1d-0123456789ab", "sequence": 42}
```

The update names the message, never its body. The page of a conversation subscribes to the reader's own topic, through
the function `mercure()` of MercureBundle, which also gives the browser the cookie that authorizes it; the Stimulus
controller `phil--conversation-bundle--thread` then loads the latest messages from the bundle's page, which checks
again that the reader takes part in the conversation, when an update about this conversation comes. An update about
another conversation is left alone, and a failed load leaves the messages as they were.

The application authorizes each participant to subscribe to their own topic only. A hub that cannot be reached — or
FrankenPHP's built-in hub outside a request it serves, in a command or a Messenger consumer — is logged as a warning and
does not fail the message: it is kept, and the page shows it when it is loaded again. A worker that writes messages
publishes through a hub with a URL of its own.

## Privacy

With phil/privacy-bundle installed, `ConversationPrivacy` takes part in its access and erasure requests, through the
interfaces of `Phil\PrivacyBundle\Application\Contract`, the data subject being the participant's identifier. A data
subject that cannot be a participant, an email address, holds nothing here.

The export, in the module `conversations`, gives:

| Key | What it holds |
|---|---|
| `conversations` | Each conversation the person took part in: its identifier, kind and subject, when they joined and left, whether they muted it |
| `messages` | Each message they wrote, opened: its conversation, identifier, moment and body |
| `reception` | Whom they let write to them, and whom they blocked |

The erasure wipes the body and the author of each of their messages — the others keep the conversation, the message
shown as erased —, removes their participations, their settings and their blocks, their identifier from the blocks of
others, and the hash of the pair of each direct conversation of theirs, and removes the conversations left without
anyone with their messages. Its report says `Redacted` for `phil_conversation_message` and `Deleted` for the
participants, the reception settings and the blocks.
