<!-- phil-dev source-sha256=b4434cf12438f7322ff66f6ef4b25d6a4386c4420d2149658d8e39cf13582876 -->
# Configuration

Un exemple, avec chaque option ; les valeurs par défaut sont dans le tableau plus bas.

```yaml
phil_sitemap:
    default_section: main
    items_by_set: 10000
    dump_directory: '%kernel.project_dir%/public'
    cache_max_age: 86400
    route_options: true
    allow_localhost: false
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
| `allow_localhost` | `false` | Si les pages peuvent être sur `localhost` ou sous `.localhost` : `true` en développement et en test seulement |
| `defaults.priority` | `null` | De 0,0 à 1,0, par pas de 0,1 |
| `defaults.changefreq` | `null` | `always`, `hourly`, `daily`, `weekly`, `monthly`, `yearly` ou `never` |
| `defaults.lastmod` | `null` | `now` — l'heure de l'horloge — ou une date |

Les valeurs par défaut sont ce sur quoi se rabat l'option d'une route ; un fournisseur dit ce qu'il sait de ses
propres pages. Une valeur par défaut laissée à `null` n'écrit rien. Une valeur par défaut que les pages ne pourraient
pas prendre — une priorité entre deux pas, un `lastmod` qui n'est ni `now` ni une date — est refusée à la construction
du conteneur, pas à celle du plan de site.


## Localhost en développement et en test

`localhost` et les noms sous `.localhost` sont refusés par défaut, comme `phil/sitemap` les refuse : aucun moteur de
recherche ne les atteint. Un poste de développement et les tests les permettent, de sorte que `/sitemap.xml` et un
vidage avec `--base-url http://localhost:8000` y fonctionnent :

```yaml
when@dev:
    phil_sitemap:
        allow_localhost: true

when@test:
    phil_sitemap:
        allow_localhost: true
```

Les pages des routes, l'index servi et l'index écrit le lisent. Un fournisseur de l'application lit le paramètre de
conteneur `phil_sitemap.allow_localhost` et le passe aux pages qu'il produit : `new Url($loc, allowLocalhost:
$allowLocalhost)`, et de même à `Alternate` et à `Image`. Une adresse IP, `http://127.0.0.1:8000/`, reste refusée.
