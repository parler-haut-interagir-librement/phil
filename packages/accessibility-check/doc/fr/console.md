<!-- phil-dev source-sha256=0cb76893fe2bb38366fea74fef3c8b94b9f85e1aeb28125178b47f62984dd41f -->
# Console

`Console\CheckCommand` est la commande `phil:accessibility:check`. Elle vérifie un fichier, ou le HTML donné directement
en argument. Elle ne va jamais chercher une adresse.

```sh
bin/console phil:accessibility:check templates/legal.html
bin/console phil:accessibility:check var/export/article.json --format=json --locale=fr
bin/console phil:accessibility:check '<img src="cat.jpg">' --min-severity=warning
```

| Argument ou option | Signification |
|---|---|
| `file` | le fichier à vérifier, ou le HTML lui-même — ou le JSON d'un document UJO avec `--ujo` ; une adresse (`https://…`) est refusée |
| `--ujo` | lit le contenu comme un document UJO ; un fichier dont le nom se termine par `.json` en est déjà un |
| `--format` | `text`, une ligne par constat et un décompte, ou `json`, le rapport |
| `--min-severity` | `error` par défaut, `warning` ou `notice` : la sévérité à partir de laquelle un constat fait échouer la vérification |
| `--locale` | la locale des messages ; celle de la politique par défaut |
| `--language` | la langue d'un contenu qui n'indique pas la sienne |

Un fichier est lu jusqu'à un octet de plus que le `maxBytes` de la politique : un fichier plus grand est ainsi refusé
sans être lu au complet. Un document UJO est lu et normalisé par `phil/ujo`.

| Code de sortie | Quand |
|---|---|
| 0 | aucun constat n'atteint la sévérité minimale ou plus grave |
| 1 | un constat l'atteint |
| 2 | le contenu ne peut pas être lu, est refusé par le vérificateur ou n'est pas un document UJO ; une option a une valeur erronée |

La commande reçoit l'`AccessibilityChecker` avec lequel elle vérifie : enregistrez-la comme service avec le vérificateur
de l'application ([Installation](installation.md#dans-une-application-symfony)).
