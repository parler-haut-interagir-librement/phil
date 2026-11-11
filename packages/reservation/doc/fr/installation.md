<!-- phil-dev source-sha256=290a52f6afe42d522efe644bbac532e6e5634a183f90f83f5cb74b08187716b9 -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare la bibliothèque et sur lequel elle est testée |
| `ext-mbstring` | toute version | Valeurs citées dans les messages, coupées en caractères |

```sh
composer require phil/reservation
```

La bibliothèque ne déclare aucun service : l'application construit ses `Bookable` à partir de ce qu'elle enregistre,
ou utilise `phil/reservation-bundle` dans Symfony.
