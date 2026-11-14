<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application;

use Phil\DataTableBundle\Application\Contract\DataTable;
use Phil\DataTableBundle\Application\Contract\TableAccess;
use Phil\DataTableBundle\Application\Definition\TableBuilder;
use Phil\DataTableBundle\Application\Exception\AccessDenied;
use Phil\DataTableBundle\Application\Exception\NotFound;
use Psr\Container\ContainerInterface;

/**
 * The application's tables, by name. A table is built each time it is opened, so that nothing of one request stays
 * for the next; its access is asked each time too.
 */
final readonly class Tables
{
    public function __construct(
        private ContainerInterface $tables,
        private TableAccess $access,
    ) {
    }

    /**
     * @param array<string, scalar|null> $context
     *
     * @throws NotFound     when no table has the name
     * @throws AccessDenied when the viewer does not hold the table's attribute
     */
    public function open(string $name, array $context = []): OpenTable
    {
        if (!$this->tables->has($name)) {
            throw NotFound::table($name);
        }

        /** @var DataTable $table */
        $table = $this->tables->get($name);
        $builder = new TableBuilder($name);
        $table->configure($builder);
        $definition = $builder->build();

        if (null !== $definition->attribute && !$this->access->isGranted($definition->attribute)) {
            throw AccessDenied::to(\sprintf('the table "%s"', $name), $definition->attribute);
        }

        return new OpenTable($definition, $table->source($context));
    }
}
