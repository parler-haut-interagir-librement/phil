<?php

declare(strict_types=1);

namespace Phil\MapBundle\Application;

use DateTimeImmutable;
use Phil\Geo\Coordinates;
use Phil\MapBundle\Application\Contract\BaseLayer;
use Phil\MapBundle\Application\Contract\BoothAvailability;
use Phil\MapBundle\Domain\Enum\BoothState;
use Phil\MapBundle\Domain\Exception\InvalidMap;
use Phil\MapBundle\Domain\ValueObject\FloorPlan;
use Phil\MapBundle\Domain\ValueObject\MapPlace;
use Symfony\UX\Map\Bridge\Leaflet\LeafletOptions;
use Symfony\UX\Map\InfoWindow;
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;
use Symfony\UX\Map\Rectangle;

/**
 * Builds the maps of the bundle on Symfony UX Map: places as markers, the neighbourhood as areas of the privacy grid,
 * a floor plan with its booths. Each comes with the list that says in text what it shows.
 *
 * Every text is escaped before it reaches an info window, which Leaflet writes as HTML.
 */
final readonly class Maps
{
    public const int MAX_PLACES = 5000;

    public function __construct(
        private BaseLayer $baseLayer,
        private Neighbourhood $neighbourhood,
        private BoothAvailability $booths,
        private MapMessages $messages = new MapMessages(),
    ) {
    }

    /**
     * Places as markers, the map fitted to them unless a center is given.
     *
     * @param iterable<MapPlace> $places
     *
     * @throws InvalidMap when there are neither places nor a center, or too many places
     */
    public function places(string $label, iterable $places, ?Coordinates $center = null, float $zoom = 13): MapView
    {
        $map = $this->geographic($center, $zoom);
        $listed = [];

        foreach ($places as $place) {
            if (\count($listed) >= self::MAX_PLACES) {
                throw InvalidMap::because(\sprintf('A map shows %d places at most.', self::MAX_PLACES));
            }

            $map->addMarker(new Marker(
                position: $this->point($place->coordinates),
                title: $place->name,
                infoWindow: new InfoWindow(headerContent: $this->escape($place->name), content: null === $place->description ? null : $this->escape($place->description)),
                extra: ['place' => $place->id, 'href' => $place->link?->value],
                id: $place->id,
            ));
            $listed[] = new ListedPlace($place->name, $place->description, $place->link?->value);
        }

        if (!$center instanceof Coordinates) {
            if ([] === $listed) {
                throw InvalidMap::because('A map without places is given its center.');
            }

            $map->fitBoundsToMarkers();
        }

        return new MapView($map, MapView::PLACES, $label, $this->messages->trans('list.places'), $listed);
    }

    /**
     * Positions shown as the areas of the privacy grid they fall in, with how many each holds.
     *
     * @param iterable<Coordinates> $positions
     *
     * @throws InvalidMap when there are too many positions
     */
    public function neighbourhood(string $label, iterable $positions, Coordinates $center, float $zoom = 12): MapView
    {
        $map = $this->geographic($center, $zoom);
        $listed = [];

        foreach ($this->neighbourhood->areas($positions) as $area) {
            $count = $this->messages->trans('neighbourhood.area', ['%count%' => $area->count]);
            $near = $this->messages->trans('neighbourhood.near', ['%coordinates%' => $area->center->blurred(2)->toString()]);
            $map->addRectangle(new Rectangle(
                southWest: $this->point($area->southWest),
                northEast: $this->point($area->northEast),
                infoWindow: new InfoWindow(content: $this->escape($count)),
                extra: ['count' => $area->count],
            ));
            $listed[] = new ListedPlace($count, $near);
        }

        return new MapView($map, MapView::NEIGHBOURHOOD, $label, $this->messages->trans('list.neighbourhood'), $listed);
    }

    /**
     * A floor plan and its booths, each in the state its resource gives for the period, or unknown without one.
     *
     * @throws InvalidMap when only one end of the period is given, or it ends before it starts
     */
    public function floorPlan(string $label, FloorPlan $plan, ?DateTimeImmutable $start = null, ?DateTimeImmutable $end = null): MapView
    {
        if ((!$start instanceof DateTimeImmutable) !== (!$end instanceof DateTimeImmutable) || ($start instanceof DateTimeImmutable && $end instanceof DateTimeImmutable && $end <= $start)) {
            throw InvalidMap::because('The period of a floor plan has a start and an end after it, or neither.');
        }

        $states = !$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable ? [] : $this->booths->states($plan->booths, $start, $end);
        $zoom = $plan->nativeZoom();
        [$top, $right] = [$plan->position(0, $plan->height)[0], $plan->position($plan->width, 0)[1]];
        [$centerLatitude, $centerLongitude] = $plan->position($plan->width / 2, $plan->height / 2);
        $map = new Map(
            options: new LeafletOptions(tileLayer: false, attributionControl: false),
            center: new Point($centerLatitude, $centerLongitude),
            zoom: 0,
            extra: ['phil' => ['plan' => [
                'image' => $plan->image->value,
                'tiles' => $plan->tiles?->value,
                'nativeZoom' => $zoom,
                'bounds' => [[$top, 0.0], [0.0, $right]],
            ]]],
            minZoom: 0,
            maxZoom: $zoom + 2,
        );
        $listed = [];

        foreach ($plan->booths as $booth) {
            $state = $states[$booth->id] ?? BoothState::Unknown;
            $words = $this->messages->trans('booth.'.$state->value);
            [$south, $west] = $plan->position($booth->x, $booth->y + $booth->height);
            [$north, $east] = $plan->position($booth->x + $booth->width, $booth->y);
            $map->addRectangle(new Rectangle(
                southWest: new Point($south, $west),
                northEast: new Point($north, $east),
                infoWindow: new InfoWindow(headerContent: $this->escape($booth->label), content: $this->escape($words)),
                extra: ['booth' => $booth->id, 'state' => $state->value],
                id: $booth->id,
            ));
            $listed[] = new ListedPlace($booth->label, $words);
        }

        return new MapView($map, MapView::PLAN, $label, $this->messages->trans('list.plan'), $listed);
    }

    private function geographic(?Coordinates $center, float $zoom): Map
    {
        $layer = $this->baseLayer->definition();

        return new Map(
            options: new LeafletOptions(tileLayer: $layer->tileLayer),
            center: $center instanceof Coordinates ? $this->point($center) : null,
            zoom: $zoom,
            extra: [] === $layer->extra ? [] : ['phil' => $layer->extra],
        );
    }

    private function point(Coordinates $coordinates): Point
    {
        return new Point($coordinates->latitude, $coordinates->longitude);
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
