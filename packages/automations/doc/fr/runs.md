<!-- phil-dev source-sha256=d7d914ef97ffcb31343cfb48461e3e0d949095331de553095bcd6a5ba6c5bc69 -->
# Exécutions

## D'un événement à une exécution

`AutomationEngine::handle($event)` demande à chaque déclencheur qui lit la classe de l'événement de le confronter aux
options de chaque définition activée de son type. Une correspondance démarre une exécution, dont l'identifiant est tiré
de l'automatisation et de la clé d'événement : le magasin la crée une seule fois, de sorte que le même événement livré
deux fois la démarre une seule fois. Chaque nouvelle exécution est sauvegardée comme en attente, due immédiatement, et
un message `AdvanceRun` qui porte son identifiant est envoyé.

## Parcourir les étapes

`AdvanceRunHandler` appelle `AutomationEngine::advance()` sur un *worker*. Le moteur lit l'exécution et la version de sa
définition, puis la réclame : il sauvegarde l'exécution comme en cours, avec un bail de cinq minutes, sur la révision
qu'il a lue. De deux *workers* qui lisent la même révision, l'un sauvegarde et l'autre ne change rien et s'arrête.
Ensuite :

| Étape | Le moteur |
|---|---|
| Action | L'exécute, puis sauvegarde le résultat et l'étape suivante, avec le bail renouvelé |
| Délai | Sauvegarde l'exécution comme en attente jusqu'à la fin du délai, et envoie un `AdvanceRun` avec un `DelayStamp` |
| Filtre | Continue quand la condition est vraie ; sinon termine l'exécution comme `filtered` |
| Embranchement | Continue avec la première étape de `then` ou de `else`, ou après l'embranchement quand cette voie est vide |

Une exécution sans étape restante se termine comme `completed`. Un message qui arrive avant son heure, ou pour une
exécution qu'un autre *worker* détient, ne change rien.

## Les messages et la planification

`AdvanceRun` et `ResumeDueRuns` ne portent qu'un identifiant et un nombre, rien d'autre. Un transport ne peut pas garder
un message différé pendant des semaines, et un *worker* peut s'arrêter au milieu d'une exécution : `AutomationsSchedule`,
la planification `automations` de Symfony Scheduler, envoie un `ResumeDueRuns` chaque minute, et son *handler* envoie un
`AdvanceRun` pour chaque exécution dont l'heure est venue — un délai terminé, une nouvelle tentative due, un bail
expiré.

```php
new AutomationsSchedule(frequency: '30 seconds', limit: 1000);
```

## Les nouvelles tentatives

Une action qui lève `ActionFailed::retryable()`, ou toute exception sauf un `ActionFailed` permanent, est réessayée
quand son étape permet des nouvelles tentatives : l'exécution attend 30 secondes, puis 60, 120… jusqu'à une heure entre
les tentatives, et chaque tentative reçoit la même clé d'idempotence.

## La compensation

Quand une étape échoue pour de bon — un échec permanent, des nouvelles tentatives épuisées, une condition qui échoue,
une action qui n'est plus enregistrée —, le moteur remonte les étapes d'action faites, la plus récente d'abord, et
appelle `compensate()` sur celles dont l'action implémente `CompensableAction`, avec le résultat que chacune a rendu.
L'exécution se termine comme `failed`, sa raison conservée. Quand une compensation lève une exception, les autres
s'exécutent quand même, et l'exécution se termine comme `compensation_failed` : quelqu'un doit y regarder.

## Les états

| `RunState` | Signifie |
|---|---|
| `waiting` | Elle continue à son heure |
| `running` | Un *worker* la détient jusqu'à la fin de son bail |
| `completed` | Chaque étape s'est exécutée |
| `filtered` | Un filtre l'a terminée |
| `failed` | Une étape a échoué ; ce qui pouvait être défait l'a été |
| `compensation_failed` | Une étape a échoué, et défaire une étape précédente a échoué aussi |

## Les *workers*

Le moteur, le catalogue, l'évaluateur et les magasins ne gardent rien entre les appels, de sorte qu'une instance sert
tous les messages d'un *worker* de longue durée. Le cache des conditions est borné à 256 entrées. `WebhookAction` garde
les adresses que sa protection a résolues : elle implémente le `ResetInterface` de Symfony, que le conteneur appelle
entre les messages.

Le moteur journalise une exécution échouée avec son seul identifiant et celui de son automatisation.
