<!-- phil-dev source-sha256=44c234ca861d13b563bfe524c879853e49a0cfd5ffdf9288c92b2c6e682ceae8 -->
# Points d'extension

Le bundle est dans ses versions `0.x` : les ports, les événements et les services ci-dessous peuvent changer dans une
version mineure, ce que dit le journal des modifications. Ils sont destinés à être stables à partir de la version `1.0`.

## `CurrentParticipant`

Qui lit et écrit dans la requête servie, dans `Phil\ConversationBundle\Application\Contract` ; nul quand personne n'est
connecté, et les pages sont alors refusées. `SecurityCurrentParticipant` lit l'identifiant de l'utilisateur de Symfony
Security et le refuse quand il contient un `@` ; une application dont les membres se connectent avec leur adresse
courriel nomme son propre service sous `phil_conversation.current_participant`.

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

Le nom que les pages et les libellés de notification montrent pour un participant. `IdentifierDirectory` montre
l'identifiant.

```php
interface ParticipantDirectory
{
    public function displayName(ParticipantId $participant): string;
}
```

## `ContactRelation`

Si deux participants sont des contacts, pour ceux qui ne reçoivent de nouvelles conversations que de leurs contacts.
`NoContacts` n'en connaît aucun.

```php
interface ContactRelation
{
    public function areContacts(ParticipantId $one, ParticipantId $other): bool;
}
```

## `MessageSealer`

Scelle et ouvre les sujets et les corps, liés à leur contexte, et nomme la clé maîtresse courante.
`KeyManagementMessageSealer` emploie le composant KeyManagement de Symfony ; une application qui fait de `MessageSealer`
un alias de son propre service le garde, et le bundle n'appelle alors jamais le composant. Un sceau maison garde les
garanties du [Chiffrement au repos](encryption.md) : un texte lié à son contexte, sa clé maîtresse nommée, une version
dans son format.

```php
interface MessageSealer
{
    public function seal(#[SensitiveParameter] string $text, string $context): string;

    public function open(string $sealed, string $context): string;

    public function currentKey(): string;
}
```

## `ConversationReader`

Les conversations lues sans la vérification du participant, pour les paquets bâtis sur le bundle ; voyez
[Bâtir sur le bundle](building-on.md). `Conversations` l'implémente, et le conteneur en fait un alias.

## `ConversationRepository` et `ReceptionRepository`

Les dépôts, dans `Phil\ConversationBundle\Domain\Repository`, avec leurs implémentations DBAL. Un autre dépôt
implémente les deux interfaces et en reçoit l'alias ; il scelle les textes au repos, garde les instants en UTC et donne
à chaque message une séquence qui ordonne les conversations. Quand `saveParticipation()` achève une nouvelle
conversation directe dont les deux participants en partagent déjà une, il supprime la nouvelle conversation et lève
`DirectConversationExists`.

## Événements

`ConversationStarted` et `MessageSent`, dans `Phil\ConversationBundle\Application\Event`, émis par le répartiteur
d'événements de l'application ; voyez [Conversations](conversations.md#événements). Les deux ne portent que des
identifiants.

## Gabarits et traductions

Les pages sont les gabarits `@PhilConversation/inbox.html.twig`, `thread.html.twig`, `_messages.html.twig`,
`start.html.twig` et `settings.html.twig`, qu'une application remplace dans `templates/bundles/PhilConversationBundle/`,
et la mise en page que nomme `phil_conversation.layout`. Leurs libellés sont dans le domaine de traduction
`phil_conversation`.
