# phil/instant-messenger

**Messaging handles read, shown and linked per service: WhatsApp, Telegram, Signal, Matrix and more.**

`phil/instant-messenger` reads a messaging handle as people type it, or from the service's own link, and keeps it
canonical in a `Handle`. Messaging services are enum cases implementing `MessengerType`, and each reads, shows and
links its own handles: a Telegram user name, a WhatsApp phone number, a Matrix ID, an iMessage email address.
`KnownMessenger` holds 14 international services, and `Messengers` detects the service of a handle among the enum
sets a field accepts. **It does not** send messages, check that an account exists, or hold the services of a region.

It is for applications where people leave a way to reach them: a member profile, a shop's contact details, an
organiser's page. A region's own services belong to the application as its own enum, accepted next to
`KnownMessenger`. Handles collide: a user name may be read by Telegram, Line and Discord alike, and a phone number by
WhatsApp, Signal, Viber and iMessage. Detection is reliable for links and for distinctive forms such as a Matrix ID;
elsewhere, ask the person for the service.

`phil/instant-messenger` is a plain PHP library on `phil/enums`, `phil/phone` and `phil/url`. `phil/phone` reads the
phone numbers: each must exist in its country's plan, is kept in E.164 and is shown in the international format. In a
Symfony application, reach for `phil/instant-messenger-bundle`, whose form field asks for the service and the handle.
The design follows `phil/url`: a service is any case of any enum that knows its own handles, and a `Handle` is a
service with a canonical value.

## Status

| Part | State |
|---|---|
| `MessengerType`, and `KnownMessenger`: 14 international services | Implemented and tested |
| Handles read from what people type and from the services' links | Implemented and tested |
| `Handle`, and `Messengers`: detecting the service among enum sets | Implemented and tested |
| Phone numbers read by `phil/phone`, in their country's plan, shown in the international format | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Services](doc/services.md) | `MessengerType`, the international services and their handles, an application's own. |
| [Handles](doc/handles.md) | `Handle`, detection among enum sets, and when to ask for the service instead. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests and mutation bar. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/instant-messenger
```

```php
use Phil\InstantMessenger\Handle;
use Phil\InstantMessenger\KnownMessenger;

$handle = Handle::of(KnownMessenger::Telegram, 'https://t.me/AdaLove');
$handle->value;      // "adalove"
$handle->display();  // "@adalove"
$handle->link();     // "https://t.me/adalove"
```

## Requirements

PHP 8.5 or later with `ext-mbstring`; `phil/enums` 0.3, `phil/phone` 0.1 and `phil/url` 0.1.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
