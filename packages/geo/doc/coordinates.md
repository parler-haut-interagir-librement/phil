# Coordinates

`Coordinates` is a point on the Earth in decimal degrees, WGS 84, as GPS and maps give them.

```php
$point = Coordinates::of(45.5017, -73.5673);
$point = Coordinates::fromString(' 45.5017 , -73.5673 ');
$point->latitude;     // 45.5017
$point->toString();   // "45.5017,-73.5673"
```

| Rule | Detail |
|---|---|
| Latitude | -90 to 90; anything else, or NaN, throws `InvalidCoordinates` |
| Longitude | -180 to 180; -180 is stored as 180, the same meridian, so equal places compare equal |
| Text | `latitude,longitude`, decimal degrees, up to three digits before the point, spaces around the comma |
| Writing | Up to seven decimals — about a centimetre —, trailing zeros dropped |
| Equality | `equals()` compares both values exactly |

## Blurring

`blurred($decimals)` rounds both values, 0 to 7 decimals. Publishing a place blurred hides its exact position while
keeping it sortable by distance:

| Decimals | About |
|---|---|
| 1 | 11 km |
| 2 | 1.1 km |
| 3 | 110 m |
| 4 | 11 m |
