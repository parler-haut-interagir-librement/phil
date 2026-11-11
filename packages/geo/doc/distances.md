# Distances

## The great-circle distance

`Coordinates::distanceTo()` is the haversine distance over a sphere of the Earth's mean radius, 6 371 008.8 m. It is
within half a percent of the distance over the ellipsoid: enough to sort places and to show "2.7 km", not to survey.

```php
$distance = $montreal->distanceTo($quebec);
$distance->meters;          // 233029.3
$distance->inKilometers();  // 233.03
$distance->inMiles();       // 144.8
```

`Distance` is zero meters or more, and finite; `kilometers()` and `miles()` build one, `isShorterThan()` and
`compare()` order them.

## Finding places near a point

A database finds places near a point in two steps: the bounding box narrows the search on an index, then the exact
distance keeps those really within reach.

```php
$box = BoundingBox::around($here, Distance::kilometers(10));
// WHERE latitude BETWEEN :south AND :north AND longitude BETWEEN :west AND :east
$near = array_filter($candidates, static fn (Store $store): bool => $store->position->distanceTo($here)->meters <= 10_000);
```

Near a pole, or when the box would cross the antimeridian, it takes every longitude: the query reads a wider band,
and the exact distance still decides.
