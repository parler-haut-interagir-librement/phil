<!-- phil-dev source-sha256=db6b27db9b499e342be588a409e64b8623f096ba2de633e1c56f5756d3892205 -->
# Configuration

La configuration du bundle se trouve sous `phil_embed`. Les clés sont obligatoires ; tout le reste a une valeur par
défaut :

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

| Option | Valeur par défaut | Effet |
|---|---|---|
| `keys` | — | Les clés qui signent les jetons, dans l'ordre : la première signe, toutes vérifient. Un identifiant compte de 1 à 32 lettres, chiffres, `_` ou `-` ; un secret fait 32 octets au moins. Voir [Jetons](tokens.md). |
| `allow_localhost` | `false` | Si `localhost` est une origine et une adresse, pour le développement et les tests. |
| `framing` | `auto` | Qui écrit `frame-ancestors` : `phil/browser-security-bundle` lorsqu'il est activé (`auto`), toujours lui (`browser_security`), ou l'en-tête propre au bundle (`header`). Voir [Encadrement](framing.md). |
| `script_max_age` | `86400` | Combien de temps les navigateurs conservent le script, en secondes. |
| `embeds.<name>` | — | Une fonction que d'autres sites peuvent intégrer ; son nom est une lettre minuscule, puis jusqu'à 63 lettres minuscules, chiffres, `_` ou `-`. |
| `embeds.<name>.origins` | `[]` | Les origines autorisées à l'intégrer, lues par `phil/url` ; toute origine que nomme un jeton lorsque la liste est vide. |
| `embeds.<name>.themes` | `[]` | Les thèmes dans lesquels elle est montrée ; un nom est une lettre minuscule, puis jusqu'à 31 lettres minuscules, chiffres ou `-`. |
| `embeds.<name>.default_theme` | `null` | Le thème lorsque le jeton n'en nomme aucun, l'un de ses thèmes. |
| `embeds.<name>.ttl` | `null` | Combien de temps un jeton vaut, en secondes, jusqu'à dix ans ; jusqu'au retrait de sa clé lorsque la valeur est nulle. |

Une clé, une intégration ou une origine refusée empêche la compilation du conteneur ; le message ne répète jamais un
secret.

## Services

| Service | Rôle |
|---|---|
| `Phil\EmbedBundle\Application\EmbedTokens` | Émet des jetons et les relit dans un contexte ; public. |
| `Phil\EmbedBundle\Application\EmbedCodes` | Écrit le code qu'un partenaire colle ; public. |
| `Phil\EmbedBundle\Application\EmbedTokenCodec` | Signe et lit les jetons avec les clés. |
| `Phil\EmbedBundle\Application\Contract\FramingPolicy` | Écrit `frame-ancestors`. |
| `Phil\EmbedBundle\Infrastructure\Http\EmbedRequestListener` | Sert la page d'une intégration pour son jeton. |
| `Phil\EmbedBundle\Infrastructure\Http\EmbedScriptController` | Sert le script, derrière la route `phil_embed_script`. |
