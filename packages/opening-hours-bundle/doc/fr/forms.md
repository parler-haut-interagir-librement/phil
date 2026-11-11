<!-- phil-dev source-sha256=fcb62dad5dd5aa5240cd9587941382ac531f60ad406843e1f34a2bbde13eda3d -->
# Formulaires

`OpeningHoursType`, vers `OpeningHours` et à partir de celui-ci :

- un champ par jour, de `monday` à `sunday`, nommé par ICU dans la langue de la requête, où l'on saisit les plages du
  jour — `09:00-12:00, 13:00-17:00`, rien quand il est fermé ;
- `specials`, les heures spéciales, une période par ligne, `2026-12-25 # Christmas` ;
- `timezone`, un choix de fuseaux horaires.

Un champ illisible est invalide à lui seul, avec un message qui montre comment le remplir. Des heures sans jour ouvert
et sans heures spéciales valent `null`.

```php
$builder->add('hours', OpeningHoursType::class, ['timezone' => 'Europe/Paris', 'with_timezone' => false]);
```

| Option | Valeur par défaut | Signifie |
|---|---|---|
| `timezone` | `null` : `default_timezone` | Le fuseau horaire des nouvelles heures |
| `with_timezone` | `true` | Si les gens choisissent le fuseau horaire |
| `locale` | `null` : celle de la requête | La langue dans laquelle les jours sont nommés |

Les libellés se trouvent dans le domaine de traduction `phil_opening_hours`, en anglais et en français ; les jours
n'en ont pas besoin.
