# Shared views, reports and alerts

The measures of a site, read by those who run it, opened to people without an account, sent on a schedule, or raised
when a day stands out.

State: the types and rules below are implemented and tested, and the reports and the alerts are sent on the schedule
`phil_analytics` ([scheduled-jobs.md](scheduled-jobs.md)). Pages and the sending of mail or notifications are the application's,
through the contracts in [extension-points.md](extension-points.md).

## Dashboards and several sites

`CounterReader` is what a dashboard reads: one metric and one dimension over a range of UTC days, under the
small-number rule, within a `ReaderScope` the application builds from its own access rules for each request.

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

**A site outside the scope reads as `null`**, so that the administrators of one site learn nothing of another, not
even whether it exists. `readAcross()` sums the counters of several sites per value, then applies the small-number
rule to the sums: a value too small to show on one site can show once the sites are added up, and never one site's
share of it. A site the scope does not cover is left out of the sum.

A range is at most 400 days, the 13 months of daily counters and a little more; older months are read from the
monthly totals of `CounterRollup::monthlyCounts()`. The exact count of each outbound link is refused here: it is read
through `LinkCountReader`, by its author and the administrators only.

## Share links and the public board

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

**Only the digest of a token is stored**, so a reader of the database cannot open the views. `fromString()` refuses
anything that has the wrong shape before any lookup.

**What a view shows** goes through `SmallNumberSuppression`, and `SharedView::mayShow()` refuses the metrics of
`MeasureNames::PRIVATE_METRICS`: the exact count of each author's links, and what people search for.

## Reports

`ReportPeriod::lastCompleteBefore($today, ReportCadence::Weekly)` is the last whole week, Monday to Sunday in UTC;
`ReportCadence::Monthly` the last whole month. Once a day, `DueReports` reads `ReportSubscriptions::ofCadence()` for the
cadences due — weekly on a Monday, monthly on the first — and hands each subscription to the `ReportSender` with its
period. The recipient of a `ReportSubscription` is an opaque reference, resolved when the report is
sent. A report reads its counters through the small-number rule, and leaves out the private metrics.

## Alerts on a spike or a dip

`TrafficAlertRule` compares a day with the mean of the days before it. With `traffic_alerts` enabled, `TrafficAlertRun`
applies it once a day to the visits of the day before, for each watched site, and hands each alert to the application's
`TrafficAlertNotifier` ([scheduled-jobs.md](scheduled-jobs.md#traffic-alerts)). The rule on its own:

```php
$rule = new TrafficAlertRule(spikePercent: 300, dipPercent: 30, minimumBaseline: 20);
$alert = $rule->evaluate($site, $day, $count, $previousDays);

if (null !== $alert) {
    $notifier->notify($alert);                             // $alert->kind, $alert->count, $alert->baseline
}
```

A site whose usual day is under `minimumBaseline` raises no alert: on a handful of visits, every day looks like a spike.
A spike at or above 300 % and a dip at or below 30 % of the usual day are the defaults.
