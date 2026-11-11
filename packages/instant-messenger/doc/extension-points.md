# Extension points

The library is plain PHP and declares no service. What an application adds is messaging services.

## `MessengerType`

`Phil\InstantMessenger\MessengerType` — a service is a case of an enum implementing it. See [Services](services.md).

| Method | Contract |
|---|---|
| `normalize(string $input): ?string` | The canonical handle the input stands for — a name, an address, a phone number in the service's form — or `null` when it is not one; never throws |
| `display(string $handle): string` | The canonical handle as people read it |
| `link(string $handle): ?string` | The address that opens a conversation, or `null` when the service has none |

A community forum, a regional network: an enum of the application's own, read and shown its own way, accepted beside
`KnownMessenger`.

## Detection

| Point | How |
|---|---|
| Which services are accepted | The `EnumSet`s given to `Messengers::detect()` and `Messengers::handle()` |
| Which service wins | The order of the sets, then the declaration order of each enum's cases |
| A handle already canonical | `Handle::fromCanonical()`, for values read back from storage |

A service is kept by its enum class and case wherever a handle is stored with it: renaming or removing a case the
data still name is a migration of the data.
