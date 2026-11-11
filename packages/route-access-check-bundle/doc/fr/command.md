<!-- phil-dev source-sha256=532b1a5de9a3d7d4fc2f45e3f1e8eae0804326abab987924d33c795f040f558e -->
# La commande

```sh
bin/console phil:route-access:check [--format=text|json|github|junit] [--baseline=FILE] [--generate-baseline[=FILE]] [--only-problems] [--strict]
```

| Option | Sens |
|---|---|
| `--format`, `-f` | Le format du rapport sur la sortie standard : `text` (par défaut), `json`, `github`, `junit`, ou un format de l'application. |
| `--baseline`, `-b` | Le fichier de ligne de base dont les échecs sont tolérés ; par défaut, celui qui est configuré. |
| `--generate-baseline` | Écrit les échecs actuels dans ce fichier, ou dans la ligne de base configurée, ou dans `route-access-baseline.json`, puis se termine avec 0. |
| `--only-problems` | Omet les routes qui passent. |
| `--strict` | Échoue aussi sur les avertissements : entrées `access_control` qui n'atteignent aucune route, entrées périmées de la ligne de base, chemins d'exemple inexacts. |

Le rapport va sur la sortie standard, brut, pour que `--format=json > report.json` écrive un document valide. Les
messages sur la commande elle-même — un format inconnu, une ligne de base illisible, la ligne de base écrite — vont sur
la sortie d'erreur.

## Codes de sortie

| Code | Quand |
|---|---|
| 0 | Chaque route passe, ou la ligne de base tolère son échec ; avec `--strict`, aucun avertissement non plus. |
| 1 | Une route n'est pas couverte ou est en conflit, et la ligne de base ne la tolère pas ; ou, avec `--strict`, un avertissement reste. |
| 2 | Le format est inconnu, ou la ligne de base est illisible : absente, plus grosse que 5 Mo, ou pas un document de ligne de base. |

## En intégration continue

```yaml
# .github/workflows/ci.yaml
- name: Route access
  run: bin/console phil:route-access:check --env=test --format=github --only-problems
```

Le format GitHub annote chaque route en échec sur le fichier et la ligne de son contrôleur. Pour un serveur
d'intégration continue qui lit JUnit :

```sh
bin/console phil:route-access:check --env=test --format=junit > var/route-access.xml
```

## Adopter la vérification

Sur une application qui a beaucoup de routes, générez la ligne de base une fois, faites-en un commit, et laissez la
vérification échouer seulement sur les nouveaux échecs :

```sh
bin/console phil:route-access:check --env=test --generate-baseline
```

Corrigez les routes tolérées une à une ; `--strict` signale alors chaque entrée de la ligne de base déjà corrigée, pour
qu'on la retire et que la ligne de base ne fasse que rétrécir.
