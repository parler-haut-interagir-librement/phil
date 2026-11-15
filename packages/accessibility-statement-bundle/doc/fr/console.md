<!-- phil-dev source-sha256=9938c8d1d15f464c6080d7ca03f261e996bb7ed376063c53a682170d2f7339a3 -->
# Console

`phil:accessibility-statement:generate` écrit la version suivante de la déclaration à partir du dernier audit, avec le
`StatementGenerator` de `phil/accessibility-statement`, puis la publie. La commande est enregistrée quand `audit` est
activé.

```sh
bin/console phil:accessibility-statement:generate
bin/console phil:accessibility-statement:generate --dry-run
bin/console phil:accessibility-statement:generate --format=json
```

| Option | Signification |
|---|---|
| `--dry-run` | Montre la version suivante sans la publier. |
| `--format` | `text`, un résumé (cible, état, évaluation et tableau des non-conformités), ou `json`, la déclaration sous la forme des données que prend la configuration. |

Avec la source `config`, la commande ne peut pas publier. Elle montre la version suivante, que vous écrivez à la main
dans la configuration; `--format=json` vous la donne.

| Code de sortie | Quand |
|---|---|
| 0 | la version est publiée, ou montrée |
| 1 | aucune déclaration n'est encore publiée, ou aucun audit n'est disponible |
| 2 | un rapport est illisible, la version ne peut pas être publiée, ou le format est inconnu |

## Le répertoire d'audit

Par défaut, l'audit est le répertoire `var/accessibility` : un rapport JSON de `phil/accessibility-check` par page,
nommé d'après elle, par exemple :

```sh
bin/console phil:accessibility:check templates/pages/home.html --format=json > var/accessibility/home.json
bin/console phil:accessibility:check templates/pages/events.html --format=json > var/accessibility/events.json
bin/console phil:accessibility-statement:generate
```

L'audit est daté par le rapport le plus récent. Sa méthode est `audit.method`, son rapport `audit.report_url`. Un
fichier dont le nom commence par un point, un fichier qui ne se termine pas par `.json` et un sous-répertoire sont
ignorés. Un répertoire de plus de 1 000 rapports, ou un rapport de plus de 5 Mio, est refusé avant d'être lu. Un audit
qui vérifie les pages autrement fournit son propre service `AuditSource` ([Points d'extension](extension-points.md)).
