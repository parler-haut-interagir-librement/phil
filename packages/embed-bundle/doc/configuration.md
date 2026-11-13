# Configuration

The bundle's configuration lives under `phil_embed`. The keys are required; everything else has a default:

```yaml
# config/packages/phil_embed.yaml
phil_embed:
    keys:                             # the first signs, all verify
        - { id: '2026-11', secret: '%env(EMBED_KEY)%' }
        - { id: '2026-05', secret: '%env(EMBED_KEY_PREVIOUS)%' }
    allow_localhost: false
    framing: auto                     # auto, browser_security or header
    script_max_age: 86400
    embeds:
        calendar:
            origins: ['https://partner.example', 'https://other.example']
            themes: [light, dark]
            default_theme: light
            ttl: ~
        form: ~                       # any origin a token names, no theme
```

| Option | Default | What it does |
|---|---|---|
| `keys` | — | The keys that sign the tokens, in order: the first signs, all verify. An identifier is 1 to 32 letters, digits, `_` or `-`; a secret is 32 bytes at least. See [Tokens](tokens.md). |
| `allow_localhost` | `false` | Whether `localhost` is an origin and an address, for development and tests. |
| `framing` | `auto` | Who writes `frame-ancestors`: `phil/browser-security-bundle` when it is enabled (`auto`), always it (`browser_security`), or the bundle's own header (`header`). See [Framing](framing.md). |
| `script_max_age` | `86400` | How long browsers keep the script, in seconds. |
| `embeds.<name>` | — | A feature other sites may embed; its name is a lowercase letter, then up to 63 lowercase letters, digits, `_` or `-`. |
| `embeds.<name>.origins` | `[]` | The origins allowed to embed it, read by `phil/url`; any origin a token names when empty. |
| `embeds.<name>.themes` | `[]` | The themes it is shown in; a name is a lowercase letter, then up to 31 lowercase letters, digits or `-`. |
| `embeds.<name>.default_theme` | `null` | The theme when the token names none, one of its themes. |
| `embeds.<name>.ttl` | `null` | How long a token holds, in seconds, up to ten years; until its key is removed when null. |

A key, an embed or an origin that is refused stops the container from compiling; the message never repeats a secret.

## Services

| Service | Role |
|---|---|
| `Phil\EmbedBundle\Application\EmbedTokens` | Issues tokens and reads them back into a context; public. |
| `Phil\EmbedBundle\Application\EmbedCodes` | Writes the code a partner pastes; public. |
| `Phil\EmbedBundle\Application\EmbedTokenCodec` | Signs and reads tokens with the keys. |
| `Phil\EmbedBundle\Application\Contract\FramingPolicy` | Writes `frame-ancestors`. |
| `Phil\EmbedBundle\Infrastructure\Http\EmbedRequestListener` | Answers the page of an embed for its token. |
| `Phil\EmbedBundle\Infrastructure\Http\EmbedScriptController` | Serves the script, behind the route `phil_embed_script`. |
