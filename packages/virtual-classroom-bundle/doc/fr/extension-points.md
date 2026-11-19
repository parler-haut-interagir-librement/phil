<!-- phil-dev source-sha256=f14d126ddfc4853687db2bc4f07a4e96cd00a9942f7a64ecb82b4642deb0fadd -->
# Points d'extension

Le bundle en est à ses versions `0.x` : les ports, les événements et les services ci-dessous peuvent changer dans une
version mineure, ce que dit le journal des modifications. Ils sont destinés à être stables à partir de `1.0`.

## `CurrentParticipant`

Qui rejoint dans la requête — un `Participant` : l'identifiant stable du compte et le nom qu'affiche la salle —, ou
null ; et le `Role` qu'il prend dans une salle. `SecurityCurrentParticipant` lit l'utilisateur authentifié de Symfony
Security ; nommez un autre service dans `current_participant`.

## `ClassroomStore`

Où les salles, les séjours et les enregistrements sont conservés : `DbalClassroomStore` par défaut,
`InMemoryClassroomStore` pour les tests, ou un service nommé dans `store`. Une implémentation tient les promesses de
`StoreTestCase`, le test commun des magasins : un seul séjour ouvert par participant, une arrivée dans un séjour connu
n'en ouvrant aucun, un départ fermant le plus récent séjour ouvert, un enregistrement une fois par identifiant et par
format.

## `CallbackUrls`

Les adresses de la fin et des événements signés d'une salle. `RoutedCallbackUrls` les génère à partir de la route du
webhook ; une application derrière un mandataire ou sur un autre hôte la remplace en donnant au port un alias vers son
propre service.

## Fournisseurs

Un fournisseur de type `service` est tout service qui implémente `Phil\VirtualClassroom\Contract\VirtualClassroom` :
un autre serveur de conférence, ou un faux dans les tests.

## Événements

Distribués par le répartiteur PSR-14, une fois que le magasin a conservé ce qu'ils disent, avec des identifiants
seulement : `ClassroomOpened`, `ClassroomEnded`, `ParticipantArrived`, `ParticipantDeparted`, `RecordingAvailable` et
`AttendanceUpdated`. Un écouteur lit le reste dans `Classrooms` : le rapport d'une séance, les enregistrements.

## Services

Publics : `Classrooms`, `Providers`, `ClassroomStore`, `CurrentParticipant`, les contrôleurs `JoinController` et
`WebhookController`, et, quand leurs bundles sont activés, `VirtualClassroomActivity`, `CoursesCompletion` et
`ClassroomPrivacy`.
