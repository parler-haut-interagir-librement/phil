<!-- phil-dev source-sha256=eadb8a7fb313e87c71317288a3b65b652da5d18f4c88d1d0b682d8a6359c8686 -->
# Points d'extension

La bibliothèque est du PHP simple : elle ne déclare ni service ni port, et ses classes sont finales. Ce que
l'application choisit passe par des arguments.

| Point | Comment |
|---|---|
| Le fuseau horaire des occurrences | Le fuseau du début donné à `Recurrence::of()`, ou le fuseau donné à `RecurrenceRule::parse()` pour un `UNTIL` lu sans fuseau |
| Les dates écartées | Les `$exclusions` de `Recurrence::of()` : occurrences annulées, jours fériés que calcule l'application |
| Les dates ajoutées | Les `$additions` de `Recurrence::of()` : une séance déplacée à un autre jour, une date de plus |
| L'endroit où les dates ajoutées et retirées sont conservées | `(string) RecurrenceDates::of(...)` écrit une ligne `RDATE` ou `EXDATE`, et `RecurrenceDates::parse()` la relit |
| Le nombre d'occurrences lues | La `$limit` de `between()`, ou `occurrences()` lu à la demande et arrêté par l'appelant |
| L'endroit où une règle est conservée | `(string) $rule` écrit le texte RFC 5545 que l'application enregistre, et `RecurrenceRule::parse()` le relit |
| Les règles en mots, dans d'autres langues | Écrites par l'application à partir des propriétés publiques de `RecurrenceRule` ; la bibliothèque n'écrit aucune phrase |
| L'interception des erreurs de la bibliothèque | Chaque exception implémente `Phil\Recurrence\Exception\RecurrenceException` |

Les fréquences plus fines que le jour (`HOURLY`, `MINUTELY`, `SECONDLY`) sont refusées plutôt qu'étendues : l'heure des
occurrences vient de l'heure du début.
