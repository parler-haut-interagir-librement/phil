<!-- phil-dev source-sha256=8d8a4c6aac2d24d4afa9b5ca80929fb9ab50755d206e6cf47c3a7de5f52745c7 -->
# Référence

## Domaine

| Classe | Rôle |
|---|---|
| `Domain\Model\Room` | Une salle de l'application et sa vie. |
| `Domain\Status\RoomStatus` | `Scheduled`, `Open`, `Ended`. |
| `Domain\Event\ClassroomOpened`, `ClassroomEnded`, `ParticipantArrived`, `ParticipantDeparted`, `RecordingAvailable`, `AttendanceUpdated` | Les événements, avec des identifiants seulement. |
| `Domain\Exception\ClassroomException` | La base des exceptions du bundle : `UnknownRoom`, `RoomClosed`, `UnknownProvider`, `InvalidRoom`. |

## Application

| Classe | Rôle |
|---|---|
| `Application\Classrooms` | Planifier, rejoindre, terminer, synchroniser, recevoir, assiduité, enregistrements. |
| `Application\Providers` | Les serveurs de la configuration, par nom. |
| `Application\WebhookSigner` | La preuve des adresses du webhook. |
| `Application\AttendeeReport` | Ce qu'un participant a suivi d'une séance. |
| `Application\Contract\ClassroomStore`, `CurrentParticipant`, `CallbackUrls` | Les ports. |

## Infrastructure

| Classe | Rôle |
|---|---|
| `Infrastructure\Dbal\DbalClassroomStore`, `ClassroomTables` | Les tables et leur magasin. |
| `Infrastructure\Doctrine\ClassroomSchemaListener` | Les tables dans le schéma que génère l'ORM. |
| `Infrastructure\Memory\InMemoryClassroomStore` | Le magasin des tests. |
| `Infrastructure\Http\JoinController`, `WebhookController` | Le lien d'accès et le webhook. |
| `Infrastructure\Routing\RoutedCallbackUrls` | Les adresses de rappel d'une salle. |
| `Infrastructure\Security\SecurityCurrentParticipant` | L'utilisateur authentifié comme participant. |
| `Infrastructure\Courses\VirtualClassroomActivity`, `CoursesCompletion` | Le type d'activité de phil/courses-bundle et son achèvement. |
| `Infrastructure\Privacy\ClassroomPrivacy` | Les séjours dans les demandes d'accès et d'effacement. |
| `Infrastructure\Console\SyncCommand`, `WebhookUrlCommand` | Les commandes. |
| `Infrastructure\Messenger\SyncRoom`, `SyncRoomHandler` | Le message d'une salle à synchroniser. |
| `Infrastructure\DependencyInjection\VirtualClassroomServices` | Les services, à partir de la configuration. |
| `PhilVirtualClassroomBundle` | Le bundle et son arbre de configuration. |

## Routes

| Nom | Chemin |
|---|---|
| `phil_virtual_classroom_join` | `/{room}/join` |
| `phil_virtual_classroom_room_webhook` | `/webhook/{provider}/{room}/{channel}/{token}` |
| `phil_virtual_classroom_webhook` | `/webhook/{provider}/{token}` |
