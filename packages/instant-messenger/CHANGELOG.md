# Changelog

All notable changes to `phil/instant-messenger` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `MessengerType`, `KnownMessenger`, `Handle`, `Messengers`; `InstantMessengerException` and `InvalidHandle`.
  `Messengers::detect()` reads the input once, and a service parses a link only when it publishes one and the input
  has a dot. `Handle::fromCanonical()` takes back a handle kept in its canonical form without reading it again, so a
  phone number a later import of the numbering plans does not know still loads.
- Phone numbers read by `phil/phone`: in their international form, in their country's plan, without an extension;
  shown in the international format.
- Telegram's own pages, `t.me/joinchat/…`, `t.me/addstickers/…`, `t.me/proxy` and the like, are not user names.
- LINE official accounts: `@id`, and the links `line.me/R/ti/p/@id`, kept with their `@` and linked as
  `https://line.me/R/ti/p/@id`; the links `line.me/R/ti/p/~id` are read. A LINE friend code, `line.me/ti/p/AbCd…`,
  is not a LINE ID.
- XMPP: an address with `?`, `#` or a control character in its user is refused, and `link()` percent-encodes the
  user as RFC 5122 writes it, so an `xmpp:` link opens a conversation with the address shown, never another.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
