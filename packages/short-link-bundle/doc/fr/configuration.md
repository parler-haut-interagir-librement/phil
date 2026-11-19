<!-- phil-dev source-sha256=225fc2b2523ecdc60053d9e7397871389ecf0c2aafb0c74f623dcfdff8a73674 -->
# Configuration

Chaque option de `phil_short_link`, avec sa valeur par défaut :

```yaml
# config/packages/phil_short_link.yaml
phil_short_link:
    enabled: true
    # The domains that serve the short links, at least one, in order.
    domains:
        - { host: go.example.com, locale: ~, scheme: https }
        - { host: lien.example.fr, locale: fr }
    # Where the root of a domain sends; none answers it with a 404.
    home_url: ~
    # localhost and the names under .localhost as domains and targets, in development and tests.
    allow_localhost: false
    entity_manager: doctrine.orm.entity_manager
    code:
        alphabet: abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789
        min_length: 6
        max_number: 1099511627775
        blocklist: []
        default_blocklist: true
        custom: true
    targets:
        allowed_hosts: []
        any_host: false
        check: false
    rate_limit:
        enabled: true
        limit: 30
        interval: 1 hour
        cache_pool: cache.app
        limiter: ~
        single_server: false
    qr_code:
        level: M
        quiet_zone: 4
        dark: '#000000'
        light: '#ffffff'
        size: 256
        scale: 8
    admin:
        enabled: true
        role: ROLE_ADMIN
        layout: '@PhilShortLink/admin/layout.html.twig'
        timezone: UTC
    visitors:
        link_previews: []
        robots: []
    analytics:
        site: ~
        event: short_link_visit
```

| Option | Rôle |
|---|---|
| `domains` | Les domaines de liens courts. Un nom d'hôte seul — ni port, ni chemin, ni adresse IP —, lu par phil/url : en minuscules, un nom international sous sa forme ASCII. `locale` limite un domaine aux liens de cette locale ; `scheme` est celui qui s'écrit dans les adresses des liens. |
| `home_url` | Une URL `http` ou `https` absolue vers laquelle la racine de chaque domaine envoie par un `302`. |
| `allow_localhost` | Accepte `localhost` et les noms sous `.localhost`, pour le développement et les tests seulement. |
| `entity_manager` | Le gestionnaire d'entités qui conserve les liens. |
| `code.alphabet` | Les caractères des codes, chacun une seule fois, parmi les lettres, les chiffres, `-`, `.`, `_` et `~`, au moins trois. |
| `code.min_length` | Le plus court code tiré, de 0 à 255. |
| `code.max_number` | Le plus grand nombre qu'un code tiré écrit, 1 000 000 ou plus : plus de nombres, c'est moins de collisions et des codes plus longs. |
| `code.blocklist` | Des mots qu'un code évite, ajoutés à la liste par défaut. |
| `code.default_blocklist` | Garde la liste par défaut de mots grossiers en anglais et en français. |
| `code.custom` | Permet à un auteur de choisir un code au lieu d'en tirer un. |
| `targets.allowed_hosts` | Les hôtes vers lesquels un lien peut mener. Vide par défaut : aucun lien ne peut être créé tant que l'application ne les nomme pas, ou n'autorise pas n'importe quel hôte. |
| `targets.any_host` | Permet à un lien de mener vers n'importe quel hôte, les domaines de liens courts exceptés. |
| `targets.check` | Lit chaque cible par phil/safe-http avant de l'enregistrer. |
| `rate_limit.*` | Les liens qu'un auteur peut créer dans une fenêtre, sur une fenêtre glissante conservée dans `cache_pool`, ou sur la fabrique de limiteurs de débit nommée par `limiter`. Dans l'environnement `prod`, un groupe de cache propre à chaque serveur est refusé, sauf si `single_server` dit que l'application tourne sur un seul serveur. |
| `qr_code.*` | Le niveau de correction d'erreur, la zone de silence en modules, les couleurs en `#rrggbb`, la taille du SVG en pixels et l'échelle du PNG en pixels par module. |
| `admin.*` | L'administration : le rôle qui l'ouvre, la mise en page que ses pages étendent, le fuseau horaire dans lequel ses dates s'écrivent. |
| `visitors.link_previews` | Des morceaux de `User-Agent` qui désignent l'aperçu de lien d'un service de messagerie ou d'un réseau social, comparés sans égard à la casse, ajoutés à ceux qui sont connus. |
| `visitors.robots` | Des morceaux de `User-Agent` qui désignent un robot d'indexation, un script ou un moniteur, comparés sans égard à la casse, ajoutés à ceux qui sont connus. |
| `analytics.site` | Le site de phil/analytics-bundle pour lequel les visites de personnes comptent ; aucun, et rien n'est compté. |
| `analytics.event` | L'événement serveur, déclaré dans les `server_events` du site. |

Une configuration qui nomme un paquet absent de l'application — `targets.check` sans phil/safe-http, `analytics.site`
sans phil/analytics-bundle, l'administration sans Twig, les formulaires ou les jetons CSRF — fait échouer la
construction du conteneur avec un message qui dit lequel.
