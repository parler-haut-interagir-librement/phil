<!-- phil-dev source-sha256=4c3141ecad8f94a9de6084ee7395bdc6f332fa02c6f05de6dd17f04626e22460 -->
# Configuration

La configuration du bundle se trouve sous `phil_feed` :

```yaml
# config/packages/phil_feed.yaml
phil_feed:
    limit: 20
    formats: [atom, rss, json]
    cache:
        max_age: 900
        shared_max_age: ~
    websub:
        hubs: ['https://hub.example.net/']
        timeout: 5.0
        max_duration: 10.0
        user_agent: 'ExampleSite/1.0 (+https://example.org/)'
    content:
        news-fr:
            kind: news
            locale: fr
            title: 'Nouvelles'
            home_page: 'https://example.org/fr/nouvelles'
            description: 'Les nouvelles du site.'
            tag_authority: 'example.org,2026'
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `limit` | `20` | Les entrées d'une page d'un flux, de 1 à 500. |
| `formats` | `[atom, rss, json]` | Les formats servis ; un format omis donne un `404` et n'est pas lié. Au moins un. |
| `allow_localhost` | `false` | Si les flux, les pages des flux de contenus et les concentrateurs peuvent être sur `localhost` ou sous `.localhost` : `true` en développement et dans les tests seulement. |
| `cache.max_age` | `900` | Les secondes pendant lesquelles un flux peut être gardé par les navigateurs et les caches partagés ; `0` ne le fait garder par aucun. |
| `cache.shared_max_age` | `null` | Les secondes pendant lesquelles un flux peut être gardé par les caches partagés — un mandataire inverse, un CDN — ; `null` suit `max_age`. |
| `websub.hubs` | `[]` | Les adresses absolues des concentrateurs WebSub avertis quand un flux change. Aucune désactive les annonces. |
| `websub.timeout` | `5.0` | Les secondes pendant lesquelles un concentrateur peut rester silencieux, de 0,1 à 60. |
| `websub.max_duration` | `10.0` | Les secondes que peut durer une annonce à un concentrateur, de 0,1 à 120. |
| `websub.user_agent` | `PhilFeed/0.1` | Le `User-Agent` des annonces. |
| `content` | `{}` | Les flux de `phil/content-bundle`, par nom : voir [Sources](sources.md#flux-de-contenus). |

## Un flux de contenus

| Clé | Requise | Ce que c'est |
|---|---|---|
| Le nom | oui | De 1 à 64 lettres minuscules, chiffres, traits d'union ou soulignés, commençant par une lettre ou un chiffre : le `{name}` de l'adresse |
| `kind` | oui | La clé de ressource du type de contenu, comme `news` |
| `locale` | oui | La locale dont le flux liste les contenus, comme `fr` ou `fr_CA` ; la langue du flux est son étiquette BCP 47, `fr-CA` |
| `title` | oui | Le titre du flux |
| `home_page` | oui | L'adresse absolue de la page du site que le flux suit |
| `description` | non | Une phrase sur le flux |
| `tag_authority` | non | Un domaine et une date, comme `example.org,2026` : chaque entrée se nomme alors `tag:example.org,2026:news:{id}`, ce qui survit à un changement de slug. Sans elle, une entrée est nommée par son adresse |

## Ce que la configuration refuse

Un concentrateur ou une page d'accueil que `phil/url` ne lit pas comme une adresse publique absolue — ou sur
`localhost` sans `allow_localhost` —, un nom de flux, un type, une locale ou une autorité d'étiquette de mauvaise forme,
une limite hors de 1 à 500, une durée de cache négative et un format inconnu sont refusés à la construction du
conteneur, avec le chemin de l'option.
