<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional\App;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The `render_chart` function of Symfony UX Chart.js, as the tests of the `ChartJs` recipe need it: a canvas carrying
 * the attributes it is given, without the bundle, which the kit does not require.
 */
final class ChartjsStubExtension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        return [new TwigFunction('render_chart', self::render(...), ['is_safe' => ['html']])];
    }

    /**
     * @param array<string, string> $attributes
     */
    public static function render(mixed $chart, array $attributes = []): string
    {
        $html = '<canvas data-controller="symfony--ux-chartjs--chart"';

        foreach ($attributes as $name => $value) {
            $html .= \sprintf(' %s="%s"', htmlspecialchars($name, \ENT_QUOTES), htmlspecialchars($value, \ENT_QUOTES));
        }

        return $html.'></canvas>';
    }
}
