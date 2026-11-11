# Doctrine

With the ORM configured and `doctrine_mapping` left on, `Phil\Geo\Coordinates` is an embeddable of two `float`
columns:

```php
#[ORM\Embedded(class: Coordinates::class)]
public Coordinates $position;   // columns position_latitude and position_longitude
```

Index both columns to search near a point: narrow on the bounding box, then keep the exact distance.

```php
$box = BoundingBox::around($here, Distance::kilometers(10));
$candidates = $repository->createQueryBuilder('s')
    ->where('s.position.latitude BETWEEN :south AND :north')
    ->andWhere('s.position.longitude BETWEEN :west AND :east')
    ->setParameters(['south' => $box->south, 'north' => $box->north, 'west' => $box->west, 'east' => $box->east])
    ->getQuery()->getResult();
```

## An optional position

The embeddable is a position always: its two columns are `NOT NULL`, and Doctrine cannot leave an embedded object
out. A property typed `?Coordinates` and embedded would write `NULL` into those columns. A place that may have no
position keeps two nullable columns of its own and builds the `Coordinates` when it has both:

```php
#[ORM\Column(nullable: true)]
private ?float $latitude = null;

#[ORM\Column(nullable: true)]
private ?float $longitude = null;

public function getPosition(): ?Coordinates
{
    return null === $this->latitude || null === $this->longitude ? null : Coordinates::of($this->latitude, $this->longitude);
}

public function setPosition(?Coordinates $position): void
{
    [$this->latitude, $this->longitude] = [$position?->latitude, $position?->longitude];
}
```

`CoordinatesType` with `'required' => false` binds to `position` through these two methods. The bounding-box query
above reads `s.latitude` and `s.longitude`, and skips the places without a position.

## Near the visitor

Searching near the visitor sends the visitor's position to the server; to keep it in the browser, publish the
places — blurred if need be — and let [the controller](distance-sort.md) sort them.
