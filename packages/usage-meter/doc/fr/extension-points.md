<!-- phil-dev source-sha256=6b22c122cfceae13d3cac01c9d8c9fdccba6c563e15f172cf14e8e44a4f5e319 -->
# Points d'extension

La bibliothèque est du PHP simple et ne déclare aucun service. `UsageMeter` prend quatre ports et une horloge ; chacun
a une implémentation fournie et peut être celui de l'application.

## `UsageStore`

`Phil\UsageMeter\UsageStore` : l'endroit où vivent les compteurs. `InMemoryUsageStore` garde ceux d'un processus,
`Dbal\DbalUsageStore` deux tables d'une connexion Doctrine DBAL. Voir [Stockage](storage.md).

| Méthode | Contrat |
|---|---|
| `add(Counter $counter, int $amount, ?int $ceiling = null, ?string $key = null): Increment` | Ajoute de façon atomique : sous un plafond, deux incrémentations au même moment sont toutes deux comptées ou l'une est refusée, jamais perdue ; une clé notée une fois n'est jamais comptée deux fois (`Increment::Duplicate`) ; une incrémentation refusée ne laisse derrière elle ni sa quantité ni sa clé |
| `total(string $subject, string $meter, DateTimeImmutable $from, DateTimeImmutable $before): int` | La somme des compteurs qui commencent dans la fenêtre |
| `purge(DateTimeImmutable $now): int` | Oublie les compteurs et les clés qu'aucune fenêtre ne lit plus ; retourne leur nombre |

Un stockage propre à l'application (Redis, par exemple) tient les trois promesses de `add()`, sinon les quotas cessent
d'en être, et la promesse de `refund()` : jamais plus rendu que ce qu'une clé a compté, peu importe combien de fois on le
demande.

## `LimitProvider`

`Phil\UsageMeter\LimitProvider` : la limite d'un sujet pour un compteur, lue à chaque appel. Voir [Limites](limits.md).

| Méthode | Contrat |
|---|---|
| `limitFor(string $subject, Meter $meter): ?Limit` | La limite du sujet, ou `null` pour laisser répondre le fournisseur suivant, puis la limite propre au compteur |

`LimitChain` en interroge plusieurs tour à tour ; `Subscription\FeatureLimits` lit les limites écrites
`usage:<meter>:<limit>` dans les fonctionnalités d'un abonnement.

## `AnchorProvider`

`Phil\UsageMeter\AnchorProvider` : l'ancre propre à un sujet pour les cycles de facturation. Voir
[Compteurs et périodes](meters-and-periods.md).

| Méthode | Contrat |
|---|---|
| `anchorFor(string $subject, Meter $meter): ?DateTimeImmutable` | Le jour à partir duquel compte le cycle du sujet, ou `null` pour l'ancre propre au cycle |

## `PeriodPolicy`

`Phil\UsageMeter\Period\PeriodPolicy` : la façon dont un compteur découpe le temps. `CalendarPeriod`, `BillingCycle`
et `RollingWindow` sont fournis.

| Méthode | Contrat |
|---|---|
| `windowAt(DateTimeImmutable $at, ?DateTimeImmutable $anchor = null): Window` | La fenêtre qui contient le moment, et le compteur dans lequel il est compté ; une politique sans ancre ignore `$anchor` |

## L'horloge

N'importe quelle `ClockInterface` PSR-20 : l'horloge de l'application en production, une horloge figée dans les tests.
