<?php

declare(strict_types=1);

namespace Phil\MapBundle\Infrastructure\Twig;

use Phil\MapBundle\Application\MapView;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

/**
 * `phil_map(view)`, a map with its label and the list of what it shows, and `phil_map_kit()`, the name under which a
 * recipe of the kit is installed, for the templates of the bundle.
 */
final readonly class MapExtension
{
    public const string TEMPLATE = '@PhilMap/map.html.twig';

    /** The elements the title of the list may be. */
    public const array HEADINGS = ['h2', 'h3', 'h4', 'h5', 'h6', 'p'];

    public function __construct(
        private KitComponents $kit,
    ) {
    }

    /**
     * @param array<string, scalar> $attributes added to the map's element; `id` names the figure, `heading` the
     *                                          element of the list's title (`h2` by default)
     */
    #[AsTwigFunction(name: 'phil_map', needsEnvironment: true, isSafe: ['html'])]
    public function map(Environment $twig, MapView $view, array $attributes = []): string
    {
        $id = isset($attributes['id']) ? (string) $attributes['id'] : 'phil-map-'.sodium_bin2hex(random_bytes(4));
        $heading = $attributes['heading'] ?? 'h2';
        $heading = \in_array($heading, self::HEADINGS, true) ? $heading : 'h2';
        unset($attributes['id'], $attributes['heading'], $attributes['data-controller']);

        return $twig->render(self::TEMPLATE, ['view' => $view, 'id' => $id, 'heading' => $heading, 'attributes' => $attributes]);
    }

    #[AsTwigFunction(name: 'phil_map_kit')]
    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }
}
