<?php

declare(strict_types=1);

namespace Phil\BulkActions\Target;

use Phil\BulkActions\Contract\BulkAction;
use Phil\BulkActions\Contract\ItemRepository;
use Phil\BulkActions\Exception\InvalidInput;
use Phil\BulkActions\Exception\UnknownAction;
use Phil\BulkActions\Internal\Check;

/**
 * A kind of item bulk actions apply to, declared by the configuration: its name, the class of its items, its
 * repository and its actions. A request names the target; the class always comes from here.
 */
final readonly class Target
{
    /** @var array<string, BulkAction> */
    private array $actions;

    /**
     * @param class-string     $class   every item the repository gives must be an instance of it
     * @param list<BulkAction> $actions
     */
    public function __construct(
        public string $name,
        public string $class,
        public ItemRepository $items,
        array $actions,
    ) {
        Check::name('target', $name);

        if (!class_exists($class) && !interface_exists($class)) {
            throw InvalidInput::because('class of target "'.$name.'"', 'must exist');
        }

        $byName = [];

        foreach ($actions as $action) {
            $actionName = Check::name('action', $action->name());

            if (isset($byName[$actionName])) {
                throw InvalidInput::because('action "'.$actionName.'" of target "'.$name.'"', 'must be declared once');
            }

            foreach ($action->fields() as $field) {
                Check::name('field of action "'.$actionName.'"', $field);
            }

            $byName[$actionName] = $action;
        }

        $this->actions = $byName;
    }

    public function action(string $name): BulkAction
    {
        return $this->actions[$name] ?? throw UnknownAction::action($this->name, $name);
    }

    /**
     * @return list<BulkAction>
     */
    public function actions(): array
    {
        return array_values($this->actions);
    }

    /**
     * Whether an item read from the repository is of the target's class.
     */
    public function holds(object $item): bool
    {
        return $item instanceof $this->class;
    }
}
