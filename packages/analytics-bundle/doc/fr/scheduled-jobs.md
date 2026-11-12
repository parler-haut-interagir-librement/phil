<!-- phil-dev source-sha256=c541fa2f12e4893fc785e0d4329d54c5d75d8fe352307a2f856034b6d6218dc5 -->
# Tâches planifiées

Quatre tâches tournent une fois par jour, chacune quand son bloc est activé : la rétention des compteurs, les rapports
dus ce jour-là, la vérification des parcours surveillés, et celle du trafic des sites surveillés. Elles tournent sur l'horaire `phil_analytics` de Symfony
Scheduler :

```bash
bin/console messenger:consume scheduler_phil_analytics
```

| Message | *Handler* | Fait |
|---|---|---|
| `Application\Message\ApplyCounterRetention` | `ApplyCounterRetentionHandler` | Cumule en totaux mensuels les compteurs quotidiens qui ont passé leur rétention |
| `Application\Message\SendDueReports` | `SendDueReportsHandler` | Envoie les rapports hebdomadaires le lundi et les mensuels le premier |
| `Application\Message\CheckBrokenFlows` | `CheckBrokenFlowsHandler` | Alerte quand un objectif surveillé cesse d'être atteint, ou l'est de nouveau |
| `Application\Message\CheckTrafficAlerts` | `CheckTrafficAlertsHandler` | Alerte quand les visites de la veille d'un site surveillé font un pic ou un creux |

**Les messages ne portent rien.** Chaque *handler* lit le jour UTC depuis le service `clock` quand il tourne : un
message retardé après minuit travaille sur le jour où il est traité, et rien de personnel ne passe jamais par un
transport.

**Une fois par jour, à `schedule.time` en UTC** (`05:00` par défaut) : assez tard pour que la veille soit entière
partout où les compteurs sont écrits, assez tôt pour un rapport lu le matin. Avec `schedule.enabled: false`, ou sans
`symfony/scheduler`, aucun horaire n'est enregistré ; les *handlers* restent, et l'application envoie les messages selon
son propre horaire.

## Rétention

`CounterRetentionRun::runFor($today)` demande à `CounterRollup::rollUpBefore()` de cumuler les jours avant
`CounterRetention::firstDailyDay($today)` — le premier du mois 13 mois plus tôt, ou `retention.months` plus tôt —, avec
le seuil des petits nombres configuré pour les dimensions ouvertes. La borne avance le premier de chaque mois : les
autres jours ne cumulent rien ([retention.md](retention.md)).

La tâche est configurée quand `retention.enabled` est vrai et que le stockage des compteurs a un cumul : le stockage
`dbal`, ou un stockage de l'application avec `counters.rollup`.

## Rapports

`DueReports::sendFor($today)` n'envoie rien la plupart des jours. Un lundi, chaque abonnement de cadence hebdomadaire
reçoit la dernière semaine entière, du lundi au dimanche ; le premier d'un mois, chaque abonnement de cadence mensuelle
reçoit le dernier mois entier. Un lundi qui est un premier envoie les deux.

```yaml
phil_analytics:
    reports:
        enabled: true
        subscriptions: App\Analytics\DbalReportSubscriptions   # ReportSubscriptions
        sender: App\Analytics\MailerReportSender               # ReportSender
```

Qui reçoit quoi et comment c'est écrit restent à l'application ([sharing-reports-alerts.md](sharing-reports-alerts.md)).

## Parcours interrompus

Un objectif qui cesse d'être atteint est souvent une page cassée : un fournisseur de paiement qui a changé sa réponse, un
formulaire dont le bouton ne soumet plus, une redirection qui tourne en boucle. Avec `phil/broken-flow-alerts`, le
bundle surveille les objectifs qu'on lui donne et alerte quand l'un est atteint bien moins souvent que son rythme le
laisse attendre :

```yaml
phil_analytics:
    broken_flows:
        enabled: true
        flows:
            checkout: { site: spring-con, goal: ticket, label: 'Ticket sales' }
            contact: { site: spring-con, goal: contact }
```

**Passif seulement.** La vérification lit les compteurs d'objectifs que la collecte tient déjà (`goals`, dimension
`goal`), par `GoalCompletionHistory` ; elle n'envoie aucune requête au site, synthétique ou autre.

Les 35 derniers jours de chaque parcours sont lus, jour par jour : les 28 jours de la base fixent le rythme, jour de la
semaine par jour de la semaine, et les 7 derniers jours lui sont comparés comme un compte de Poisson. Un parcours
achalandé est interrompu après un seul jour silencieux ; un parcours atteint deux fois par jour en demande environ
quatre ; un parcours dont le jour habituel est proche de zéro n'est jamais interrompu, puisqu'un jour silencieux y est
ordinaire. L'alerte part le jour où le parcours s'interrompt, et une seconde quand il est de nouveau atteint. La règle et
ses réglages sont décrits dans la documentation de `phil/broken-flow-alerts`.

| Clé | Par défaut | Sens |
|---|---|---|
| `flows` | aucun | Les objectifs surveillés, par identifiant de parcours : `site`, `goal`, et le `label` que montre une alerte |
| `baseline_days` | `28` | Les jours qui fixent le rythme, de 7 à 366 |
| `window_days` | `7` | Les derniers jours qui lui sont comparés, de 1 à 31 |
| `significance` | `0.001` | À quel point la baisse doit être improbable dans un parcours qui fonctionne, au-dessus de 0 et sous 0.5 |
| `weekly` | `true` | Si chaque jour de la semaine a son propre rythme, à partir d'une base de deux semaines ou plus |
| `sender` | aucun | L'identifiant d'un `AlertSender` ; sans lui, `NotifierAlertSender` envoie par le service `notifier` |

Sans expéditeur, les alertes passent par Symfony Notifier comme notifications sans destinataire : importance haute pour
un parcours interrompu, basse pour un rétablissement, pour que la politique de canaux de `framework.notifier` décide où
va chacune (un salon de clavardage, par exemple). Une application qui veut des destinataires ou ses propres mots nomme
son propre `AlertSender`.

## Alertes de trafic

`TrafficAlertRun::checkFor($today)` lit, pour chaque site surveillé, les visites de la veille et des `baseline_days`
jours qui la précèdent, et remet chaque alerte de `TrafficAlertRule` au `TrafficAlertNotifier` que nomme l'application.
Un site dont les visites tombent à rien fait un creux : une collecte arrêtée se voit le lendemain matin au lieu de
ressembler à une semaine tranquille.

```yaml
phil_analytics:
    traffic_alerts:
        enabled: true
        sites: [spring-con]
        notifier: App\Analytics\ChatTrafficAlertNotifier      # TrafficAlertNotifier
```

| Clé | Par défaut | Sens |
|---|---|---|
| `sites` | aucun | Les références des sites surveillés ; au moins une |
| `notifier` | aucun | L'identifiant d'un `TrafficAlertNotifier` ; requis |
| `spike_percent` | `300` | Un jour à cette part du jour habituel ou plus est un pic ; plus de 100 |
| `dip_percent` | `30` | Un jour à cette part du jour habituel ou moins est un creux ; de 1 à 99 |
| `minimum_baseline` | `20` | Un site dont le jour habituel compte moins de visites ne lève aucune alerte |
| `baseline_days` | `28` | Les jours dont le jour habituel est la moyenne, de 7 à 366 |

La tâche lit les compteurs et n'écrit rien ; son travail est le nombre de sites surveillés fois les jours de référence,
une lecture par jour.
