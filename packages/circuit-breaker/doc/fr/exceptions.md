<!-- phil-dev source-sha256=70bed57c63e75f1736d26470380278753216655f4c6167cd6eb5dcfdf142f290 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Phil\CircuitBreaker\Exception\CircuitBreakerException`.

| Exception | Étend | Levée quand |
|---|---|---|
| `InvalidCircuit` | `InvalidArgumentException` | Une clé hors de son alphabet ou de plus de 87 octets, une valeur de politique hors des bornes, une clé déclarée deux fois dans `CircuitBreakers`. |
| `UnknownCircuit` | `RuntimeException` | On demande à `CircuitBreakers::get()` une clé pour laquelle aucun circuit n'est déclaré. |

`InvalidCircuit` est une erreur de configuration, qu'il vaut mieux attraper là où la configuration est lue, pour qu'elle
échoue au démarrage plutôt qu'au premier appel. `UnknownCircuit` est une erreur de code : l'appel nomme un circuit que
personne n'a déclaré.

Un disjoncteur ne lève jamais d'exception sur un circuit ouvert : `isAvailable()` répond `false`, et l'appelant choisit
sa solution de repli.
