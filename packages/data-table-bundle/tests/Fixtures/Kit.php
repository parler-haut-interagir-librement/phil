<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Fixtures;

use Phil\DataTableBundle\Application\Contract\DataTable;
use Phil\DataTableBundle\Application\EditValues;
use Phil\DataTableBundle\Application\RowValues;
use Phil\DataTableBundle\Application\State\StateResolver;
use Phil\DataTableBundle\Application\TableOperations;
use Phil\DataTableBundle\Application\Tables;
use Phil\DataTableBundle\Application\View\TableViews;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The bundle's services wired by hand, over one access.
 */
final class Kit
{
    public readonly RecordingAccess $access;

    public readonly RowValues $values;

    public readonly EditValues $editValues;

    public readonly TranslatorInterface $translator;

    public readonly TableOperations $operations;

    public readonly TableViews $views;

    public function __construct(?TranslatorInterface $translator = null)
    {
        $this->access = new RecordingAccess();
        $this->values = new RowValues(PropertyAccess::createPropertyAccessor());
        $this->editValues = new EditValues($this->values);
        $identity = new IdentityTranslator();
        $identity->setLocale('en');
        $this->translator = $translator ?? $identity;
        $this->operations = new TableOperations($this->access, $this->values, $this->editValues, Validation::createValidator(), $this->translator);
        $this->views = new TableViews(new StateResolver(), $this->access, $this->values, $this->editValues, $this->operations, $this->translator);
    }

    public function tables(DataTable ...$tables): Tables
    {
        $byName = [];

        foreach ($tables as $table) {
            $byName[$table::name()] = $table;
        }

        return new Tables(new class($byName) implements ContainerInterface {
            /** @param array<string, DataTable> $tables */
            public function __construct(private readonly array $tables)
            {
            }

            public function get(string $id): DataTable
            {
                return $this->tables[$id] ?? throw new class extends RuntimeException implements NotFoundExceptionInterface {};
            }

            public function has(string $id): bool
            {
                return isset($this->tables[$id]);
            }
        }, $this->access);
    }
}
