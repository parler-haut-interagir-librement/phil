<!-- phil-dev source-sha256=a225d680d2a497a86d7fddaa316d6d33a923dbe44eba056730d1b2fb1a74b46f -->
# Salles

`Phil\VirtualClassroomBundle\Application\Classrooms` est le service public des salles.

## Planifier

```php
$room = $classrooms->schedule(
    'Algebra, week 3',        // 2 to 64 characters
    provider: 'main',         // the default provider when null
    record: true,
    maxMinutes: 90,           // 1 to 1440, or the server's limit
    owner: 'workshop:42',     // what the room belongs to
    id: null,                 // a RoomId, drawn when null
);
```

Une salle planifiée est conservée, et aucun serveur n'est appelé. Son **propriétaire** est ce à quoi l'application dit
qu'elle appartient, de 1 à 190 lettres, chiffres, `_`, `.`, `:`, `/` ou `-` : `ofOwner()` trouve la plus récente salle
d'un propriétaire. Le type d'activité de phil/courses-bundle possède ses salles sous la forme
`phil_courses:<activity>`.

## Rejoindre

Les participants suivent le lien d'accès du bundle, la route `phil_virtual_classroom_join` :
`/virtual-classroom/{room}/join`. `JoinController` nomme le participant de la requête par `CurrentParticipant`, demande
son rôle, et répond par une redirection 303 vers le lien du serveur, avec `Cache-Control: no-store, private` et
`Referrer-Policy: no-referrer`.

| Réponse | Quand |
|---|---|
| 303 | La salle s'est ouverte, ou l'était ; le lien du rôle du participant. |
| 403 | Personne de la requête ne peut rejoindre. |
| 404 | Aucune salle n'a cet identifiant. |
| 410 | La salle est terminée. |
| 503, `Retry-After: 30` | Le serveur est injoignable, refuse la salle, ou répond ce qui est illisible. |

`Classrooms::joinUrl()` écrit le lien lui-même : il ouvre la salle sur son serveur — chaque fois, ce à quoi une salle en
cours répond de nouveau avec la clé de sa séance —, avec les adresses de sa fin et de ses événements signés, la note
ouverte à la première arrivée, et distribue `ClassroomOpened` une seule fois.

`SecurityCurrentParticipant`, la valeur par défaut, nomme l'utilisateur authentifié par son identifiant d'utilisateur —
aussi le nom qu'affiche la salle — et en fait un modérateur quand `isGranted(moderator_role, $room)`. Un identifiant
d'utilisateur qui est une adresse courriel ne nomme personne : une application dont les utilisateurs se connectent par
courriel règle `current_participant` sur un service à elle, qui rend l'identifiant stable du compte et son nom
d'affichage.

## Terminer

`end()` termine la salle sur son serveur quand le serveur le peut et que la salle a été ouverte, puis la note terminée,
ferme les séjours encore ouverts à ce moment, et distribue `ClassroomEnded` et `AttendanceUpdated`. Une salle terminée
ne laisse plus entrer personne ; la terminer deux fois ne fait rien. Une salle se termine aussi quand son serveur le
dit — par notification, ou quand `sync()` la trouve disparue.

## Synchroniser

`sync()` demande au serveur d'une salle ouverte qui s'y trouve, quand le serveur peut le dire : un participant vu pour la
première fois arrive à ce moment, un participant qu'on ne voit plus repart, une salle que le serveur ne connaît plus
se termine. Pour une salle enregistrée, elle liste les enregistrements et conserve les nouveaux.
`phil:virtual-classroom:sync` synchronise les salles ouvertes, pour les serveurs qui n'envoient aucune notification ou
pour compenser une notification perdue.
