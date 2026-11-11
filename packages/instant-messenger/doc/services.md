# Services

## `MessengerType`

A service is a case of an enum implementing `MessengerType`:

| Method | Gives |
|---|---|
| `normalize(string $input): ?string` | The canonical handle the input stands for, or `null` when it is not one |
| `display(string $handle): string` | The handle as people read it |
| `link(string $handle): ?string` | The address that opens a conversation, or `null` |

## The international services

| Service | Handle | Read from | Link |
|---|---|---|---|
| `WhatsApp` | A phone number, `+15145550123` | `wa.me/…`, `api.whatsapp.com/send?phone=…` | `https://wa.me/15145550123` |
| `Telegram` | A user name of 5 to 32 letters, digits and `_`, in lowercase | `@name`, `t.me/…`, `telegram.me/…`; not Telegram's own pages, `t.me/joinchat/…`, `t.me/addstickers/…` and the like | `https://t.me/name`, shown `@name` |
| `Signal` | A phone number, or a user name `name.01` | `signal.me/#p/…` | `https://signal.me/#p/+1…` for a number; none for a user name |
| `Messenger` | A Facebook user name | `m.me/…` | `https://m.me/name` |
| `Matrix` | `@user:server` | `matrix.to/#/…` | `https://matrix.to/#/@user:server` |
| `Xmpp` | `user@server`, without `?`, `#` or control characters | `xmpp:…` | `xmpp:user@server`, the user percent-encoded as RFC 5122 writes it |
| `Threema` | An 8-character ID, in capitals | `threema.id/…` | `https://threema.id/ID` |
| `Viber` | A phone number | — | `viber://chat?number=…` |
| `Line` | An ID of 4 to 20 characters; an official account's ID keeps its `@` | `~id`, `@id`, `line.me/ti/p/~…`, `line.me/R/ti/p/~…` and `…/@…`; not a friend code, `line.me/ti/p/AbCd…` | `https://line.me/ti/p/~id`; `https://line.me/R/ti/p/@id` for an official account |
| `Discord` | A user name of 2 to 32 characters | `@name` | none, shown `@name` |
| `WeChat` | An ID of 6 to 20 characters, a letter first | — | none |
| `IMessage` | A phone number, or an email address in lowercase | — | none |
| `Snapchat` | A user name of 3 to 15 letters, digits, `.`, `-` and `_`, a letter first and a letter or digit last | `@name`, `snapchat.com/add/…` | `https://www.snapchat.com/add/name` |
| `Session` | A Session ID: `05` and 64 hexadecimal digits, in lowercase | — | none |

**A phone number is written internationally**, with `+` and the country code; spaces, dots, dashes and parentheses
are forgiven. `phil/phone` reads it: the number must exist in its country's numbering plan and carry no extension,
and it is kept in E.164, `+15145550123`, and shown in the international format, `+1 514-555-0123`. `label()` gives the service's own name, `XMPP`, `iMessage`.

**iMessage has no link**: `sms:` opens the device's text messages, iMessage or not, and Apple publishes no address
of its own.

## Which services are here

A service is in `KnownMessenger` when it is used across countries and names its people itself, or by a phone number
or an email address. Left out:

| Kind | For instance | Why |
|---|---|---|
| Bound to a workspace or a server | Slack, Mattermost, Rocket.Chat, Microsoft Teams | A name means nothing without its workspace |
| A plain phone number or account | SMS, RCS, Google Chat | A telephone or email field, not a service |
| A social network | Instagram, X, Bluesky, Mastodon | A profile, a URL kind rather than a handle |
| Regional | KakaoTalk, Zalo, QQ | The application's own enum, beside `KnownMessenger` |
| Without handles, or closed | SimpleX, Skype | Nothing to store |

## An application's own services

A community forum, a regional network: an enum of the application's, implementing `MessengerType`, read and shown
its own way. Fields and constraints accept it beside `KnownMessenger`.
