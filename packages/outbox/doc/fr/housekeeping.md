<!-- phil-dev source-sha256=960c6cea657fe28c23e3d8eadb7254bcc42f8f7f016d4a4c46ff70701494d2a8 -->
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

## La dernière erreur

`OutboxRecord::error()` écrit l'erreur d'une tentative échouée : la classe et le code de l'exception, par exemple
`RuntimeException (code 550)`, et son message seulement pour les exceptions de ce paquet, `Phil\Outbox\Exception\…`, dont
le texte nomme des types, des clés et des réglages. Le message de toute autre exception n'est jamais conservé : un
serveur de courriel qui refuse une adresse, ou une base de données qui cite une valeur en double, laisserait des
renseignements personnels dans l'enregistrement jusqu'à ce qu'il soit purgé ou écarté. L'exception complète appartient
aux journaux de l'écouteur, que l'application contrôle.

La raison donnée à `UndeliverableRecord::because()` par un codec est conservée : écrivez-la comme une ligne de journal,
avec des identifiants seulement, comme `The set 42 is gone.`.

## La rétention

Un enregistrement livré est conservé pendant sa rétention, sept jours par défaut, puis purgé : les enregistrements
livrés montrent ce qui a été communiqué, et conservent entre-temps les clés d'idempotence de leurs événements.
Exécutez la purge selon un horaire ; chaque exécution retire au plus `$limit` enregistrements, les plus anciens
d'abord, pour qu'elle reste brève.
