<!-- phil-dev source-sha256=f06165105203599ab6b838ba27d621010a8c034492ba41e065154560b6f6476f -->
# phil/usage-meter

**Ce que chaque sujet a utilisé de ce qu'il peut utiliser, et un refus avant qu'une action ne lui fasse dépasser son
quota.**

phil/usage-meter compte ce qu'un sujet utilise (projets créés, exportations faites, jetons dépensés par un modèle,
requêtes envoyées) et le tient à un quota par période. Un compteur compte par jour, semaine, mois ou année du
calendrier dans un fuseau horaire, par périodes de facturation d'un nombre de mois ancrées sur une date (celle de
chaque sujet, quand il en a une), ou sur une fenêtre glissante découpée en compartiments. `consume()` vient avant une
action et la refuse si elle dépasserait le quota ; `record()` compte ce qui a déjà été utilisé et ne refuse jamais.
Les deux prennent une clé d'idempotence, pour qu'une requête reprise ne compte qu'une fois, et `refund()` rend ce
qu'une clé a compté quand l'action a échoué ensuite. La limite d'un sujet vient
de l'application (un forfait, une dérogation, les fonctionnalités d'un abonnement) ou de la valeur par défaut du
compteur ; le compte recommence à chaque frontière de période.

Il s'adresse à toute application PHP qui vend ou donne des niveaux d'utilisation : un site freemium dont le niveau
gratuit permet trois exportations par mois, un service facturé selon les jetons ou les requêtes de chaque période de
facturation, une API qui permet tant d'appels par heure, un outil qui plafonne les projets d'un compte. Avec
`phil/commerce-subscription-bundle`, les limites d'un abonné sont lues dans les fonctionnalités des offres qu'il
détient, écrites `usage:<meter>:<limit>`.

C'est une bibliothèque PHP simple dont le seul prérequis est `psr/clock`. Les compteurs vivent dans un
stockage : en mémoire pour les tests et les exécutions uniques, ou dans deux tables d'une connexion Doctrine DBAL sur
PostgreSQL, MySQL, MariaDB ou SQLite. Là, une incrémentation est une transaction autour d'un seul `UPDATE`
conditionnel : deux requêtes au même moment sont toutes deux comptées, ou l'une est refusée, jamais perdue. Les tables
rejoignent le schéma que génère l'ORM, pour qu'une migration les crée.

La conception est réduite : un `Meter` nomme ce qui est compté, sa `PeriodPolicy` (`CalendarPeriod`, `BillingCycle`,
`RollingWindow`) et sa `Limit` par défaut ; `Meters` les contient ; un `UsageStore` garde les `Counter` ;
`LimitProvider` et `AnchorProvider` sont les ports de l'application ; `UsageMeter` assemble le tout et répond par un
`Usage`, ou lance `QuotaExceeded`.

## État

| Partie | État |
|---|---|
| Compteurs, limites (un nombre ou aucune), périodes du calendrier dans un fuseau horaire, cycles de facturation avec les jours de fin de mois, fenêtres glissantes en compartiments | Implémenté et testé |
| `consume()` qui refuse avant que le quota soit dépassé, `record()` qui compte au-delà, `usage()`, `allows()`, `limit()` | Implémenté et testé |
| Clés d'idempotence : une incrémentation comptée une fois par sujet, compteur et clé ; une incrémentation refusée laisse sa clé libre | Implémenté et testé |
| Fournisseurs de limites en chaîne ; ancres par sujet | Implémenté et testé |
| Limites lues dans les fonctionnalités de `phil/commerce-subscription-bundle` | Implémenté et testé |
| Stockage en mémoire ; stockage Doctrine DBAL avec une incrémentation conditionnelle atomique, schéma et écouteur ORM, purge | Implémenté et testé |
| `refund()` de ce qu'une clé a compté, pour une action qui a ensuite échoué : borné, idempotent, atomique dans les deux stockages, testé sur MySQL 8.4 et PostgreSQL 17 | Implémenté et testé |
| Un bundle Symfony : compteurs tirés de la configuration, le stockage DBAL, limiteurs de débit sur les contrôleurs, purge en console | Implémenté dans `phil/usage-meter-bundle` |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation, déclaration des services dans Symfony. |
| [Compteurs et périodes](doc/fr/meters-and-periods.md) | `Meter`, `Meters`, les périodes du calendrier, les cycles de facturation et les ancres, les fenêtres glissantes. |
| [Comptage](doc/fr/counting.md) | `consume()`, `record()`, `refund()`, `usage()`, `allows()`, les clés d'idempotence, `Usage`. |
| [Limites](doc/fr/limits.md) | `Limit`, les fournisseurs de limites, la chaîne, les limites tirées des fonctionnalités d'abonnement. |
| [Stockage](doc/fr/storage.md) | Les stockages, les tables de la base de données, l'atomicité, l'écouteur de schéma, la purge. |
| [Exceptions](doc/fr/exceptions.md) | `UsageMeterException` et ses trois exceptions. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les arguments et les données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation, et comment tester du code qui compte. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/usage-meter
```

```php
use Phil\UsageMeter\InMemoryUsageStore;
use Phil\UsageMeter\Limit;
use Phil\UsageMeter\Meter;
use Phil\UsageMeter\Meters;
use Phil\UsageMeter\Period\CalendarPeriod;
use Phil\UsageMeter\Period\RollingWindow;
use Phil\UsageMeter\UsageMeter;
use Symfony\Component\Clock\NativeClock;

$meter = new UsageMeter(
    new Meters(
        new Meter('exports', CalendarPeriod::month('America/Toronto'), Limit::of(3)),
        new Meter('requests', RollingWindow::hours(1), Limit::of(500)),
    ),
    new InMemoryUsageStore(),
    new NativeClock(),
);

$meter->consume($accountId, 'exports', 1, $requestId); // before the export; QuotaExceeded when it does not fit

$usage = $meter->usage($accountId, 'exports');
printf('%d of %s used, until %s', $usage->used, $usage->limit->toString(), $usage->resetsAt->format('Y-m-d'));
```

## Prérequis

PHP 8.5 ou plus récent et `psr/clock`. `doctrine/dbal` pour le stockage en base de données, `doctrine/orm` pour
l'écouteur de schéma, `phil/commerce-subscription-bundle` pour les limites lues dans les fonctionnalités d'abonnement,
chacun seulement s'il est utilisé.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
