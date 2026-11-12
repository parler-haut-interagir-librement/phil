<!-- phil-dev source-sha256=baa825a094c49921f246f57da4a19b4a34afb88a30b00cd4658b4a0ceeb062d5 -->
# Tableaux de bord, vues partagées, rapports et alertes

Les mesures d'un site, lues par ceux qui le font tourner, ouvertes à des personnes sans compte, envoyées selon un
horaire, ou signalées quand un jour se démarque.

État : les types et règles ci-dessous sont implémentés et testés, et les rapports et les alertes sont envoyés sur
l'horaire `phil_analytics` ([scheduled-jobs.md](scheduled-jobs.md)). Les pages et l'envoi des courriels ou des
notifications sont à l'application, par les contrats de [extension-points.md](extension-points.md).

## Tableaux de bord et plusieurs sites

`CounterReader` est ce que lit un tableau de bord : une métrique et une dimension sur une plage de jours UTC, sous la
règle des petits nombres, dans une `ReaderScope` que l'application construit à chaque requête d'après ses propres
règles d'accès.

```php
use Phil\AnalyticsBundle\Application\Reading\CounterReader;
use Phil\AnalyticsBundle\Domain\Reading\ReaderScope;

$scope = $isPlatformOperator
    ? ReaderScope::everySite()
    : ReaderScope::ofSites(...$sitesThisAccountAdministers);

$counts = $reader->read($scope, $site, MeasureNames::PAGEVIEWS, 'route', $from, $to);

if (null === $counts) {
    throw new NotFoundHttpException();                     // the same answer as a site that does not exist
}

$counts->shown;                                            // ['home' => 120, 'talk' => 48]
$counts->others;                                           // the "other" group, or null when it may not be shown

$network = $reader->readAcross($scope, $allSites, MeasureNames::SERVER_EVENTS, 'event', $from, $to);
```

**Un site hors de la portée se lit comme `null`**, pour que les administrateurs d'un site n'apprennent rien d'un
autre, pas même qu'il existe. `readAcross()` additionne les compteurs de plusieurs sites par valeur, puis applique la
règle des petits nombres aux sommes : une valeur trop petite pour s'afficher sur un site peut s'afficher une fois les
sites additionnés, et jamais la part d'un seul site. Un site que la portée ne couvre pas est laissé hors de la somme.

Une plage compte au plus 400 jours, les 13 mois de compteurs quotidiens et un peu plus ; les mois plus anciens se lisent
dans les cumuls mensuels de `CounterRollup::monthlyCounts()`. Le compte exact de chaque lien sortant est refusé ici :
il se lit par `LinkCountReader`, par son auteur et les administrateurs seulement.

## Liens de partage et tableau public

```php
$token = ShareToken::generate();                           // shown once
$views->save(SharedView::link($site, $token, $now, $now->modify('+30 days')));

$views->save(SharedView::publicBoard($site, $now));        // the site's public board

$view = $views->linkOfTokenDigest(ShareToken::fromString($fromUrl)->digest());

if (null === $view || !$view->isOpenAt($now)) {
    throw new NotFoundHttpException();
}

$views->save($view->revoked($now));                        // closed for good
```

**Seule l'empreinte d'un jeton est stockée** : quelqu'un qui lit la base de données ne peut pas ouvrir les vues.
`fromString()` refuse tout ce qui n'a pas la bonne forme avant toute recherche.

**Ce que montre une vue** passe par `SmallNumberSuppression`, et `SharedView::mayShow()` refuse les métriques de
`MeasureNames::PRIVATE_METRICS` : le compte exact des liens de chaque auteur, et ce que les gens cherchent.

## Rapports

`ReportPeriod::lastCompleteBefore($today, ReportCadence::Weekly)` est la dernière semaine entière, du lundi au dimanche
en UTC ; `ReportCadence::Monthly`, le dernier mois entier. Une fois par jour, `DueReports` lit
`ReportSubscriptions::ofCadence()` pour les cadences dues — hebdomadaire le lundi, mensuelle le premier — et passe
chaque abonnement au `ReportSender` avec sa période. Le destinataire d'un `ReportSubscription` est une référence opaque,
résolue à l'envoi du rapport. Un rapport lit ses compteurs par la règle des petits nombres, et laisse de côté les
métriques privées.

## Alertes sur un pic ou un creux

`TrafficAlertRule` compare un jour à la moyenne des jours qui le précèdent. Avec `traffic_alerts` activé,
`TrafficAlertRun` l'applique une fois par jour aux visites de la veille, pour chaque site surveillé, et remet chaque
alerte au `TrafficAlertNotifier` de l'application ([scheduled-jobs.md](scheduled-jobs.md#alertes-de-trafic)). La règle
seule :

```php
$rule = new TrafficAlertRule(spikePercent: 300, dipPercent: 30, minimumBaseline: 20);
$alert = $rule->evaluate($site, $day, $count, $previousDays);

if (null !== $alert) {
    $notifier->notify($alert);                             // $alert->kind, $alert->count, $alert->baseline
}
```

Un site dont le jour habituel est sous `minimumBaseline` ne déclenche aucune alerte : sur une poignée de visites, chaque
jour ressemble à un pic. Un pic à 300 % ou plus et un creux à 30 % ou moins du jour habituel sont les valeurs par défaut.
