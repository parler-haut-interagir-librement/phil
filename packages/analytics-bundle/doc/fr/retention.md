<!-- phil-dev source-sha256=28aaaa48b44b96614b6d03c6539a6a445ddb7b61026f8bc990dde490521b3c57 -->
# Rétention

Les compteurs quotidiens sont gardés pendant 13 mois entiers, puis seuls leurs totaux mensuels restent. Un mois peut
alors être comparé au même mois un an plus tôt, sans que le détail de chaque jour s'attarde.

État : `CounterRetention`, `UtcMonth` et le cumul par défaut, `DbalCounterRollup`, sont implémentés et testés. Le bundle
lance le cumul une fois par jour sur l'horaire `phil_analytics` ([scheduled-jobs.md](scheduled-jobs.md#rétention)) ;
réglez `retention.months` pour garder les jours plus ou moins longtemps.

```php
$retention = new CounterRetention();                        // 13 months; new CounterRetention(6) for 6
$bound = $retention->firstDailyDay(UtcDay::of($now));       // 2025-10-01 on 2026-11-09

$rollup->rollUpBefore($bound, $threshold);
```

La borne est toujours le premier jour d'un mois : seuls des mois entiers sont cumulés.

`rollUpBefore()` ajoute les jours avant la borne à leurs totaux mensuels et les supprime, en une seule transaction. Le
total mensuel d'une dimension ouverte (`MeasureNames::OPEN_DIMENSIONS` : les hôtes et les termes de recherche) sous le
seuil n'est pas gardé : un terme rare est oublié plutôt que stocké pour de bon.

Pour lire plus loin que les compteurs quotidiens, `monthlyCounts()` additionne les totaux d'une plage de mois. La règle
des petits nombres s'applique comme à toute lecture.
