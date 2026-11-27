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

## How the mapping is declared

The mapping is written in Doctrine attributes, on `Infrastructure\Doctrine\CoordinatesEmbeddable`: `#[ORM\Embeddable]`,
and an `#[ORM\Column]` of type `float` on `latitude` and on `longitude`. `phil/geo` is a plain PHP library that does
not know Doctrine, so the attributes cannot sit on `Coordinates` itself; `CoordinatesEmbeddable` mirrors its two
properties and is never instantiated. `Infrastructure\Doctrine\CoordinatesMappingDriver` reads those attributes with
Doctrine's attribute driver and maps `Coordinates` with them.

When the application configures the ORM and `doctrine_mapping` is on, the bundle sets the parameter
`phil_geo.doctrine_mapping` (`PhilGeoBundle::DOCTRINE_MAPPING`), and its compiler pass adds the driver to the driver
chain of the default entity manager, for the class `Phil\Geo\Coordinates` alone. An application whose places live in
another entity manager sets `doctrine_mapping: false` and adds the driver itself, in the `build()` of its kernel:

```php
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Phil\Geo\Coordinates;
use Phil\GeoBundle\Infrastructure\Doctrine\CoordinatesMappingDriver;
use Symfony\Component\DependencyInjection\Definition;

protected function build(ContainerBuilder $container): void
{
    // The parameter holds the name of the entity manager.
    $container->setParameter('app.places_entity_manager', 'places');
    $container->addCompilerPass(new DoctrineOrmMappingsPass(
        new Definition(CoordinatesMappingDriver::class),
        [Coordinates::class],
        ['app.places_entity_manager'],
    ));
}
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
