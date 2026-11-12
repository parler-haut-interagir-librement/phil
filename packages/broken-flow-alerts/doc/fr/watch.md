<!-- phil-dev source-sha256=b1af0f910d464e337355cf15147ce66cc3bda313b82f61e8c5bbc8e2a5582154 -->
# La surveillance et ses alertes

`BrokenFlowWatch::check()` applique la règle à chaque parcours qu'on lui donne et envoie les alertes des parcours dont
l'état a changé le dernier jour UTC entier.

```php
$watch = new BrokenFlowWatch($history, $sender, $clock);              // the default rule
$watch = new BrokenFlowWatch($history, $sender, $clock, new BrokenFlowRule(windowDays: 14));

$alerts = $watch->check([Flow::of('checkout', 'Ticket sales')]);      // list<FlowAlert>, already sent
```

## Parcours

Un `Flow` est un identifiant que comprend le `CompletionHistory` de l'application et un libellé que montre une alerte :
1 à 128 octets chacun, sans espaces autour ni caractères de contrôle. Le libellé est l'identifiant quand aucun n'est
donné.

## Les jours lus

Le dernier jour entier est le jour UTC qui précède l'instant de l'horloge : à 21 h 30 à Montréal le 11 novembre, déjà le
12 en UTC, c'est le 11. Pour chaque parcours, la surveillance demande à l'historique `historyDays() + 1` jours qui se
terminent ce jour-là — 36 avec la règle par défaut —, depuis minuit UTC :

```php
$history->dailyCompletions($flow, new DateTimeImmutable('2026-10-07 00:00 UTC'), 36);   // 2026-10-07 to 2026-11-11
```

Un historique d'une autre longueur est refusé avec `InvalidFlowValue`.

## Une alerte quand l'état change

La surveillance ne garde aucune trace des alertes passées. Elle applique la règle deux fois au même historique : une fois
aux jours qui se terminent le dernier jour entier, une fois aux jours qui se terminent la veille. Puis :

| La veille | Le dernier jour | Alerte |
|---|---|---|
| Pas interrompu | Interrompu | `Broken`, avec l'étendue, les aboutissements observés et attendus |
| Interrompu | Pas interrompu, et au moins un aboutissement le dernier jour | `Recovered`, avec les aboutissements du dernier jour |
| Interrompu | Pas interrompu, et aucun aboutissement le dernier jour | Aucune : le silence a duré assez longtemps pour devenir le rythme |
| Interrompu | Interrompu | Aucune : le parcours a été signalé le jour de son interruption |
| Pas interrompu | Pas interrompu | Aucune |

Un parcours est ainsi signalé une fois quand il s'interrompt, et une fois quand il aboutit de nouveau. **Un passage
répété le même jour renvoie les mêmes alertes** : planifiez un passage par jour.

## Ce que contient une alerte

| Propriété de `FlowAlert` | Sens |
|---|---|
| `flow` | Le `Flow` |
| `kind` | `FlowAlertKind::Broken` ou `FlowAlertKind::Recovered` |
| `day` | Le dernier jour entier, `YYYY-MM-DD` |
| `since` | Le premier jour de l'étendue que couvrent les nombres, `YYYY-MM-DD` |
| `observed` | Les aboutissements de `since` à `day` |
| `expected` | Les aboutissements qu'attend le rythme sur les mêmes jours |

## Notifications

`Notifier\NotifierAlertSender` envoie chaque alerte comme une `Notification` de Symfony Notifier :

| Alerte | Sujet | Contenu | Importance |
|---|---|---|---|
| Interrompu | `Flow "Ticket sales" stopped completing` | `3 completions from 2026-11-08 to 2026-11-11, where about 42 were expected.` | haute |
| Rétabli | `Flow "Ticket sales" completes again` | `12 completions on 2026-11-11.` | basse |

```php
new NotifierAlertSender($notifier);                                   // to the channels that need no recipient
new NotifierAlertSender($notifier, new Recipient('ops@example.com')); // to these recipients
```

L'importance permet à la politique de canaux de l'application d'envoyer un parcours interrompu à un salon de clavardage
et à un téléphone, et un rétablissement à un salon seulement. Le texte est en anglais ; une application qui veut ses
propres mots, ou une traduction, écrit son propre `AlertSender` ([extension-points.md](extension-points.md)).
