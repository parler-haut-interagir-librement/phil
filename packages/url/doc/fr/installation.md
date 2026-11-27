<!-- phil-dev source-sha256=4d6105297db5dbf380c5e33f9e8ef42a2055eda64684910563cde3c9bc22be06 -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que la bibliothèque déclare et sur lequel elle est testée |
| `ext-mbstring` | toute | Les messages coupés à une longueur en caractères |
| `ext-uri` | toute | `Uri\WhatWg\Url`, l'analyseur d'URL du WHATWG livré avec PHP 8.5 : une adresse lue comme un navigateur la lit |
| `phil/enums` | ^0.3@dev | `EnumSet`, les ensembles de genres qu'un champ accepte |
| `symfony/polyfill-intl-idn` | ^1.43 | `idn_to_ascii()` et `idn_to_utf8()` quand `ext-intl` n'est pas chargée : un hôte international gardé sous sa forme ASCII |

```sh
composer require phil/url
```

## Avec ou sans `ext-intl`

`ext-intl` est suggérée, non requise. Quand elle est chargée, les `idn_to_ascii()` et `idn_to_utf8()` de PHP s'exécutent et
le polyfill reste inactif ; sans elle, le polyfill prend leur place. Les résultats sont les mêmes, à deux différences près :

| | Avec `ext-intl` | Sans `ext-intl` |
|---|---|---|
| Un hôte de lettres ASCII minuscules, de chiffres et de traits d'union, `example.com` | Lu et affiché sans aucune conversion IDNA | Pareil, à la même vitesse |
| Un hôte international, `bücher.example` | Converti en ASCII par ICU, environ 2 µs par adresse | Converti par le polyfill en PHP, environ 15 fois plus lentement : quelque 30 µs par adresse |
| `display()` d'un hôte international | Dans ses propres lettres, `bücher.example`, sauf si le vérificateur d'usurpation d'ICU le juge suspect | En ASCII, `xn--bcher-kva.example` : sans vérificateur d'usurpation, aucun hôte international n'est jugé sûr à montrer dans ses propres lettres |

Une application dont les gens saisissent des adresses internationales, ou qui en montre, charge `ext-intl`.

Tant que le paquet n'est pas publié, il est résolu à partir du monodépôt PHIL par un dépôt `path` ou `vcs`.
