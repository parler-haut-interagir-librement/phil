<!-- phil-dev source-sha256=d7ae3160c1790d0223baf5e20cea79dc0aa211c654c546f60779cebc844d21b0 -->
# Relais

`Relay::relay()` relaie un lot d'enregistrements :

1. Il réserve jusqu'à `$limit` enregistrements — 100 par défaut, 1 000 au plus — sous un jeton qui lui est propre et un
   bail de `$lease` secondes, 60 par défaut.
2. Pour chaque enregistrement, dans l'ordre où ils ont été enregistrés, il reconstruit l'événement par le codec de
   l'enregistrement et le répartit par le répartiteur PSR-14.
3. Il marque l'enregistrement livré une fois la répartition terminée, et seulement alors.
4. Un échec rend l'enregistrement pour qu'il soit essayé de nouveau plus tard, ou le déclare mort après sa dernière
   tentative.

```php
$relay = new Relay($store, $codecs, $dispatcher, $clock, new Backoff(), lease: 60);
$report = $relay->relay(100);
```

Le relais ne conserve aucun état d'une exécution à l'autre : une seule instance sert toutes les exécutions d'un
*worker*. Une application Symfony l'exécute avec la commande `phil:outbox:relay` de phil/outbox-bundle ; une autre
application appelle `relay()` depuis sa propre boucle de *worker* ou sa tâche planifiée.

## Au moins une fois

Un enregistrement est livré au moins une fois, jamais exactement une fois : un processus qui s'arrête après la
répartition et avant la marque livre de nouveau l'enregistrement à l'exécution suivante. **Les écouteurs sont
idempotents.** Chaque enregistrement a un identifiant stable, qu'un codec place dans son événement et que
`RecordedEvent::$id` porte : un écouteur conserve les identifiants qu'il a traités, ou rend son effet idempotent — un
upsert, une contrainte d'unicité, un état qui ne bouge pas deux fois.

## Les baux

Une réservation prend à bail ses enregistrements jusqu'à la fin de son bail : aucune autre réservation ne les obtient
entre-temps, de sorte que des relais concurrents ne livrent jamais le même enregistrement pendant que son bail court.
Le relais cesse d'essayer les enregistrements de son lot une fois le bail écoulé ; ils redeviennent libres à la fin du
bail et la réservation suivante les prend, sans qu'une tentative soit comptée.

Le bail est plus long que l'écouteur le plus lent. Un écouteur qui le dépasse laisse un autre relais réserver
l'enregistrement et le livrer de nouveau ; la marque du premier relais est alors refusée, ce que son rapport compte
comme `lost`.

## L'ordre d'une clé

Les enregistrements d'une clé d'ordre passent l'un après l'autre : un entrepôt retient un enregistrement tant qu'un
enregistrement antérieur de sa clé est en attente — à bail, en attente de sa prochaine tentative — ou mort. Un lot
contient au plus un enregistrement d'une clé ; le suivant passe dans un lot ultérieur, une fois l'enregistrement
antérieur livré ou écarté. Un enregistrement mort retient sa clé jusqu'à ce qu'il soit essayé de nouveau ou écarté : voyez
[Entretien](housekeeping.md). Les enregistrements sans clé n'attendent jamais les uns après les autres.

## Nouvelles tentatives et enregistrements morts

Une tentative échouée rend l'enregistrement avec ses tentatives et la classe et le message de l'exception, coupés à
500 octets. `Backoff` décide quand il est essayé de nouveau : son premier délai après le premier échec, doublé après
chaque échec suivant, sans jamais dépasser son délai le plus long. Après sa dernière tentative, l'enregistrement est
mort.

| Réglage de `Backoff` | Valeur par défaut |
|---|---|
| `$firstDelay` | 60 secondes |
| `$longestDelay` | 86 400 secondes |
| `$maxAttempts` | 10 |

Un enregistrement est mort aussitôt, après une seule tentative, quand sa charge utile n'en est pas une, ou quand son
codec lance `UndeliverableRecord`.

## Les types inconnus

Un enregistrement dont aucun codec ne reconstruit le type est essayé de nouveau pendant le délai de grâce des types
inconnus, compté depuis le moment où l'enregistrement s'est produit : un processus Web déployé avec un nouveau codec
peut enregistrer un type avant que les *workers* qui le relaient soient déployés. Ses tentatives suivent le recul,
jamais plus tard que la fin du délai de grâce et quel que soit leur nombre ; le délai écoulé, la tentative suivante le
déclare mort. Le délai de grâce est le dernier argument de `Relay` : une heure par défaut
(`Relay::UNKNOWN_TYPE_GRACE`), de 0, qui enterre l'enregistrement aussitôt, à sept jours
(`Relay::MAX_UNKNOWN_TYPE_GRACE`).

## Les enregistrements écartés

Un codec lance `ForgottenRecord` quand ce que l'enregistrement nomme a été effacé exprès. Le relais marque
l'enregistrement écarté, sans tentative comptée et sans erreur : aucun écouteur ne le reçoit, il n'est jamais mort, il
laisse passer les enregistrements suivants de sa clé, et la purge le retire avec les enregistrements livrés.

## Le rapport

`RelayReport` indique ce qu'une exécution a fait :

| Propriété | Compte les enregistrements |
|---|---|
| `claimed` | que l'exécution a réservés |
| `delivered` | livrés et marqués comme tels |
| `retried` | rendus pour être essayés de nouveau |
| `dead` | déclarés morts |
| `expired` | laissés sans essai parce que le bail s'est écoulé |
| `lost` | dont une autre réservation avait repris la marque |
| `skipped` | écartés parce que ce qu'ils nomment a été effacé |

`isEmpty()` indique une exécution qui n'a rien trouvé ; `plus()` additionne deux exécutions.
