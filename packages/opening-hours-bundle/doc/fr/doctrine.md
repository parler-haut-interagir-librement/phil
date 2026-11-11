<!-- phil-dev source-sha256=e089a92a492ca4fc3dea5da5d52ea4015f7f5a87a5cd463e8419ecf9edc7dd85 -->
# Doctrine

Avec DoctrineBundle, le bundle enregistre le type de colonne `phil_opening_hours` : des `OpeningHours` dans une seule
colonne JSON, tels que les écrit l'`OpeningHoursArray` de `phil/opening-hours`.

```php
#[ORM\Column(type: 'phil_opening_hours', nullable: true)]
private ?OpeningHours $hours = null;
```

Une valeur qui n'est pas un `OpeningHours` est refusée à l'écriture. Un texte stocké qui n'est pas du JSON, qui
s'imbrique sur plus de 6 niveaux ou qui ne contient pas d'heures est refusé à la lecture.
