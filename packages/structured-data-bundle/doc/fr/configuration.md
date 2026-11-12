<!-- phil-dev source-sha256=a9afb7bbfb504ed5c2bc346566243488eee35e3bcc503e182161743ab34109ec -->
# Configuration

```yaml
phil_structured_data:
    base_url: 'https://example.com/'      # the request's address when null
    language: ~                          # the language of every graph, BCP 47
    request_language: false              # or the request's language
    website:
        name: Example Press              # turns the WebSite on
        alternate_name: Example
        description: Jeux et livres
        url: ~                           # the base address when null
        search_url: 'https://example.com/recherche?q={search_term_string}'
    organization:
        type: Organization               # or Corporation, NGO, LocalBusiness, Store…
        name: Example Press        # turns the Organization on
        legal_name: ~
        url: ~                           # the base address when null
        logo: 'https://example.com/logo.png'
        description: ~
        email: bonjour@example.com
        telephone: '+15145550100'
        same_as: ['https://social.example/example-press']
        address:
            street_address: 1 rue Principale
            address_locality: Montréal
            address_region: QC
            postal_code: H2X 1Y4
            address_country: CA
    web_page: true
    breadcrumb:
        menu: main                       # a phil/menu-bundle menu
    nonce:
        request_attribute: ~             # e.g. script_nonce
        content_security_policy: false   # the script nonce of phil/browser-security-bundle
    check:
        timeout: 10
    allow_localhost: false
```

| Clé | Valeur par défaut | Sens |
|---|---|---|
| `base_url` | `null` | L'adresse du site, pour les identifiants et les adresses de ses nœuds ; sans elle, le schéma, l'hôte et le chemin de base de la requête. Un site derrière un mandataire ou servi sous plusieurs hôtes la définit, pour que ses identifiants restent les mêmes |
| `language` | `null` | La langue des textes de chaque graphe, écrite comme `@language` de son contexte |
| `request_language` | `false` | Le graphe dans la langue de la requête quand `language` est nul |
| `website` | désactivé | Le site comme `WebSite`, `#website` à l'adresse de base ; activé par n'importe laquelle de ses clés, et il exige alors un `name` |
| `website.search_url` | `null` | L'adresse de la recherche du site, avec `{search_term_string}` à la place des mots : une `SearchAction` |
| `organization` | désactivé | Le propriétaire comme `Organization`, ou la sorte que nomme `type`, `#organization` à l'adresse de base ; activé par n'importe laquelle de ses clés, et il exige alors un `name` |
| `organization.address` | vide | Sa `PostalAddress`, omise quand toutes les clés sont nulles |
| `web_page` | `true` | Chaque page comme `WebPage`, `#webpage` à son adresse — sans sa chaîne de requête —, qui fait partie du site quand le site est activé |
| `breadcrumb.menu` | `null` | Le menu dont le fil d'Ariane devient la `BreadcrumbList` de chaque page ; exige phil/menu-bundle |
| `nonce.request_attribute` | `null` | L'attribut de la requête principale qui contient un *nonce* pour l'élément JSON-LD ; par défaut, l'élément n'en a aucun et la page garde son cache partagé |
| `nonce.content_security_policy` | `false` | L'élément JSON-LD prend le *nonce* de script de la politique qu'envoie phil/browser-security-bundle, qui le nomme alors ; exige ce bundle. Une page avec un *nonce* quitte les caches partagés |
| `check.timeout` | `10` | Le nombre de secondes pendant lesquelles `phil:structured-data:check` attend une page, 0,1 au moins |
| `allow_localhost` | `false` | Si une adresse des graphes peut être sur `localhost` ou sous `.localhost`, pour les fabriques de `Schema` et le validateur de la vérification : `true` sous `when@dev` et `when@test` seulement |

Le site, son propriétaire et la page portent la langue de la requête comme `inLanguage`.

## Ce qui est refusé

Une valeur qu'aucun graphe ne pourrait contenir empêche la construction du conteneur, avec un message qui nomme la
clé :

- une adresse — `base_url`, `website.url`, `website.search_url`, `organization.url`, `organization.logo`,
  `organization.same_as` — qui n'est pas une adresse absolue `http://` ou `https://`, comme la lit `phil/url` : avec un nom d'utilisateur ou un
  mot de passe, sur une adresse IP, ou sur `localhost` ou sous `.localhost` tant que `allow_localhost` vaut `false` ; un
  hôte international est accepté ;
- une `search_url` sans `{search_term_string}` ;
- une `language` qui n'est pas une étiquette BCP 47 ; une locale avec trait de soulignement, `fr_CA`, est lue comme
  `fr-CA` ;
- un `type` d'organisation qui n'est pas un terme schema.org ;
- un site ou un propriétaire activé sans `name` ;
- un site ou un propriétaire que son type n'accepterait pas : les nœuds de la configuration sont construits une fois par
  `Schema` de phil/structured-data quand le conteneur est construit, de sorte qu'un `LocalBusiness` sans son adresse
  est refusé là plutôt que sur une page ;
- à la fois `nonce.request_attribute` et `nonce.content_security_policy`.
