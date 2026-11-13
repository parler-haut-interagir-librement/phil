# Changelog

All notable changes to `phil/embed-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- Embeds declared in the configuration: their allowed origins, their themes, their default theme, the lifetime of their
  tokens.
- `Origin`: an origin read by `phil/url`, without path, query nor fragment, `localhost` only when allowed.
- `EmbedTokenCodec`: tokens `pe1.hs256.<key>.<claims>.<signature>`, the format and the algorithm named and signed, an
  ordered list of keys — the first signs, all verify —, bounded and compared in constant time.
- `EmbedTokens`: a token issued for one origin, read back into the context of a request, the theme of the page, of the
  token or by default.
- `EmbedRequestListener`: the page of an embed, by its route's `_embed` default or `#[Embeddable]`, framed by the token's
  origin alone, by none for a token that is not valid, untouched without one.
- `FramingPolicy`, with `BrowserSecurityFraming` through `phil/browser-security-bundle`, chosen once every bundle is
  loaded, and `HeaderFraming` in a header of its own.
- `EmbedMessage`: the frame protocol's reference, its fixtures shared with the JavaScript.
- `<phil-embed>`: the frame at the height of its content, the theme sent to it, messages read from its frame and its
  origin alone, the link kept without JavaScript or when the frame is refused.
- The `frame` controller: readiness, height and theme inside the frame, posted to and read from the token's origin
  alone.
- `EmbedCodes`, `phil_embed_code()` and `phil:embed:token`: the code a partner pastes, and a token from the console.
- `EmbedScriptController`: the element and the protocol's module under stable addresses, readable across origins.
- `RemoteCommentThreads`: the port of comments carried by another site's forum.
- Fuzz targets for the token decoder and the frame protocol.
- The Flex recipe in `recipe/0.1`: the bundle, a key from the environment and the script's route.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
