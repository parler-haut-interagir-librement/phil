<!-- phil-dev source-sha256=78c81622b5a00bac3cc029926cc8c28c96900a0b17b3ded395bf397a18affaa1 -->
# Bâtir sur le bundle

Un paquet bâti sur les conversations — un service d'assistance dont les demandes sont des conversations,
`phil/live-chat` avec des visiteurs — emploie trois choses que le bundle lui donne, et décide lui-même de l'accès.

## Conversations ouvertes

`startOpen()` démarre une conversation de groupe avec son seul auteur : une demande à un service d'assistance, un
clavardage avec un visiteur. Aucun réglage de réception ne s'applique, puisque personne d'autre n'y est encore.

```php
$request = $conversations->startOpen(ParticipantId::fromString('visitor-31'), 'Where is my order?', 'Ordered on Monday.');
```

## Rejoindre

`join()` fait entrer un participant dans une conversation de groupe, ou l'y ramène. Appelée sans `$by`, c'est la
décision du paquet lui-même — un agent qui prend une demande — et aucun réglage de réception ne s'applique ; appelée
avec un participant actif comme `$by`, les réglages du nouvel arrivant s'appliquent, comme lorsqu'un membre ajoute un
ami à un groupe.

```php
$conversations->join($request->id, ParticipantId::fromString('agent-4'));
$conversations->reply($request->id, ParticipantId::fromString('agent-4'), 'It left the warehouse this morning.');
```

Une fois entré, l'agent est un participant comme les autres : il écrit, lit, met en sourdine et quitte, et reçoit les
notifications et les mises à jour Mercure de la conversation.

## Lire sans en faire partie

`ConversationReader`, que `Conversations` implémente, lit une conversation, ses messages par page et ses participants
sans vérifier que le lecteur y participe : un agent qui lit les demandes de sa file avant d'en prendre une. Le paquet
vérifie l'accès avant de l'appeler.

```php
use Phil\ConversationBundle\Application\Contract\ConversationReader;

$conversation = $reader->conversation($id);
$messages = $reader->messages($id, 20);
$participants = $reader->participants($id);
```

## Suivre les messages

`MessageSent` et `ConversationStarted` informent un paquet de ce qui s'est passé, par des identifiants : un service
d'assistance remet une demande à ses agents quand le demandeur répond, un clavardage en direct avertit le personnel
qu'un visiteur a écrit.
