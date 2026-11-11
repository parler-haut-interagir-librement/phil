<!-- phil-dev source-sha256=1331a72976393cc5d088527029ddd136402779ca71bbca9a6b4a2f3cc7300e53 -->
# Installation

## Prérequis

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que la bibliothèque déclare et sur lequel elle est testée |
| `ext-json` | toute | Lire et écrire le JSON UJO |
| `ext-mbstring` | toute | Compter les caractères d'un texte selon sa limite |

## Installer

```sh
composer require phil/ujo
```

La bibliothèque n'enregistre rien et ne lit aucune configuration. Chaque classe est une valeur ou un service sans
état : construisez `Ujo`, ou ses parties, là où il le faut, ou déclarez-les comme services. Dans une application
Symfony, `phil/ujo-bundle` les déclare.
