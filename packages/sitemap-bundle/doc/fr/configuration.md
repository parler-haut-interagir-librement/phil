<!-- phil-dev source-sha256=58d69c7cbeb8a9b01d611b3c3766f8a8d3c134601e94f88e0abcef3d2628a247 -->
# Configuration

Un exemple, avec chaque option ; les valeurs par défaut sont dans le tableau plus bas.

```yaml
phil_sitemap:
    default_section: main
    items_by_set: 10000
    dump_directory: '%kernel.project_dir%/public'
    cache_max_age: 86400
    route_options: true
    defaults:
        priority: 0.5
        changefreq: weekly
        lastmod: '2026-11-01'
```

| Option | Valeur par défaut | Rôle |
|---|---|---|
| `default_section` | `default` | La section d'une route dont l'option n'en nomme aucune : de 1 à 64 lettres minuscules, chiffres, traits d'union ou soulignés, commençant par une lettre ou un chiffre, sans se terminer par un souligné suivi de chiffres |
| `items_by_set` | `50000` | Le nombre maximal de pages d'un fichier, de 1 à 50 000 ; une section qui en a plus est coupée en parties numérotées |
| `dump_directory` | `%kernel.project_dir%/public` | Là où écrit `phil:sitemap:dump` quand aucun répertoire n'est donné |
| `cache_max_age` | `3600` | Les secondes pendant lesquelles les navigateurs et les caches partagés peuvent garder un plan de site servi ; `0` ne le laisse garder par aucun ([serving-and-dumping.md](serving-and-dumping.md)) |
| `route_options` | `true` | Si les routes qui portent une option `sitemap` produisent des pages |
| `defaults.priority` | `null` | De 0,0 à 1,0, par pas de 0,1 |
| `defaults.changefreq` | `null` | `always`, `hourly`, `daily`, `weekly`, `monthly`, `yearly` ou `never` |
| `defaults.lastmod` | `null` | `now` — l'heure de l'horloge — ou une date |

Les valeurs par défaut sont ce sur quoi se rabat l'option d'une route ; un fournisseur dit ce qu'il sait de ses
propres pages. Une valeur par défaut laissée à `null` n'écrit rien. Une valeur par défaut que les pages ne pourraient
pas prendre — une priorité entre deux pas, un `lastmod` qui n'est ni `now` ni une date — est refusée à la construction
du conteneur, pas à celle du plan de site.
