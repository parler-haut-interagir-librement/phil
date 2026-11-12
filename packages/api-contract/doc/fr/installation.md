<!-- phil-dev source-sha256=a1f14f9745d3601e623eaf5236a0f0a4ec15320f7edb952f8437fb808a827937 -->
# Installation

| Prérequis | Contrainte | Pourquoi |
|---|---|---|
| PHP | `>=8.5` | Le minimum déclaré, la version sur laquelle la bibliothèque est testée. |

```bash
composer require phil/api-contract:^0.1@dev
```

La bibliothèque ne dépend que de PHP : les en-têtes et les corps lui sont transmis sous forme de chaînes. Dans
une application Symfony, exigez plutôt `phil/api-contract-bundle`, qui exige la bibliothèque.
