# Doctrine

With DoctrineBundle, the bundle registers the column type `phil_opening_hours`: `OpeningHours` in one JSON column,
as `phil/opening-hours`'s `OpeningHoursArray` writes them.

```php
#[ORM\Column(type: 'phil_opening_hours', nullable: true)]
private ?OpeningHours $hours = null;
```

A value that is not `OpeningHours` is refused on writing; a stored text that is no JSON, nested more than 6 levels,
or no hours, on reading.
