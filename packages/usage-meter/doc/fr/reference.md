<!-- phil-dev source-sha256=0f00a2e3b4961816594f9e302d887d830a4cee3e8b631f8c7acda6eefedb0d0b -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `Phil\UsageMeter\UsageMeter` | `final readonly class` | La bibliothèque en un seul objet : `consume()`, `record()`, `usage()`, `allows()`, `limit()`, `purge()` |
| `Phil\UsageMeter\Usage` | `final readonly class` | L'utilisation d'un compteur par un sujet dans la fenêtre courante |
| `Phil\UsageMeter\Limit` | `final readonly class` | Un nombre entier à partir de zéro, ou aucune limite |
| `Phil\UsageMeter\Meter` | `final readonly class` | Ce qui est compté, sa période et sa limite par défaut |
| `Phil\UsageMeter\Meters` | `final readonly class` | Les compteurs d'une application, par nom |
| `Phil\UsageMeter\Counter` | `final readonly class` | Un compteur d'un stockage : sujet, compteur, début, expiration |
| `Phil\UsageMeter\Increment` | enum | Ce qu'un stockage a fait d'une incrémentation : notée, refusée, doublon |
| `Phil\UsageMeter\UsageStore` | interface | L'endroit où vivent les compteurs |
| `Phil\UsageMeter\InMemoryUsageStore` | `final class` | Les compteurs d'un seul processus |
| `Phil\UsageMeter\LimitProvider` | interface | La limite d'un sujet pour un compteur, tirée de l'application |
| `Phil\UsageMeter\LimitChain` | `final readonly class` | Plusieurs fournisseurs de limites, le premier qui répond l'emportant |
| `Phil\UsageMeter\AnchorProvider` | interface | L'ancre propre à un sujet, pour les cycles de facturation |
| `Phil\UsageMeter\Period\PeriodPolicy` | interface | La façon dont un compteur découpe le temps |
| `Phil\UsageMeter\Period\Window` | `final readonly class` | La durée que couvre un quota, et le compteur courant |
| `Phil\UsageMeter\Period\CalendarPeriod` | `final readonly class` | Des jours, des semaines ISO, des mois ou des années dans un fuseau horaire |
| `Phil\UsageMeter\Period\CalendarUnit` | enum | Jour, semaine, mois ou année |
| `Phil\UsageMeter\Period\BillingCycle` | `final readonly class` | Des périodes de mois comptées à partir d'une ancre |
| `Phil\UsageMeter\Period\RollingWindow` | `final readonly class` | Les dernières secondes, en nombre donné, en compartiments |
| `Phil\UsageMeter\Dbal\DbalUsageStore` | `final readonly class` | Les compteurs dans deux tables d'une connexion Doctrine DBAL |
| `Phil\UsageMeter\Dbal\UsageSchemaListener` | `final readonly class` | Les tables ajoutées au schéma que génère l'ORM |
| `Phil\UsageMeter\Subscription\FeatureLimits` | `final readonly class` | Les limites écrites `usage:<meter>:<limit>` dans les fonctionnalités |
| `Phil\UsageMeter\Exception\UsageMeterException` | interface | Chaque exception de la bibliothèque |
| `Phil\UsageMeter\Exception\QuotaExceeded` | `final class` | Une action refusée avant de dépasser le quota |
| `Phil\UsageMeter\Exception\UnknownMeter` | `final class` | Un compteur qui n'a pas été déclaré |
| `Phil\UsageMeter\Exception\InvalidUsage` | `final class` | Une valeur avec laquelle la bibliothèque ne peut pas travailler |
