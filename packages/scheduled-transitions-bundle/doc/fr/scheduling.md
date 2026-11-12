<!-- phil-dev source-sha256=81b01494ed8e31a458d8de3b6decd1584ecbd94811d8d72e15c746433b9b41b3 -->
# Planification

Une transition est un nom (`publish`, `unpublish`, `archive` : lettres minuscules, chiffres et `_`) appliqué à une
ressource, connue par sa sorte et son identifiant, à un moment donné.

| Méthode | Ce qu'elle fait |
|---|---|
| `schedule($resource, $transition, $at)` | La planifie ; la même transition déjà en attente pour la ressource est plutôt déplacée |
| `cancel($resource, $transition)` | Annule une transition en attente, ou toutes celles de la ressource ; retourne leur nombre |
| `pendingFor($resource)` | Ses transitions en attente, la plus proche en premier |
| `runDue($limit)` | Applique celles qui sont échues, la plus ancienne en premier, au plus ce nombre ; un `RunReport` des appliquées et des échouées |

```php
$page = ResourceReference::of('page', $page->id);
$scheduler->schedule($page, 'publish', new DateTimeImmutable('2026-11-09 09:00', new DateTimeZone('America/Toronto')));
$scheduler->schedule($page, 'unpublish', new DateTimeImmutable('2026-12-09 09:00', new DateTimeZone('America/Toronto')));
```

## Tentatives

Un gestionnaire qui lance une exception est une tentative échouée : son message est gardé dans `lastError()` et la
transition reste en attente, pour être tentée à l'exécution suivante, jusqu'à ce que `max_attempts` soit atteint ; elle
est alors `failed` et n'est plus jamais tentée. Une transition qu'aucun gestionnaire n'applique échoue de la même façon.
Une transition faite, échouée ou annulée ne change plus.
