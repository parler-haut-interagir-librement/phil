# Extension points

The bundle is in its `0.x` releases: the ports, the events and the services below may change in a minor release, said
in the changelog. They are meant to be stable from `1.0`.

## `CurrentParticipant`

Who reads and writes in the request being served, in `Phil\ConversationBundle\Application\Contract`; null when nobody
is signed in, and the pages are then denied. `SecurityCurrentParticipant` reads Symfony Security's user identifier and
refuses it when it holds an `@`; an application whose members sign in with their email address names its own service
under `phil_conversation.current_participant`.

```php
interface CurrentParticipant
{
    public function current(): ?ParticipantId;
}

final readonly class MemberParticipant implements CurrentParticipant
{
    public function __construct(private Security $security) {}

    public function current(): ?ParticipantId
    {
        $user = $this->security->getUser();

        return $user instanceof Member ? ParticipantId::fromString($user->publicId()) : null;
    }
}
```

## `ParticipantDirectory`

The name the pages and the notification labels show for a participant. `IdentifierDirectory` shows the identifier.

```php
interface ParticipantDirectory
{
    public function displayName(ParticipantId $participant): string;
}
```

## `ContactRelation`

Whether two participants are contacts, for those who receive new conversations from their contacts only. `NoContacts`
knows none.

```php
interface ContactRelation
{
    public function areContacts(ParticipantId $one, ParticipantId $other): bool;
}
```

## `MessageSealer`

Seals and opens the subjects and bodies, bound to their context, and names the current master key.
`KeyManagementMessageSealer` uses Symfony's KeyManagement component; an application that aliases `MessageSealer` to its
own service keeps it, and the bundle then never calls the component. A sealer of its own keeps the guarantees of
[Encryption at rest](encryption.md): a text bound to its context, its master key named, a version in its format.

```php
interface MessageSealer
{
    public function seal(#[SensitiveParameter] string $text, string $context): string;

    public function open(string $sealed, string $context): string;

    public function currentKey(): string;
}
```

## `ConversationReader`

The conversations read without the participant check, for the packages built on the bundle; see
[Building on the bundle](building-on.md). `Conversations` implements it, and the container aliases it.

## `ConversationRepository` and `ReceptionRepository`

The stores, in `Phil\ConversationBundle\Domain\Repository`, with their DBAL implementations. Another store implements
both interfaces and is aliased to them; it seals the texts at rest, keeps the moments in UTC and gives each message a
sequence that orders the conversations. When `saveParticipation()` completes a new direct conversation whose two
participants already share one, it removes the new conversation and throws `DirectConversationExists`.

## Events

`ConversationStarted` and `MessageSent`, in `Phil\ConversationBundle\Application\Event`, dispatched through the
application's event dispatcher; see [Conversations](conversations.md#events). Both carry identifiers only.

## Templates and translations

The pages are the templates `@PhilConversation/inbox.html.twig`, `thread.html.twig`, `_messages.html.twig`,
`start.html.twig` and `settings.html.twig`, which an application overrides in `templates/bundles/PhilConversationBundle/`,
and the layout `phil_conversation.layout` names. Their labels are in the translation domain `phil_conversation`.
