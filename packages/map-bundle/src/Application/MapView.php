<?php

declare(strict_types=1);

namespace Phil\MapBundle\Application;

use Symfony\UX\Map\Map;

/**
 * A map ready to render with `phil_map()`: Symfony UX Map's map, the label that names it to assistive technologies, the
 * title of its list and the list itself, which the page shows whether or not the map is drawn.
 */
final readonly class MapView
{
    public const string PLACES = 'places';

    public const string NEIGHBOURHOOD = 'neighbourhood';

    public const string PLAN = 'plan';

    /**
     * @param self::PLACES|self::NEIGHBOURHOOD|self::PLAN $kind
     * @param list<ListedPlace>                           $places
     */
    public function __construct(
        public Map $map,
        public string $kind,
        public string $label,
        public string $listTitle,
        public array $places,
    ) {
    }
}
