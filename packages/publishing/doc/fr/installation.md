<!-- phil-dev source-sha256=7b24c6da882dd33eec027d7e4ade068d104fabe8dcf34360a38849dc08b7e224 -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que la bibliothèque déclare et sur lequel elle est testée ; le contrat déclare ses champs comme propriétés d'interface |
| `phil/url` | ^0.1@dev | `Url`, l'adresse canonique d'une page, absolue et normalisée |

```sh
composer require phil/publishing
```

Tant que le paquet n'est pas publié, il est résolu depuis le monodépôt de PHIL par un dépôt `path` ou `vcs`.

La bibliothèque ne déclare aucun service et n'a besoin d'aucune configuration. Les événements passent par le répartiteur
de l'application : l'EventDispatcher de Symfony, ou n'importe quel répartiteur PSR-14.
