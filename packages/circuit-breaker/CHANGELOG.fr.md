<!-- phil-dev source-sha256=dcaade5bef01c55bd184f6050481024472fb8552f5fcb3d5b0180d700bd4e720 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/circuit-breaker` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-17

Documentation seulement.

### Modifié

- Le README, en anglais et en français, dit ce que fait le paquet, sans la formulation de ses premières ébauches.
- `CONTRIBUTING.md` dit ce que fait le paquet et comment y travailler, sans la formulation de ses premières ébauches.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `CircuitKey` : ce que garde un disjoncteur, des segments ASCII minuscules séparés par `.`, `_` ou `-`, 87 octets au
  plus.
- `CircuitPolicy` : un seuil de taux d'échec de 1 à 100 %, un minimum d'appels qui est le dénominateur, une fenêtre
  fixe, une durée d'ouverture et les succès en semi-ouverture qui ferment le circuit ; un seuil de lenteur facultatif en
  millisecondes et un taux d'appels lents, pour que les appels qui durent plus longtemps que le seuil ouvrent aussi le
  circuit ; `trips()` en arithmétique entière, `isSlow()`.
- `CallOutcome` : comment un appel s'est passé, `succeeded()` ou `failed()`, avec sa durée en millisecondes quand
  l'appelant l'a mesurée.
- `CircuitState` et `CircuitSnapshot` : la machine à états fermé/ouvert/semi-ouvert comme transitions pures d'une
  politique et d'un instant — `at()`, `record()`, `recordSuccess()`, `recordFailure()`, `permitsCalls()` —, `closed()`
  et `restore()` ; les appels lents comptés avec les appels et les échecs de la fenêtre, un appel d'essai lent ouvrant
  de nouveau le circuit.
- `CircuitStateStore`, le port des états partagés par les processus ; un état jamais stocké ou perdu est fermé.
- `CircuitOverride`, le port de l'exploitant qui force un circuit ouvert ; `CircuitOverrides`, plusieurs forçages
  demandés ensemble, aucun ne forçant rien quand la liste est vide.
- `CircuitBreaker` : `isAvailable()`, `state()`, `record()`, `recordSuccess()` et `recordFailure()` sur un stockage, un
  forçage et une horloge PSR-20, les transitions dues au temps enregistrées seulement quand elles changent quelque
  chose, rien de consigné tant que le circuit est forcé ouvert ; `CircuitBreakers`, les disjoncteurs par clé, chaque clé
  déclarée une fois.
- `CircuitBreakerException`, implémentée par `InvalidCircuit` et `UnknownCircuit`.
- Le banc d'essai de la demande et du rapport d'un disjoncteur, minutés ou non, d'une transition et d'une clé.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
