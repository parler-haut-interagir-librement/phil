<?php

declare(strict_types=1);

namespace Phil\PrintCompositor;

use Phil\PrintCompositor\Exception\InvalidLayout;
use Phil\PrintCompositor\Internal\Gd;

/**
 * What a print file is made of: the zone it covers at its real size, the resolution it is drawn at and its layers, in
 * order. Every item lies inside the zone.
 */
final readonly class PrintJob
{
    public const int DEFAULT_DPI = 300;

    /**
     * @param list<Layer> $layers
     */
    public function __construct(
        public Zone $zone,
        public array $layers,
        public int $dpi = self::DEFAULT_DPI,
    ) {
        Gd::checkDpi($dpi);

        if ([] === $layers) {
            throw InvalidLayout::because(\sprintf('A print job of the zone "%s" has at least one layer.', $zone->name));
        }

        foreach ($layers as $layer) {
            foreach ($layer->items as $item) {
                $box = $item->box();

                if (!$box->isInside($zone->width, $zone->height)) {
                    throw InvalidLayout::because(\sprintf('An item of the layer "%s" goes past the %s × %s mm zone "%s": its box ends at %s × %s mm.', $layer->name, $zone->width, $zone->height, $zone->name, $box->x + $box->width, $box->y + $box->height));
                }
            }
        }
    }

    /**
     * The inks of the layers, each once, in the order they first appear.
     *
     * @return list<string>
     */
    public function inks(): array
    {
        return array_values(array_unique(array_map(static fn (Layer $layer): string => $layer->ink, $this->layers)));
    }
}
