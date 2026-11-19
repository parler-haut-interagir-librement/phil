<!-- phil-dev source-sha256=4a6ed2cf02296dd5dce214a3aaeb949e37b0e7a273aad5665f828a3d1559b52d -->
# Plans d'étage

Un plan d'étage est une image avec des kiosques dessinés par-dessus : la salle d'un salon professionnel, un lieu, un
campus.

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

## Le plan

`FloorPlan::of(image, width, height, booths, tiles)` prend l'adresse de l'image et sa taille en pixels, de 1 à 1 000 000
de chaque côté, jusqu'à 2 000 kiosques, et le gabarit des tuiles qui en sont découpées, lorsqu'il y en a. Un kiosque qui
dépasse le bord, deux kiosques du même identifiant et un gabarit sans `{z}`, `{x}` et `{y}` sont refusés.

Le plan est dessiné sur le système de référence plat de Leaflet, `L.CRS.Simple` : aucune projection, aucune tuile du
monde. Son échelle est de 2^-n unités de carte par pixel, n étant le plus petit entier qui fait tenir l'image dans 180
unités de largeur et 90 de hauteur ; au zoom n, un pixel de l'écran est un pixel de l'image. Son coin supérieur gauche est
à l'origine, le bas est le sud et la droite est l'est. Le contrôleur ajuste la carte au plan et l'y maintient.

## Les kiosques

`Booth::of(id, label, x, y, width, height, resource)` est un rectangle de l'image, en pixels à partir de son coin
supérieur gauche, avec l'identifiant de la ressource `phil/reservation` qui le représente dans les réservations, lorsqu'il
en a une. Chaque kiosque est dessiné comme un rectangle dont la classe est son état, et listé avec lui.

| État | Signifie | Classe |
|---|---|---|
| `free` | Une unité peut être réservée sur toute la période | `phil-map-booth isFree` |
| `taken` | Refusé parce que la ressource est pleine | `phil-map-booth isTaken` |
| `closed` | Refusé pour une autre raison : fermé alors, trop tôt, hors de la grille | `phil-map-booth isClosed` |
| `unknown` | Pas de période, pas de ressource, ou une ressource qui n'existe pas | `phil-map-booth isUnknown` |

Les états viennent d'un `BoothAvailability`. Lorsque `phil/reservation-bundle` est activé, `ReservationBoothAvailability`
demande à `Reservations::check()` la ressource de chaque kiosque, sans rien réserver ni retenir. Sans lui, chaque kiosque
est inconnu. Une application qui a ses propres réservations implémente l'interface et la nomme dans
`booths.availability`.

## Les grandes images

Une image de quelques milliers de pixels se charge d'un seul coup, comme une superposition d'image. Une image plus
grande est découpée en tuiles, que le navigateur charge à mesure que le visiteur zoome :

```sh
php bin/console phil:map:tile-image public/plans/hall-a.png public/plans/hall-a
```

La commande découpe l'image en `<z>/<x>/<y>.png`, du zoom 0 au zoom natif du plan, où une tuile montre les pixels mêmes
de l'image, et dit la taille et le zoom natif. `GdImageTiler` emploie l'extension GD de PHP : il lit le PNG, le JPEG, le
GIF et le WebP, écrit des tuiles PNG transparentes de `image_tiles.tile_size` pixels, et vérifie la taille de l'image
contre `image_tiles.max_pixels` à partir de son en-tête, avant de la décoder — GD occupe environ cinq octets de mémoire
par pixel.

Un autre découpeur — sur Imagick, libvips ou un service — est un `ImageTiler` ; créez-en un alias.
