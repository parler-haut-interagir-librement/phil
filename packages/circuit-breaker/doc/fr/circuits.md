<!-- phil-dev source-sha256=3f0428f229775fb3adb8f0dfe3dff80e9b180b0a6c098f54f030028eb047de83 -->
# Circuits

## Clés

Une `CircuitKey` nomme ce que protège un disjoncteur : un client HTTP (`http.payments`), ou une destination comme un
point de terminaison de webhook (`webhook.partner-a`). Un circuit par clé : un tiers en échec ou une destination lente
ne déclenche ainsi jamais que le sien.

Une clé est formée de segments ASCII en minuscules séparés par `.`, `_` ou `-`, de 87 octets au plus. Cette longueur
laisse à un adaptateur la place de dériver d'une clé un nom à lui, comme un indicateur de fonctionnalité d'au plus 100
octets nommé `circuit-open.<key>`. Tout le reste lève `InvalidCircuit`.

```php
CircuitKey::of('http.payments');            // kept
CircuitKey::of('https://example.com/hook'); // InvalidCircuit
```

## Politiques

`CircuitPolicy::of($failureRatePercent, $minimumCalls, $windowSeconds, $openSeconds, $halfOpenSuccesses = 1,
$slowCallMilliseconds = null, $slowCallRatePercent = 100)` :

| Valeur | Bornes | Sens |
|---|---|---|
| `failureRatePercent` | 1 à 100 | La part d'appels échoués qui ouvre le circuit. |
| `minimumCalls` | au moins 1 | Les appels qu'une fenêtre doit compter avant de pouvoir ouvrir le circuit. |
| `windowSeconds` | au moins 1 | La longueur de la fenêtre fixe dans laquelle les appels sont comptés. |
| `openSeconds` | au moins 1 | Combien de temps le circuit reste ouvert avant de laisser passer des appels d'essai. |
| `halfOpenSuccesses` | au moins 1 | Les succès d'essai consécutifs qui le ferment. |
| `slowCallMilliseconds` | au moins 1, ou `null` | La durée que dépasse un appel lent ; `null` ne compte aucun appel comme lent. |
| `slowCallRatePercent` | 1 à 100 | La part d'appels lents qui ouvre le circuit, quand il y a un seuil. |

Une valeur hors des bornes lève `InvalidCircuit`. Les valeurs appartiennent à l'application, une politique par tiers.

- **Le minimum est le dénominateur.** Sans lui, un échec sur un appel fait un taux d'échec de 100 %, et une erreur
  isolée déclenche un tiers en bonne santé. `trips($calls, $failures)` est de l'arithmétique entière
  (`failures × 100 ≥ rate × calls`) : 1 échec sur 3 appels face à 33 % est donc décidé exactement, il déclenche.
- **Les appels lents comptent aussi.** Un tiers qui répond, mais trop lentement, immobilise les processus qui
  l'attendent aussi sûrement qu'un tiers qui échoue. Avec un seuil, un appel qui dure plus que `slowCallMilliseconds`
  est lent, qu'il ait réussi ou échoué — un appel qui dure exactement le seuil ne l'est pas —, et
  `trips($calls, $failures, $slowCalls)` ouvre aussi le circuit quand `slowCalls × 100 ≥ slowCallRatePercent × calls`,
  sur le même minimum d'appels. Un appel dont la durée n'a pas été mesurée n'est jamais lent ;
  `isSlow(CallOutcome)` dit si un appel l'est.

## La machine à états

| État | Appels | Quitté quand |
|---|---|---|
| `closed` | Passent, et leurs résultats sont comptés dans la fenêtre courante. | Il **s'ouvre** quand, dans la fenêtre, au moins `minimumCalls` appels ont été faits et qu'au moins `failureRatePercent` % d'entre eux ont échoué, ou qu'au moins `slowCallRatePercent` % d'entre eux ont été lents. |
| `open` | Vont directement à la solution de repli, sans attendre un délai d'expiration. | Après `openSeconds`, il devient **mi-ouvert**. |
| `half_open` | Les appels d'essai passent. | `halfOpenSuccesses` succès consécutifs qui ne sont pas lents le **ferment** ; un seul échec, ou un seul appel lent, le **rouvre** pour une durée `openSeconds` complète. |

`CircuitSnapshot` contient un état, l'instant où il a commencé (`since`) et ses compteurs. Chaque transition retourne un
nouvel instantané et aucune ne lit d'horloge : le temps entre en argument.

| Méthode | Effet |
|---|---|
| `closed(DateTimeImmutable $now)` | Un circuit fermé dont la fenêtre commence maintenant. |
| `restore($state, $since, $calls, $failures, $halfOpenSuccesses, $slowCalls = 0)` | Un instantané stocké reconstruit, les compteurs ramenés à ce qu'ils peuvent valoir. |
| `at(CircuitPolicy $policy, DateTimeImmutable $now)` | Les transitions que seul le temps provoque : d'ouvert à mi-ouvert après `openSeconds`, une nouvelle fenêtre après `windowSeconds`. Retourne le même instantané quand rien n'a changé. |
| `record($policy, CallOutcome $outcome, $now)` | Un appel compté, après `at()` : un échec, un appel lent, ou les deux. Seul un échec ou un appel lent ouvre le circuit. |
| `recordSuccess($policy, $now)` | Un succès dont la durée n'a pas été mesurée, après `at()`. |
| `recordFailure($policy, $now)` | Un échec dont la durée n'a pas été mesurée, après `at()`. |
| `permitsCalls()` | Si les appels passent : dans tous les états sauf `open`. |

- **Une fenêtre fixe.** Un circuit fermé commence une nouvelle fenêtre, compteurs à zéro, `windowSeconds` après le début
  de la fenêtre. Une fenêtre glissante, ou une limite d'appels d'essai simultanés en mi-ouvert, exige un stockage
  partagé et un verrou, et relève de l'adaptateur de stockage.
- Un résultat inscrit pendant que le circuit est ouvert — un appel en course avec un autre processus — ne change rien.
- Chaque nouvel état commence avec des compteurs vides.

## Résultats

Un `CallOutcome` dit comment s'est passé un appel : `CallOutcome::succeeded($milliseconds = null)` ou
`CallOutcome::failed($milliseconds = null)`, ses propriétés publiques `failed` et `milliseconds`. La durée est la mesure
de l'appelant, au moins 0, ou `null` quand elle n'a pas été mesurée ; une durée négative lève `InvalidCircuit`.
