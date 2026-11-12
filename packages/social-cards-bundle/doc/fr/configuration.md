<!-- phil-dev source-sha256=77c8bd7968610b4732596f75b13ede4b1f014d3434d40907248aa10006b5b9aa -->
# Configuration

```yaml
# config/packages/phil_social_cards.yaml — an example, every option shown
phil_social_cards:
    site_name: 'Example'                  # og:site_name, and the subtitle of a card without a description
    locale: ~                             # fr_CA; the request's when null
    twitter_site: '@example'
    fediverse_creator: '@team@example.social'
    default_image: ~                      # an address, or url, alt, width, height, type; used when no card is drawn
    cards:
        enabled: true
        directory: '%kernel.project_dir%/var/social-cards'
        cache_max_age: 31536000
        async: true
        default_template: default
        templates:
            default:
                title_font: '%kernel.project_dir%/assets/fonts/Inter-Bold.ttf'
                subtitle_font: '%kernel.project_dir%/assets/fonts/Inter-Regular.ttf'
                background: '#0f172a'
                title_color: '#f8fafc'
                subtitle_color: '#cbd5e1'
                accent: '#e11d48'
                logo: '%kernel.project_dir%/assets/images/logo.png'
    preview:
        enabled: true
        timeout: 5
        max_duration: 10
        max_bytes: 1048576
        max_redirects: 3
        cache_ttl: 86400
        user_agent: 'PhilSocialCards/0.1 (link preview)'
        limiter: ~                        # limiter.<name> of framework.rate_limiter, for pasted links
```

| Option | Valeur par défaut | Règle |
|---|---|---|
| `site_name`, `locale`, `twitter_site`, `fediverse_creator`, `default_image` | null | Vérifiées comme les vérifie `ShareMetadata`, à la construction du conteneur |
| `cards.enabled` | true | Les cartes ne sont dessinées que si au moins un gabarit est déclaré |
| `cards.directory` | `%kernel.project_dir%/var/social-cards` | Les cartes et leurs demandes ; partagé par les serveurs Web et les workers |
| `cards.cache_max_age` | 31 536 000 | Des secondes, 0 ou plus |
| `cards.async` | true | Les cartes sont dessinées par un worker quand `messenger.default_bus` existe |
| `cards.default_template` | `default` | Doit être parmi les gabarits dès qu'on en déclare un |
| `cards.templates` | aucun | Les clés de `CardTemplate::KEYS` : `title_font` obligatoire, couleurs en hexadécimal, tailles en entiers |
| `preview.enabled` | true | Le client, le récupérateur, les aperçus, le point de terminaison, le composant et la commande |
| `preview.timeout` | 5 | De 0,1 à 60 secondes de silence |
| `preview.max_duration` | 10 | De 0,1 à 120 secondes pour toute la récupération |
| `preview.max_bytes` | 1 048 576 | De 1 Kio à 16 Mio lus d'une page |
| `preview.max_redirects` | 3 | De 0 à 10 |
| `preview.cache_ttl` | 86 400 | Les secondes pendant lesquelles un aperçu reste dans `cache.app` |
| `preview.user_agent` | `PhilSocialCards/0.1 (link preview)` | Non vide, sur une ligne, sans caractère de contrôle ; nommez le site et un moyen de joindre la personne qui l'exploite |
| `preview.limiter` | null | L'identifiant d'une fabrique de limiteur de débit ; le point de terminaison des liens collés consomme un jeton par adresse qu'il lit, par adresse de client |

Les polices et les images d'un gabarit sont lues la première fois que le gabarit sert, là où elles sont installées ;
la configuration ne vérifie que la forme d'un gabarit.
