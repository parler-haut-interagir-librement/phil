# Configuration

```yaml
# config/packages/phil_conversation.yaml
phil_conversation:
    # The DBAL connection of the tables.
    connection: default

    # How the subjects and bodies are sealed at rest; required.
    encryption:
        # The master key that seals the new texts. The texts sealed under a previous key still open while the key
        # management service holds it.
        key: conversations
        # The service of a client of the KeyManagement component, such as "key_management.vault"; null for its
        # default client.
        kms: ~

    # The CurrentParticipant service naming who reads and writes; Symfony Security's user identifier when null.
    current_participant: ~
    # The ParticipantDirectory service naming the participants on the pages; their identifiers when null.
    directory: ~
    # The ContactRelation service saying who are contacts; nobody when null.
    contacts: ~

    # The Twig template the pages extend, with the blocks "title" and "main".
    layout: '@PhilConversation/layout.html.twig'
    # Where the recipes of the kit are, under the components directory; the Toolkit's subdirectory when null.
    kit_subdirectory: ~

    limits:
        body_length: 10000   # characters a message holds at most, up to 1 000 000
        subject_length: 200  # characters a subject holds at most, up to 1 000
        group_size: 50       # participants a group holds at most, its author included, 2 to 1 000
        page_size: 50        # messages or conversations a page shows, up to 200

    # The notification of each new message, with phil/notifications-bundle registered.
    notifications:
        enabled: true
        type: phil_conversation.message

    # The Mercure update that tells the open pages a message came.
    realtime:
        enabled: false
        hub: Symfony\Component\Mercure\HubInterface
        topic_prefix: 'urn:phil:conversation:'
```

## `encryption`

Required: there is no way to keep the conversations in the clear. `key` names the master key the new texts are sealed
under; to change it, see [the rotation](encryption.md#rotating-the-master-key). `kms` names the service of a client of
the KeyManagement component; the container stops when it is not defined.

## `current_participant`

Without a service of its own, the bundle names the participant by the identifier of Symfony Security's user, and
refuses it when it holds an `@`: an application whose members sign in with their email address names its own
`CurrentParticipant`, which gives their stable identifier. See [Extension points](extension-points.md#currentparticipant).

## `directory` and `contacts`

`directory` names the members on the pages: their display name rather than their identifier. `contacts` tells which
members are contacts, for those who let only their contacts write to them; without it, such a member receives no new
conversation.

## `layout`

The pages extend this template and fill its blocks `title` and `main`. The default is a bare HTML page; an application
gives its own layout, which defines both blocks.

## `limits`

A message longer than `body_length` or a subject longer than `subject_length`, counted in characters, is refused; a
group may not hold more than `group_size` participants; a page of a conversation or of the inbox shows `page_size`
lines, and a caller that asks for more gets that many.

## `notifications`

With phil/notifications-bundle registered, the type is declared to it, with the channels `site` and `email` and a
collapse of the unread notifications about a same conversation, and each new message is notified. `enabled: false`
turns it off. See [Notifications](integrations.md#notifications).

## `realtime`

With MercureBundle, `enabled: true` publishes an update for each new message on each participant's own topic,
`topic_prefix` followed by their identifier, and gives the page of a conversation the Stimulus controller that loads
the new messages. The container stops when Mercure is not installed. See [Real time](integrations.md#real-time).
