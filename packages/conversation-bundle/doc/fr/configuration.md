<!-- phil-dev source-sha256=5b5e20cba66f34017782a5e660212c5053f384a1dfc0ba30e494a96093e7ffa1 -->
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

Obligatoire : il n'y a aucun moyen de garder les conversations en clair. `key` nomme la clé maîtresse sous laquelle les
nouveaux textes sont scellés ; pour la changer, voyez [la rotation](encryption.md#rotation-de-la-clé-maîtresse). `kms`
nomme le service d'un client du composant KeyManagement ; le conteneur s'arrête quand il n'est pas défini.

## `current_participant`

Sans service qui lui soit propre, le bundle nomme le participant par l'identifiant de l'utilisateur de Symfony
Security, et le refuse quand il contient un `@` : une application dont les membres se connectent avec leur adresse
courriel nomme son propre `CurrentParticipant`, qui donne leur identifiant stable. Voyez
[Points d'extension](extension-points.md#currentparticipant).

## `directory` et `contacts`

`directory` nomme les membres sur les pages : leur nom d'affichage plutôt que leur identifiant. `contacts` indique
quels membres sont des contacts, pour ceux qui ne laissent que leurs contacts leur écrire ; sans lui, un tel membre ne
reçoit aucune nouvelle conversation.

## `layout`

Les pages étendent ce gabarit et remplissent ses blocs `title` et `main`. La valeur par défaut est une page HTML nue ;
une application donne sa propre mise en page, qui définit les deux blocs.

## `limits`

Un message plus long que `body_length` ou un sujet plus long que `subject_length`, comptés en caractères, est refusé ;
un groupe ne peut pas contenir plus de `group_size` participants ; une page d'une conversation ou de la boîte de
réception montre `page_size` lignes, et un appelant qui en demande plus en reçoit autant.

## `notifications`

Avec phil/notifications-bundle enregistré, le type lui est déclaré, avec les canaux `site` et `email` et un
regroupement des notifications non lues d'une même conversation, et chaque nouveau message est notifié.
`enabled: false` le désactive. Voyez [Notifications](integrations.md#notifications).

## `realtime`

Avec MercureBundle, `enabled: true` publie une mise à jour pour chaque nouveau message sur le sujet propre à chaque
participant, `topic_prefix` suivi de son identifiant, et donne à la page d'une conversation le contrôleur Stimulus qui
charge les nouveaux messages. Le conteneur s'arrête quand Mercure n'est pas installé. Voyez
[Temps réel](integrations.md#temps-réel).
