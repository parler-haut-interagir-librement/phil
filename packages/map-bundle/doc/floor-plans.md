# Floor plans

A floor plan is an image with booths drawn over it: a trade show's hall, a venue, a campus.

```php
use Phil\MapBundle\Domain\ValueObject\Booth;
use Phil\MapBundle\Domain\ValueObject\FloorPlan;
use Phil\MapBundle\Domain\ValueObject\SiteAddress;

$plan = FloorPlan::of(SiteAddress::of('/plans/hall-a.png'), 4000, 2500, [
    Booth::of('a1', 'Booth A1', 120, 80, 300, 200, resource: $resourceId),
    Booth::of('a2', 'Booth A2', 440, 80, 300, 200),
], tiles: SiteAddress::of('/plans/hall-a/{z}/{x}/{y}.png'));

$view = $maps->floorPlan('Hall A', $plan, new DateTimeImmutable('2027-05-14 09:00'), new DateTimeImmutable('2027-05-16 18:00'));
```

## The plan

`FloorPlan::of(image, width, height, booths, tiles)` takes the image's address and its size in pixels, from 1 to
1,000,000 a side, up to 2,000 booths, and the template of the tiles cut from it, when there are. A booth that goes past
the edge, two booths of one identifier, and a template without `{z}`, `{x}` and `{y}` are refused.

The plan is drawn on Leaflet's flat reference system, `L.CRS.Simple`: no projection, no tiles of the world. Its scale is
2^-n map units a pixel, n being the smallest that fits the image in 180 units of width and 90 of height; at zoom n, one
pixel of the screen is one pixel of the image. Its top-left corner is at the origin, down is south and right is east. The
controller fits the map to the plan and keeps it within.

## Booths

`Booth::of(id, label, x, y, width, height, resource)` is a rectangle of the image, in pixels from its top-left corner,
with the identifier of the `phil/reservation` resource it is booked as, when it is. Each booth is drawn as a rectangle
whose class is its state, and listed with it.

| State | Means | Class |
|---|---|---|
| `free` | One unit can be booked over the whole period | `phil-map-booth isFree` |
| `taken` | Refused because the resource is full | `phil-map-booth isTaken` |
| `closed` | Refused for another reason: closed then, too soon, off the grid | `phil-map-booth isClosed` |
| `unknown` | No period, no resource, or a resource that does not exist | `phil-map-booth isUnknown` |

The states come from a `BoothAvailability`. With `phil/reservation-bundle` enabled, `ReservationBoothAvailability` asks
`Reservations::check()` for each booth's resource, without booking or holding anything. Without it, every booth is
unknown. An application with its own bookings implements the interface and names it in `booths.availability`.

## Large images

An image of a few thousand pixels loads at once, as an image overlay. A larger one is cut into tiles, which the
browser loads as the visitor zooms in:

```sh
php bin/console phil:map:tile-image public/plans/hall-a.png public/plans/hall-a
```

The command cuts the image into `<z>/<x>/<y>.png`, from zoom 0 to the plan's native zoom, where a tile shows the
image's own pixels, and says the size and the native zoom. `GdImageTiler` uses PHP's GD extension: it reads PNG, JPEG,
GIF and WebP, writes transparent PNG tiles of `image_tiles.tile_size` pixels, and checks the image's size against
`image_tiles.max_pixels` from its header, before decoding it — GD holds about five bytes a pixel in memory.

Another tiler — on Imagick, libvips or a service — is an `ImageTiler`; alias it.
