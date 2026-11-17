<!-- phil-dev source-sha256=ef6738f2d4fb3c97d95e2a147ebe3f86e16fc935e89db07447f622c7302a3415 -->
# Workers

Le relais ne garde aucun état entre les lots ni entre les exécutions : l'entrepôt, le relais et l'exécuteur sont des
services sans état, et un processus de longue durée ne retient rien d'un lot au suivant. Chaque exécution est bornée :
un lot contient au plus `relay.batch_size` enregistrements, et une exécution se termine après ses lots, son temps ou
un signal.

## Sous un gestionnaire de processus

```ini
; supervisord
[program:outbox-relay]
command=php /srv/app/bin/console phil:outbox:relay --time-limit=3600
autorestart=true
```

L'exécution attend `--sleep` secondes après un lot qui n'a rien trouvé, et se termine après une heure pour que le
gestionnaire de processus en démarre une neuve. SIGTERM la termine après le lot en cours.

## Une seule exécution à la fois

Avec `relay.lock` et une fabrique de verrous — `lock.factory` de FrameworkBundle, présente quand `symfony/lock` est
installé —, une seule exécution à la fois détient le verrou `phil_outbox.relay`, renouvelé après chaque lot, pendant
deux fois le bail. Une exécution démarrée pendant qu'une autre le détient s'efface aussitôt. Employez un entrepôt de
verrous que tous les hôtes partagent quand le relais s'exécute sur plusieurs hôtes.

Sans le verrou, plusieurs exécutions partagent l'outbox en toute sécurité : chaque lot est pris à bail, de sorte que
deux exécutions ne livrent jamais le même enregistrement tant que son bail court, et les enregistrements d'une clé
d'ordre passent toujours l'un après l'autre. Désactivez `relay.lock` pour relayer avec plusieurs processus à la fois.

## Selon un horaire

```yaml
phil_outbox:
    scheduler:
        enabled: true
        frequency: 60
        max_batches: 10
```

Le bundle ajoute un horaire `phil_outbox` de Symfony Scheduler : toutes les `frequency` secondes, un message
`RelayOutbox`, qui ne porte rien, parvient à `RelayOutboxHandler`, qui relaie jusqu'à ce que l'outbox soit vide ou que
`max_batches` lots soient écoulés. Un *worker* de Messenger le consomme :

```sh
php bin/console messenger:consume scheduler_phil_outbox
```

Le déclencheur planifié exige `symfony/scheduler` et `symfony/messenger` ; un tic qui trouve une autre exécution
détenant le verrou ne fait rien.

## La purge

Exécutez `phil:outbox:purge` selon un horaire distinct, par exemple un message récurrent de l'horaire de l'application
ou une entrée *cron*, pour retirer les enregistrements livrés dont la rétention est écoulée.
