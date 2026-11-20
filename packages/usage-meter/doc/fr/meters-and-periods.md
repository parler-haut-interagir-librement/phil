<!-- phil-dev source-sha256=e6e4e58138458d4aa1d416ce71d9b442d7e97e16891996563725bb83af1ccd64 -->
# Compteurs et périodes

## Compteurs

Un `Meter` nomme ce qui est compté, la façon dont le temps est découpé pour lui, et la limite d'un sujet qu'aucun
fournisseur de limites ne nomme, habituellement celle du niveau gratuit :

```php
use Phil\UsageMeter\Limit;
use Phil\UsageMeter\Meter;
use Phil\UsageMeter\Meters;
use Phil\UsageMeter\Period\CalendarPeriod;

$meters = new Meters(
    new Meter('projects', CalendarPeriod::year(), Limit::of(3)),
    new Meter('ai.tokens', CalendarPeriod::month(), Limit::of(50_000)),
);
```

Un nom compte jusqu'à 64 lettres minuscules, chiffres, `_`, `.` ou `-`, et commence par une lettre ou un chiffre ;
c'est ce que garde le stockage et ce que nomme une fonctionnalité d'abonnement. `Meters` refuse un nom déclaré deux
fois ; `get()` lance `UnknownMeter` pour un nom qu'il ne contient pas, `has()` le demande, `names()` les énumère dans
l'ordre.

## Fenêtres

À tout moment, la période d'un compteur donne une `Window` : le premier instant compté (`start`), le moment où la
fenêtre change de nouveau (`end`), et le compteur qui reçoit ce qui est compté maintenant (`counterStart`,
`counterEnd`). Pour une période du calendrier ou de facturation, la fenêtre est la période et son compteur unique. Pour
une fenêtre glissante, les compteurs sont des compartiments.

## Périodes du calendrier

`CalendarPeriod` compte par `CalendarUnit` (jour, semaine ISO à partir du lundi, mois ou année) à partir de minuit,
heure locale, dans un fuseau horaire, UTC par défaut. Tous les sujets recommencent ensemble, le premier du mois dans ce
fuseau :

```php
CalendarPeriod::day('Europe/Paris');
CalendarPeriod::week();
CalendarPeriod::month('America/Toronto');
CalendarPeriod::year();
```

Un jour où le décalage horaire change dure 23 ou 25 heures ; la période suit l'heure murale. Un fuseau inconnu lance
`InvalidUsage`.

## Cycles de facturation

`BillingCycle` compte des périodes de 1 à 120 mois à partir d'une ancre, dans le fuseau horaire de l'ancre : un
abonnement pris le 14 mars à 10 h 30 commence une nouvelle période le 14 de chaque mois à 10 h 30.

```php
use Phil\UsageMeter\Period\BillingCycle;

BillingCycle::monthly(new DateTimeImmutable('2026-03-14 10:30', new DateTimeZone('America/Toronto')));
BillingCycle::yearly($anchor);
new BillingCycle($anchor, 3); // quarterly
```

Une ancre le 29, le 30 ou le 31 tombe le dernier jour d'un mois plus court, puis revient à son propre jour ensuite :
31 janvier, 28 février, 31 mars, 30 avril. Un moment avant l'ancre appartient aux périodes qui la précèdent, comptées de
la même façon.

**L'ancre propre à chaque sujet.** Les abonnés partagent rarement un même jour de facturation. Un `AnchorProvider`
donne l'ancre d'un sujet (le jour où il s'est abonné, lu dans l'application), et elle l'emporte sur celle avec
laquelle le cycle a été construit, qui sert aux sujets qui n'en ont pas :

```php
use Phil\UsageMeter\AnchorProvider;
use Phil\UsageMeter\Meter;

final readonly class SubscriptionAnchors implements AnchorProvider
{
    public function anchorFor(string $subject, Meter $meter): ?DateTimeImmutable
    {
        return $this->subscriptions->startOf($subject); // null: the cycle's own anchor
    }
}

new UsageMeter($meters, $store, $clock, anchors: new SubscriptionAnchors(...));
```

Les périodes du calendrier et les fenêtres glissantes ignorent les ancres.

## Fenêtres glissantes

`RollingWindow` compte les dernières secondes, en nombre donné, découpées en 1 à 1440 compartiments qui divisent la
fenêtre exactement. Une fenêtre de 24 heures en 24 compartiments compte l'heure courante et les 23 précédentes, et
libère l'utilisation d'une heure quand cette heure quitte la fenêtre :

```php
use Phil\UsageMeter\Period\RollingWindow;

RollingWindow::minutes(10); // 10 buckets of one minute
RollingWindow::hours(24);   // 24 buckets of one hour
RollingWindow::days(30);    // 30 buckets of one day
new RollingWindow(3600);    // one bucket: fixed blocks of an hour
```

Les compartiments sont alignés sur l'époque Unix : tous les sujets les partagent et ils ne dépendent jamais d'un fuseau
horaire. Plus il y a de compartiments, plus la fenêtre glisse près de la seconde, et plus un sujet détient de
compteurs à la fois. Le `Usage::$resetsAt` d'une fenêtre glissante est le moment où le plus ancien compartiment la
quitte : le moment où une partie de l'utilisation est libérée, pas toute.
