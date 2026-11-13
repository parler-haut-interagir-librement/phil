# phil/embed-bundle

**Features of a Symfony site that other sites embed, framed by the origins it allows and nobody else.**

phil/embed-bundle turns a page of the site — a calendar, a form, a list of events — into something another site can
paste into its own pages. The site gives that other site a code: a `<phil-embed>` element holding a plain link, and a
script. The element puts the page in a frame, at the height its content asks for, and keeps following it; a reader
without JavaScript follows the link. The frame's address carries a token, signed by the site for that embed and that
origin: the page is answered in the theme the token names, and may be framed by that origin alone — a token copied to
another site shows nothing there.

The height travels by a small `postMessage` protocol: the frame posts its readiness and its height to the origin of its
token, never to `*`; the element reads messages from its own frame alone, and only from the origin of its address; the
theme the other site asks for goes the other way, under the same checks. The bundle writes `frame-ancestors` through
`phil/browser-security-bundle` when it is installed, or in a header of its own.

It is for any Symfony site whose partners show its content: a venue's calendar on the sites of its artists, a
registration form on a partner's page, a list of events on a city's portal.

## Status

| Part | State |
|---|---|
| `Origin`, `SigningKey`, `EmbedClaims`: an origin read by `phil/url`, a key, what a token says | Implemented and tested |
| `EmbedTokenCodec`: tokens `pe1.hs256.<key>.<claims>.<signature>`, an ordered list of keys | Implemented, tested and fuzzed |
| `Embed`, `Embeds`, `EmbedTokens`: the embeds of the configuration, tokens issued and read back into a context | Implemented and tested |
| `EmbedRequestListener`: the page of an embed by its route's `_embed` or `#[Embeddable]` | Implemented and tested |
| `BrowserSecurityFraming`, `HeaderFraming`: `frame-ancestors` for the token's origin, or none | Implemented and tested, with and without `phil/browser-security-bundle` |
| `EmbedMessage`: the frame protocol's reference, shared with the JavaScript by its fixtures | Implemented, tested and fuzzed |
| `<phil-embed>`: the frame at the height of its content, the theme, the link kept without JavaScript or when refused | Implemented; driven in Chrome across two origins by the browser suite |
| The `frame` controller: readiness, height and theme inside the frame | Implemented; driven in Chrome by the browser suite |
| `EmbedCodes`, `phil_embed_code()`, `phil:embed:token`: the code a partner pastes, a token from the console | Implemented and tested |
| `EmbedScriptController`: the script under a stable address, readable across origins | Implemented and tested |
| `RemoteCommentThreads`: the comments of a page carried by the forum of another site | Not started: the port is declared, without an adapter until the forum exists |
| The Flex recipe: the bundle, a key from the environment, the script's route | Written in `recipe/0.1`, for the recipes repository of PHIL |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, the bundle, the key, the route, the controller. |
| [Configuration](doc/configuration.md) | Every option, and the services registered. |
| [Embedding](doc/embedding.md) | Declaring an embed, its page, the code a partner pastes, the element and its fallback. |
| [Tokens](doc/tokens.md) | The format, the keys and their rotation, the claims, what a copied token can do. |
| [Protocol](doc/protocol.md) | The `postMessage` messages and the checks on both sides. |
| [Framing](doc/framing.md) | `frame-ancestors`, with and without `phil/browser-security-bundle`. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | The PHP and Node tests, the fuzz targets and the checks in a browser. |
| [Reference](doc/reference.md) | Every class and module of the bundle, with its role. |

## Quick start

```sh
composer require phil/embed-bundle
```

```yaml
# config/packages/phil_embed.yaml
phil_embed:
    keys:
        - { id: '1', secret: '%env(EMBED_KEY)%' }
    embeds:
        calendar:
            origins: ['https://partner.example']
            themes: [light, dark]
            default_theme: light
```

```php
#[Route('/embed/calendar', defaults: ['_embed' => 'calendar'])]
public function calendar(): Response { /* the page, with phil_embed_frame_attributes() on <html> */ }
```

```twig
<pre>{{ phil_embed_code('calendar', 'https://partner.example', 'calendar_embed', {}, url('calendar'), 'Our calendar') }}</pre>
```

## Requirements

PHP 8.5 or later with the sodium extension, Symfony 8.2 and `phil/url`. For the frame controller, Stimulus 3 and
AssetMapper; for the policy written into the site's own, `phil/browser-security-bundle`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
