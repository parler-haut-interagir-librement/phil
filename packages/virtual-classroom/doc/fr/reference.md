<!-- phil-dev source-sha256=3bb1877cdf7f2badaffaa920c5fe70a5e061a699bb5be095b8fb6e34185354a2 -->
# Référence

## `Phil\VirtualClassroom`

| Type | Rôle |
|---|---|
| `Contract\VirtualClassroom` | Le port d'un serveur de conférence. |
| `Contract\RoomEvent` | Ce qu'une notification dit qui est arrivé à une salle, et quand. |
| `RoomId` | L'identifiant d'une salle ; `fromString()`, `generate()`, `equals()`. |
| `ParticipantId` | L'identifiant stable d'un participant ; `fromString()`, `tryFrom()`, `equals()`. |
| `Participant` | Un participant et le nom que la salle affiche. |
| `Role` | `Moderator` ou `Attendee`. |
| `RoomSpec` | Ce avec quoi une salle est ouverte. |
| `OpenedRoom` | Une salle que le serveur connaît, avec la clé de sa séance. |
| `Capabilities` | Ce qu'un fournisseur sait faire : `end`, `presences`, `recordings`, `webhooks`. |
| `Presences`, `RoomState` | Qui est dans une salle, et si elle est en cours, en attente ou disparue. |
| `Recording` | Un enregistrement et l'un de ses formats. |
| `Webhook` | Une notification telle qu'elle est parvenue à l'application ; `ENDED`, `EVENTS`, `ACCOUNT`. |
| `Timestamps` | Interne : un instant tel qu'un serveur l'écrit. |

## `Phil\VirtualClassroom\Event`

| Type | Rôle |
|---|---|
| `ParticipantJoined` | Un participant est entré dans la salle. |
| `ParticipantLeft` | Un participant a quitté la salle. |
| `RoomEnded` | La salle s'est terminée. |
| `RecordingReady` | Un enregistrement est prêt, décrit quand la notification porte son adresse. |

## `Phil\VirtualClassroom\Attendance`

| Type | Rôle |
|---|---|
| `Interval` | Un séjour d'un participant. |
| `AttendanceSheet` | `seconds()` et `percent()` d'une séance suivie. |

## `Phil\VirtualClassroom\BigBlueButton`

| Type | Rôle |
|---|---|
| `BigBlueButton` | L'adaptateur ; `signed()` écrit l'adresse d'un appel avec sa somme de contrôle. |
| `ChecksumAlgorithm` | `Sha256`, `Sha384`, `Sha512`. |
| `XmlAnswer` | Interne : une réponse de l'API. |

## `Phil\VirtualClassroom\Jitsi`

| Type | Rôle |
|---|---|
| `Jitsi` | L'adaptateur ; `selfHosted()` et `jaas()`. |

## `Phil\VirtualClassroom\Http` et `Token`

| Type | Rôle |
|---|---|
| `Http\HttpBudget` | Les bornes de temps et de taille d'un appel. |
| `Http\BoundedHttp` | Interne : un GET dans les limites d'un budget. |
| `Token\JsonWebToken` | Interne : signer et vérifier des jetons Web JSON compacts. |

## `Phil\VirtualClassroom\Exception`

`VirtualClassroomException`, `InvalidValue`, `ProviderUnavailable`, `ProviderRefused`, `InvalidResponse`,
`InvalidWebhook`, `Unsupported` ; voyez [Erreurs et budgets](errors.md).
