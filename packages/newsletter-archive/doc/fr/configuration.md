<!-- phil-dev source-sha256=4e4effb5433d6c648a78dfaec4182374f75d0fd37f3e47cfd34a4f15bc0337a8 -->
# Configuration

```yaml
# config/packages/phil_newsletter_archive.yaml
phil_newsletter_archive:
    enabled: true
    connection: doctrine.dbal.default_connection
    page_size: 20
    slugger: ~
    slug_max_length: 120
```

| Option | Valeur par défaut | Ce qu'elle fait |
|---|---|---|
| `enabled` | `true` | Enregistre les services de l'archive ; `false` n'en enregistre aucun. |
| `connection` | `doctrine.dbal.default_connection` | La connexion Doctrine DBAL des tables de l'archive. |
| `page_size` | `20` | Combien de numéros une page de l'archive liste, de 1 à 100. |
| `slugger` | `null` | Le service d'un `Phil\Slug\Slugger` qui fabrique les slugs des numéros, celui de `phil/slug-bundle` par exemple. Par défaut, un `TransliteratingSlugger`. |
| `slug_max_length` | `120` | Le slug le plus long que fabrique le slugger par défaut, de 16 à 200, coupé entre deux mots. |

Le site d'une requête est celui que `phil/newsletter-bundle` résout par son service `CurrentSite` : une application qui
sert plusieurs sites sépare leurs archives grâce à lui.
