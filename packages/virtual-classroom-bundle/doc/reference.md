# Reference

## Domain

| Class | Role |
|---|---|
| `Domain\Model\Room` | A room of the application and its life. |
| `Domain\Status\RoomStatus` | `Scheduled`, `Open`, `Ended`. |
| `Domain\Event\ClassroomOpened`, `ClassroomEnded`, `ParticipantArrived`, `ParticipantDeparted`, `RecordingAvailable`, `AttendanceUpdated` | The events, identifiers only. |
| `Domain\Exception\ClassroomException` | The base of the bundle's exceptions: `UnknownRoom`, `RoomClosed`, `UnknownProvider`, `InvalidRoom`. |

## Application

| Class | Role |
|---|---|
| `Application\Classrooms` | Schedule, join, end, sync, receive, attendance, recordings. |
| `Application\Providers` | The servers of the configuration, by name. |
| `Application\WebhookSigner` | The proof of the webhook addresses. |
| `Application\AttendeeReport` | What a participant attended of a session. |
| `Application\Contract\ClassroomStore`, `CurrentParticipant`, `CallbackUrls` | The ports. |

## Infrastructure

| Class | Role |
|---|---|
| `Infrastructure\Dbal\DbalClassroomStore`, `ClassroomTables` | The tables and their store. |
| `Infrastructure\Doctrine\ClassroomSchemaListener` | The tables in the schema the ORM generates. |
| `Infrastructure\Memory\InMemoryClassroomStore` | The store of the tests. |
| `Infrastructure\Http\JoinController`, `WebhookController` | The join link and the webhook. |
| `Infrastructure\Routing\RoutedCallbackUrls` | The callback addresses of a room. |
| `Infrastructure\Security\SecurityCurrentParticipant` | The authenticated user as the participant. |
| `Infrastructure\Courses\VirtualClassroomActivity`, `CoursesCompletion` | The activity type of phil/courses-bundle and its completion. |
| `Infrastructure\Privacy\ClassroomPrivacy` | The stays in the access and erasure requests. |
| `Infrastructure\Console\SyncCommand`, `WebhookUrlCommand` | The commands. |
| `Infrastructure\Messenger\SyncRoom`, `SyncRoomHandler` | The message of a room to sync. |
| `Infrastructure\DependencyInjection\VirtualClassroomServices` | The services, from the configuration. |
| `PhilVirtualClassroomBundle` | The bundle and its configuration tree. |

## Routes

| Name | Path |
|---|---|
| `phil_virtual_classroom_join` | `/{room}/join` |
| `phil_virtual_classroom_room_webhook` | `/webhook/{provider}/{room}/{channel}/{token}` |
| `phil_virtual_classroom_webhook` | `/webhook/{provider}/{token}` |
