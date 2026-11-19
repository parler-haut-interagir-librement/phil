<!-- phil-dev source-sha256=8b7755a66cb8db0ac9e388e9ab5228bd5ae18ce5e8fa8b6360b246e6693c674a -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Genre | Rôle |
|---|---|---|
| `Phil\Habits\Habits` | `final readonly class` | Le point d'entrée : démarrer, changer, archiver, valider, geler, annuler, lire le progrès et l'historique, lister et oublier les habitudes d'un membre |
| `Phil\Habits\Habit` | `final readonly class` | Une habitude : son membre, son nom, son horaire, son fuseau horaire, son premier jour, sa cible et son dernier jour ; `archive()`, `change()` |
| `Phil\Habits\Schedule` | `final readonly class` | Quand une habitude est due : `parse()`, les constructeurs, `toString()`, `periods()`, `streakUnit()` |
| `Phil\Habits\Frequency` | énumération | `daily`, `every`, `on`, `week`, `month` |
| `Phil\Habits\Period` | `final readonly class` | Une période d'un horaire : ses jours, les validations qu'elle demande, les jours qu'elle représente |
| `Phil\Habits\Day` | `final readonly class` | Un jour du calendrier : `fromString()`, `of()`, `plus()`, les comparaisons, sa semaine et son mois |
| `Phil\Habits\CheckIn` | `final readonly class` | Ce qu'un membre a fait un jour, enregistré en UTC |
| `Phil\Habits\CheckInStatus` | énumération | `done`, `partial`, `skipped`, `frozen` |
| `Phil\Habits\ProgressCalculator` | `final readonly class` | Tire les séries et la force des validations |
| `Phil\Habits\Progress` | `final readonly class` | Les séries, la force et la période courante d'une habitude ; `strengthPercent()` |
| `Phil\Habits\PeriodProgress` | `final readonly class` | Où en est une période : unités faites et demandées, jours excusés |
| `Phil\Habits\Streak` | `final readonly class` | La série courante et la meilleure, et leur unité |
| `Phil\Habits\StreakUnit` | énumération | `day`, `interval`, `week`, `month` |
| `Phil\Habits\HalfLifeStrength` | `final readonly class` | Le modèle de force par défaut, une moyenne à lissage exponentiel avec une demi-vie en jours |
| `Phil\Habits\Milestones` | `final readonly class` | Les longueurs de série et les forces que célèbrent les événements |
| `Phil\Habits\Contract\HabitStore` | interface | Où sont gardées les habitudes |
| `Phil\Habits\Contract\CheckInStore` | interface | Où sont gardées les validations |
| `Phil\Habits\Contract\StrengthModel` | interface | Comment la force bouge à chaque période |
| `Phil\Habits\Memory\InMemoryHabitStore`, `InMemoryCheckInStore` | `final class` | Les dépôts en mémoire |
| `Phil\Habits\Dbal\DbalHabitStore`, `DbalCheckInStore` | `final readonly class` | Les dépôts sur Doctrine DBAL, avec `configureSchema()` |
| `Phil\Habits\Event\HabitChecked`, `HabitUnchecked`, `StreakReached`, `StrengthReached` | `final readonly class` | Les événements, qui désignent l'habitude et son membre |
| `Phil\Habits\Exception\HabitsException` | interface | Toute exception du paquet |
| `Phil\Habits\Exception\InvalidHabit` | `final class` | Une habitude, un nom de table ou un réglage hors des bornes |
| `Phil\Habits\Exception\InvalidSchedule` | `final class` | Une règle ou un nombre d'horaire hors des bornes |
| `Phil\Habits\Exception\InvalidCheckIn` | `final class` | Une validation ou un gel sur un jour que l'habitude n'accepte pas |
| `Phil\Habits\Exception\InvalidDay` | `final class` | Un jour qui n'est pas écrit `YYYY-MM-DD`, ou qui n'est pas dans le calendrier |
| `Phil\Habits\Exception\HabitNotFound` | `final class` | Aucune habitude n'a cet identifiant |

`Phil\Habits\Dbal\TableName` est interne aux dépôts en base de données.
