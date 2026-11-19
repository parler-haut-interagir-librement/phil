<!-- phil-dev source-sha256=586d4ed9cc79a202f7caae4c25bee6f62674c6955fbecc76828f0fe6638caec0 -->
# Stockage

## Les ports

| Port | Garde | Demandé pour |
|---|---|---|
| `Contract\HabitStore` | Les habitudes | `get()`, `ofMember()` par identifiant, `save()` à la place de celle qui est gardée, `remove()` |
| `Contract\CheckInStore` | Les validations, au plus une par habitude et par jour | `get()`, `between()` les plus anciennes d'abord, `save()` à la place de celle du jour, `remove()`, `removeHabit()` |

Un dépôt ne garde aucun état entre les requêtes autre que ce qu'il stocke, de sorte que `Habits` reste sûr dans un
*worker*.

## En mémoire

`Memory\InMemoryHabitStore` et `Memory\InMemoryCheckInStore` gardent tout dans des tableaux, perdus avec l'objet : pour
les tests, les démonstrations et un processus unique.

## Sur Doctrine DBAL

`Dbal\DbalHabitStore` et `Dbal\DbalCheckInStore` gardent les habitudes et les validations dans deux tables d'une
connexion Doctrine DBAL. Ils fonctionnent sur SQLite, MySQL et PostgreSQL, et sont testés sur SQLite, MySQL 8.4 et
PostgreSQL 17.

| Table | Colonnes | Clé et index |
|---|---|---|
| `phil_habit` | `id`, `member`, `name`, `schedule` (sa règle), `time_zone` (son nom), `starts_on`, `target`, `archived_on` | Clé primaire sur `id` ; index sur `member` |
| `phil_habit_check_in` | `habit`, `day`, `status`, `value`, `recorded_at` (un horodatage Unix, en UTC) | Clé primaire sur `habit` et `day` |

Les jours sont gardés comme des dates, sans heure ni fuseau. Les identifiants sont gardés comme de l'ASCII visible et
comparés octet pour octet : sur MySQL, dont la collation par défaut ignore la casse, leurs colonnes prennent la
collation `ascii_bin`. La clé primaire des validations sert le progrès d'une habitude en un seul balayage de plage.

Chaque dépôt ajoute sa table à un schéma qui en est dépourvu, sous le nom qu'on lui a donné — `phil_habit` et
`phil_habit_check_in` par défaut :

```php
use Doctrine\DBAL\Schema\Schema;

$habits = new DbalHabitStore($connection);
$checkIns = new DbalCheckInStore($connection);

$schema = $checkIns->configureSchema($habits->configureSchema(new Schema()));
```

Une migration de Doctrine Migrations les appelle sur le schéma qu'on lui donne ; une application sur l'ORM les appelle
depuis un écouteur de `postGenerateSchema`, pour que `doctrine:schema:update` et `make:migration` voient les tables. Un
nom de table est vérifié avant d'atteindre SQL : jusqu'à 63 lettres minuscules, chiffres ou `_`, sans commencer par un
chiffre.

Enregistrer une habitude ou une validation remplace la ligne de la même clé en une seule transaction. Une ligne que les
dépôts n'ont pas écrite — une colonne du mauvais type, un statut inconnu — est refusée avec `UnexpectedValueException`
plutôt que lue comme autre chose.
