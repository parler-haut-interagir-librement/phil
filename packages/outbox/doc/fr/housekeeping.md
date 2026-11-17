<!-- phil-dev source-sha256=9782a5e8595f12433d564dc8e0536ce0730dbeaf7e2868964d1f8e9350c15983 -->
# Entretien

`Housekeeping` est ce qu'un exploitant fait de l'outbox. phil/outbox-bundle l'expose par des commandes de console.

```php
$housekeeping = new Housekeeping($store, $clock);
```

| Méthode | Fait |
|---|---|
| `dead(int $limit = 50, int $offset = 0)` | Les enregistrements morts, les plus anciens d'abord |
| `find(RecordId $id)` | Un enregistrement, dans n'importe quel état |
| `retry(RecordId $id)` | Remet un enregistrement mort en attente, relayé aussitôt, ses tentatives remises à 0 et sa dernière erreur conservée |
| `retryAll(int $limit = 100)` | La même chose pour les plus anciens enregistrements morts |
| `discard(RecordId $id)` | Retire un enregistrement mort pour de bon, ce qui laisse passer les enregistrements suivants de sa clé d'ordre |
| `discardAll(int $limit = 100)` | La même chose pour les plus anciens enregistrements morts |
| `purge(int $retention = 604800, int $limit = 1000)` | Retire les enregistrements livrés réglés il y a plus de `$retention` secondes |
| `counts()` | Combien d'enregistrements sont en attente, livrés et morts |

## Les enregistrements morts

Un enregistrement mort demeure jusqu'à ce qu'un exploitant l'essaie de nouveau ou l'écarte : il retient entre-temps les
enregistrements suivants de sa clé d'ordre. Sa dernière erreur dit pourquoi il a échoué ; corrigez la cause — un
écouteur, un codec manquant, les données qu'il nomme —, puis essayez-le de nouveau. Écartez un enregistrement dont
l'événement n'importe plus.

## La rétention

Un enregistrement livré est conservé pendant sa rétention, sept jours par défaut, puis purgé : les enregistrements
livrés montrent ce qui a été communiqué, et conservent entre-temps les clés d'idempotence de leurs événements.
Exécutez la purge selon un horaire ; chaque exécution retire au plus `$limit` enregistrements, les plus anciens
d'abord, pour qu'elle reste brève.
