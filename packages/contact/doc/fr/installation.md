<!-- phil-dev source-sha256=c4790237f1285383ded56c261656b869ef91a20f32e251e2398d30bc1a10ae6c -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que la bibliothèque déclare et sur lequel elle est testée |
| `ext-filter` | toute | La vérification des adresses de courriel |
| `ext-intl` | toute | Les domaines de courriel dans d'autres écritures, vérifiés sous leur forme ASCII ; la langue de la culture d'un nom |
| `ext-mbstring` | toute | Les textes mesurés en caractères ; les lignes vCard repliées entre deux caractères |
| `phil/phone`, `phil/url`, `phil/instant-messenger` | ^0.1@dev | Les numéros de téléphone, les adresses Web, les identifiants de messagerie |
| `phil/address` | ^0.1@dev | Les adresses postales d'une fiche JSContact |
| `phil/enums` | ^0.3@dev | Les ensembles de services de messagerie et de sortes d'adresses que le lecteur JSContact accepte |

```sh
composer require phil/contact
```
