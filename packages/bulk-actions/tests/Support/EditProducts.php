<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Support;

use LogicException;
use Phil\BulkActions\Change\ChangeSet;
use Phil\BulkActions\Contract\BulkAction;
use Phil\BulkActions\Exception\ItemRefused;
use Phil\BulkActions\Exception\VersionConflict;
use Phil\BulkActions\Run\ItemContext;

/**
 * Sets the status and the price of products. Products named in $refused, $conflicting or $exploding throw.
 */
final class EditProducts implements BulkAction
{
    /** @var list<string> */
    public array $refused = [];

    /** @var list<string> */
    public array $conflicting = [];

    /** @var list<string> */
    public array $exploding = [];

    /** @var list<ItemContext> */
    public array $contexts = [];

    /** @var list<string> */
    public array $fieldList = ['status', 'price'];

    public function forgetContexts(): void
    {
        $this->contexts = [];
    }

    public function name(): string
    {
        return 'edit';
    }

    public function attribute(): string
    {
        return 'PRODUCT_EDIT';
    }

    public function isDestructive(): bool
    {
        return false;
    }

    public function fields(): array
    {
        return $this->fieldList;
    }

    public function label(object $item): string
    {
        \assert($item instanceof Product);

        return $item->name;
    }

    public function values(object $item): array
    {
        \assert($item instanceof Product);

        return ['status' => $item->status, 'price' => $item->price];
    }

    public function apply(object $item, ChangeSet $changes, ItemContext $context): void
    {
        \assert($item instanceof Product);
        $this->contexts[] = $context;

        $status = $changes->value('status');
        $price = $changes->value('price');

        if (\is_string($status)) {
            $item->status = $status;
        }

        if ($changes->has('price')) {
            $item->price = \is_int($price) ? $price : null;
        }

        if (\in_array($item->id, $this->refused, true)) {
            throw ItemRefused::because('products.refused.locked');
        }

        if (\in_array($item->id, $this->conflicting, true)) {
            throw VersionConflict::create();
        }

        if (\in_array($item->id, $this->exploding, true)) {
            throw new LogicException('Boom.');
        }
    }
}
