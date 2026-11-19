<!-- phil-dev source-sha256=0f8f308c7f344375392979e31e9dd39dbd9e68266bcb6e3cc9e087d3d271fb0d -->
# Cartes

`Phil\MapBundle\Application\Maps` construit chaque carte du bundle sur Symfony UX Map, et donne un `MapView` : la carte,
le libellé qui la nomme aux technologies d'assistance, le titre de sa liste, et la liste elle-même. `phil_map()` le
rend.

```php
use Phil\Geo\Coordinates;
use Phil\MapBundle\Application\Maps;
use Phil\MapBundle\Domain\ValueObject\MapPlace;
use Phil\MapBundle\Domain\ValueObject\SiteAddress;

final readonly class VenuesController
{
    public function __construct(private Maps $maps, private Environment $twig) {}

    public function __invoke(): Response
    {
        $view = $this->maps->places('Our venues', [
            MapPlace::of('hall', 'Main hall', Coordinates::of(45.5017, -73.5673), 'Doors at 9', SiteAddress::of('/venues/hall')),
            MapPlace::of('park', 'Park', Coordinates::of(45.5100, -73.5600)),
        ]);

        return new Response($this->twig->render('venues.html.twig', ['map' => $view]));
    }
}
```

## Les lieux

`places(string $label, iterable $places, ?Coordinates $center = null, float $zoom = 13)` met un marqueur sur chaque
`MapPlace` : un identifiant, un nom, les coordonnées de `phil/geo`, et facultativement une ligne à son sujet et la page
vers laquelle il mène, un `SiteAddress` — un chemin du site ou une adresse absolue lue par `phil/url`. Sans centre, la
carte s'ajuste à ses marqueurs ; une carte sans lieux en reçoit un. Une carte montre 5 000 lieux au plus.

Le nom et la ligne sont du texte : ils sont échappés avant d'atteindre la fenêtre d'information du marqueur, que Leaflet
écrit comme du HTML.

## Le quartier

`neighbourhood(string $label, iterable $positions, Coordinates $center, float $zoom = 12)` compte les positions qu'on lui
donne — membres, lieux, groupes — par cellule de la grille de vie privée, et dessine les cellules qui atteignent le
minimum comme des zones avec leur compte. Il lit 100 000 positions au plus. Aucune position n'en sort : voir
[Vie privée](privacy.md).

## Les plans d'étage

`floorPlan(string $label, FloorPlan $plan, ?DateTimeImmutable $start = null, ?DateTimeImmutable $end = null)` dessine un
plan et ses kiosques. Sur une période, chaque kiosque prend l'état que donne sa ressource ; sans période, chaque kiosque
est inconnu. Voir [Plans d'étage](floor-plans.md).

## Le rendu

```twig
{{ phil_map(map) }}
{{ phil_map(map, {id: 'venues', heading: 'h3', class: 'isTall'}) }}
```

`phil_map(view, attributes)` rend un `<figure>` : l'élément de Symfony UX Map, avec le contrôleur du bundle à côté de
celui de Leaflet, le rôle `region`, le libellé de la vue comme `aria-label`, et la liste en dessous comme description ;
puis la liste, un titre et un groupe Item du kit PHIL UX, un élément par lieu, zone ou kiosque, avec son lien et sa
ligne.

| Attribut | Effet |
|---|---|
| `id` | L'identifiant du `<figure>` ; celui de la liste est `<id>-list`. Un identifiant aléatoire en l'absence d'un. |
| `heading` | L'élément du titre de la liste : `h2` par défaut, `h3` à `h6`, ou `p`. |
| Tout autre | Ajouté à l'élément de la carte ; `class` est ajouté à `phil-map-canvas`. |

Le `<figure>` a la classe `phil-map` et `isPlaces`, `isNeighbourhood` ou `isPlan`. Sans JavaScript, l'élément de la
carte reste caché et la liste dit ce que la carte montre.

## Les mots

Les titres de la liste, les comptes des zones et les états des kiosques viennent du domaine de traduction `phil_map`, en
anglais et en français ; sans traducteur, ou sans message, ils sont en anglais.
