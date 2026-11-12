<!-- phil-dev source-sha256=1e70103ede722b4d0a4e9025b896a84aeb3d2b6a8297f1bd90a1bcbeee1f8c44 -->
# Vérifier une page

```bash
bin/console phil:structured-data:check https://example.com/jeux/tournoi
bin/console phil:structured-data:check var/pages/tournoi.html --strict
bin/console phil:structured-data:check https://example.com/ --format=json
```

La commande lit chaque élément JSON-LD de la page avec `JsonLdReader`, les fusionne en un seul graphe, puis le vérifie
avec le `Validator` de phil/structured-data.

| Argument ou option | Sens |
|---|---|
| `page` | Une adresse `http://` ou `https://`, récupérée avec `symfony/http-client` ; ou le chemin d'une page HTML enregistrée. Une adresse de tout autre schéma — `file://`, `phar://` — est refusée, jamais ouverte par un enveloppeur de flux |
| `--strict` | Échouer aussi sur les avertissements |
| `--format` | `text`, la valeur par défaut, ou `json` |

| Code de sortie | Quand |
|---|---|
| 0 | Aucune erreur, et aucun avertissement avec `--strict` |
| 1 | Une erreur, un élément illisible, un avertissement avec `--strict`, ou une page illisible |
| 2 | Un format inconnu |

Le texte énumère chaque élément illisible et chaque problème, puis le nombre de nœuds, d'éléments, d'erreurs et
d'avertissements. Le JSON contient la `page`, le nombre d'`elements` et de `nodes`, les éléments `unreadable` et les
`issues`.

Une page est récupérée par un `GET`, avec cinq redirections au plus, en `check.timeout` secondes ; une page de plus de
5 Mo est tronquée et refusée, et une réponse hors de la plage 2xx est un échec. Un déploiement peut vérifier quelques
adresses de chaque sorte de page après sa mise en ligne.
