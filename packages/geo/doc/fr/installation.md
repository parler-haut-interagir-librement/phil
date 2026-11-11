<!-- phil-dev source-sha256=225553cc1a2ecfa88842134e810e8b8e5c8fda6c1ec38da8d61f594645c2d2aa -->
# Installation

| Prérequis | Version | Raison |
|---|---|---|
| PHP | 8.5 ou plus récent, avec l'extension filter | Le plancher que la bibliothèque déclare et sur lequel elle est testée |
| `symfony/http-client` | ^8.2, facultatif | Les adaptateurs de géocodage |
| `symfony/cache` | ^8.2, facultatif | `CachedGeocoder` |

```sh
composer require phil/geo
```

Tant que le paquet n'est pas publié, il est résolu depuis le monodépôt PHIL par un dépôt `path` ou `vcs`.
