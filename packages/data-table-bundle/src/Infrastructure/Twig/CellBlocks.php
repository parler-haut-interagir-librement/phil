<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Infrastructure\Twig;

use Phil\DataTableBundle\Application\View\CellView;
use Twig\BlockChain;
use Twig\Environment;

/**
 * The cells of `Column::block()`, drawn by a `Twig\BlockChain`: the cell themes of the application first, in their order,
 * then the column's own template. A theme overrides a block by defining it, and every block it leaves out is the
 * column's. The chains are kept for the request, one per column template — templates the application declares, so
 * their number is bounded by its code —, and dropped by `kernel.reset`.
 */
final class CellBlocks
{
    /** @var array<string, BlockChain> */
    private array $chains = [];

    /**
     * @param list<string> $themes the templates whose blocks win over a column's, the first one over the others
     */
    public function __construct(
        private readonly array $themes = [],
    ) {
    }

    /**
     * The cell drawn by its block, with `row` and `value` in the block's context; an empty string for a cell without
     * a block.
     */
    public function render(Environment $twig, CellView $cell): string
    {
        if (null === $cell->block || null === $cell->template) {
            return '';
        }

        return $this->chain($twig, $cell->template)->renderBlock($cell->block, ['row' => $cell->row, 'value' => $cell->value]);
    }

    public function reset(): void
    {
        $this->chains = [];
    }

    private function chain(Environment $twig, string $template): BlockChain
    {
        if (isset($this->chains[$template])) {
            return $this->chains[$template];
        }

        return $this->chains[$template] = new BlockChain($twig, [...$this->themes, $template]);
    }
}
