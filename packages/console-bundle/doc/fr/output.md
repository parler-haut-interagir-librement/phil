<!-- phil-dev source-sha256=b20560f5bbaf7034e14380e490d18d4d6cca629694b34293673853dae1408eed -->
# Sortie

## Horodatages

`--timestamps` place l'heure devant chaque ligne que la commande écrit, sur la sortie standard et sur la sortie
d'erreur :

```sh
bin/console app:import --timestamps
bin/console app:import --timestamps=relative
```

```text
[2026-03-14T09:26:53.589+00:00] Importing 1200 rows
[+12.345s] Imported
```

| Valeur | Heure |
|---|---|
| aucune | Le format configuré, `iso` par défaut |
| `iso` | Le moment en ISO 8601, à la milliseconde, dans le fuseau horaire de l'application |
| `relative` | Les secondes écoulées depuis le départ de la commande, à la milliseconde |
| `none` | Aucune heure, même si la configuration l'active |

L'heure est ajoutée aux octets écrits dans le flux, une fois le travail du formateur terminé :

- une ligne écrite en plusieurs parties reçoit une seule heure, à son début ;
- les largeurs des tableaux, des titres et des blocs restent celles de leur texte ;
- un message que la verbosité masque n'atteint jamais le flux et ne reçoit aucune heure ;
- l'heure est grise sur une sortie décorée, ordinaire sinon.

Une sortie qui n'écrit dans aucun flux, comme un `BufferedOutput`, ne reçoit aucune heure. La question et l'avis des
gardes précèdent l'installation des horodatages et n'en ont pas.

## Journaux de débogage

Avec Monolog, `--debug-phil` écrit les journaux des canaux choisis, à partir du niveau de débogage, sur la sortie
d'erreur, quelle que soit la verbosité des gestionnaires que l'application configure :

```sh
bin/console app:import --debug-phil=doctrine,http_client
bin/console app:import --debug-phil
```

Sans valeur, l'option affiche les canaux de `debug.channels`, ou tous les canaux si cette liste est vide. Les canaux
sont ceux de MonologBundle : `app` pour `monolog.logger`, et le nom de chaque `monolog.logger.<channel>`. Un canal que
le conteneur n'a pas est signalé sur la sortie d'erreur ; les autres sont affichés. Les messages sont écrits en texte
brut et jamais lus comme du balisage de console. Les gestionnaires sont retirés des journaliseurs à la fin de la
commande.

## Sommaire

À la fin d'une commande, avec `-v` par défaut, la sortie d'erreur reçoit une ligne :

```text
app:import: exit code 0 in 12.345 s, memory +3.2 MiB, peak 48.0 MiB.
```

La durée va de la fin des gardes à la fin de la commande. La mémoire est ce que les scripts de PHP utilisent de plus, ou
de moins, qu'au départ. Le pic est celui du processus depuis son démarrage. `summary: always` écrit la ligne à toutes
les verbosités, `summary: never` jamais.

## Les options d'une commande

Les trois options sont ajoutées à la commande qui s'exécute, et non à l'application : une option d'application entrerait
en conflit avec une commande qui définit à sa manière une option du même nom. Une telle option reste celle de la
commande, et le bundle n'y touche pas ; `timestamps.enabled` s'y applique tout de même.
