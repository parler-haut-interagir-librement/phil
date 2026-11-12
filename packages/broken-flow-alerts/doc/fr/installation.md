<!-- phil-dev source-sha256=97bf2dee0c0b7010464f12a84abfaa1201ca32ecead4682e093d4c775e975a6a -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Classes en lecture seule, constantes typées, `new` sans parenthèses |
| `psr/clock` | Le dernier jour UTC entier, depuis l'horloge de l'application |
| `symfony/notifier` 8.2, facultatif | `Notifier\NotifierAlertSender`, les alertes comme notifications |

## Installer

La bibliothèque est en `0.1.x-dev` et n'est pas encore publiée sur Packagist. D'ici là, déclarez un dépôt `path` vers une
copie locale, ou un dépôt `vcs`, puis requérez-la :

```bash
composer require phil/broken-flow-alerts:^0.1@dev
```

C'est une bibliothèque, pas un bundle : il n'y a rien à enregistrer. Construisez un `BrokenFlowWatch` avec le
`CompletionHistory` de l'application, un `AlertSender` et une horloge PSR-20 — le service `clock` de Symfony, ou toute
autre —, et gardez-le ; il ne tient aucun état propre, et sert à autant de passages qu'il faut dans un *worker* de longue
durée.

## Dans une application Symfony

Avec `phil/analytics-bundle`, le bundle configure tout depuis sa configuration, les objectifs de ses sites servant de
parcours. Sans lui, déclarez les services :

```yaml
services:
    Phil\BrokenFlowAlerts\Notifier\NotifierAlertSender:
        arguments: ['@notifier']

    Phil\BrokenFlowAlerts\BrokenFlowRule:
        arguments: { $baselineDays: 28, $windowDays: 7, $significance: 0.001 }

    Phil\BrokenFlowAlerts\BrokenFlowWatch:
        arguments:
            - '@App\Monitoring\OrderCompletionHistory'           # your CompletionHistory
            - '@Phil\BrokenFlowAlerts\Notifier\NotifierAlertSender'
            - '@clock'
            - '@Phil\BrokenFlowAlerts\BrokenFlowRule'
```

Lancez ensuite `check()` une fois par jour, après minuit UTC, depuis un message planifié ou une tâche *cron*. Le lancer
plus souvent renvoie les mêmes alertes le même jour.
