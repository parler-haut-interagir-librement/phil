<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Infrastructure\Twig;

use Phil\DataTableBundle\Application\View\CellView;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

/**
 * The functions of the bundle's templates: `phil_data_table_kit()`, the name under which a recipe of the kit is
 * installed, `{% component (phil_data_table_kit('Button')) %}`; `phil_data_table_cell()`, a cell of `Column::block()`
 * drawn through the cell themes of the application.
 */
final readonly class DataTableExtension
{
    public function __construct(
        private KitComponents $kit,
        private CellBlocks $cells,
    ) {
    }

    #[AsTwigFunction(name: 'phil_data_table_kit')]
    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }

    #[AsTwigFunction(name: 'phil_data_table_cell', needsEnvironment: true, isSafe: ['html'])]
    public function cell(Environment $twig, CellView $cell): string
    {
        return $this->cells->render($twig, $cell);
    }
}
