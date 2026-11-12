<!-- phil-dev source-sha256=13bea7b532a5561f1feb494ba427b07cf1617884b517a69c40c1ebcbf65fdbf9 -->
# Exécution

## Symfony Scheduler

Avec `symfony/scheduler`, le bundle déclare le calendrier `phil_transitions` : un message `RunDueTransitions` à chaque
`frequency`, traité par `Scheduler::runDue($batch)`. Un *worker* le consomme :

```bash
bin/console messenger:consume scheduler_phil_transitions
```

## Cron

Sans *worker*, lancez la commande ; elle échoue quand une transition a échoué :

```bash
* * * * * bin/console phil:scheduled-transitions:run --limit=100
```

## Les exécutions qui se chevauchent

Une exécution réserve chaque transition avant de l'appliquer : un seul `UPDATE` fixe `claimed_until`, `claim_for`
secondes après la réservation (dix minutes par défaut), seulement si la transition est en attente et qu'aucune autre
exécution ne la retient. Une exécution *cron* qui dure au-delà de la minute suivante, *cron* et un *worker* configurés
tous les deux, ou deux *workers* qui consomment le calendrier n'appliquent donc jamais deux fois la même transition : la
seconde exécution saute ce que la première retient. Donnez à `claim_for` une durée plus longue que celle de tout
gestionnaire ; une exécution qui meurt en retenant une transition laisse une autre la reprendre une fois la réservation
échue. Une tentative échouée libère sa réservation aussitôt et est retentée à l'exécution suivante.

## Le coût d'une exécution

Chaque résultat est écrit par un seul `UPDATE`, qui libère aussi la réservation, et la transition est ensuite détachée
du gestionnaire d'entités : aucun *flush* ultérieur de l'exécution, y compris ceux des gestionnaires, ne la compare ni ne
l'écrit de nouveau. Une exécution d'un lot coûte une réservation, le gestionnaire et un `UPDATE` par transition, quelle
que soit la taille du lot.

## Quand un gestionnaire brise le gestionnaire d'entités

Un gestionnaire dont le propre *flush* échoue, par exemple sur une contrainte violée, ferme le gestionnaire d'entités de
Doctrine. La tentative échouée est tout de même comptée, écrite par le même simple `UPDATE` sur la connexion, si bien
que `max_attempts` l'abandonne à temps ; l'exécution s'arrête alors, et les transitions qu'elle n'a pas atteintes
attendent, sans réservation, l'exécution suivante, qui a un nouveau gestionnaire d'entités : l'exécution *cron*
suivante, ou le message suivant du *worker* une fois le conteneur réinitialisé.
