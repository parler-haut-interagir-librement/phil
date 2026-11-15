<?php

declare(strict_types=1);

namespace Phil\Lti\Platform\Ags;

use Override;
use Phil\Lti\Contract\LineItemStore;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Line items held in one process, for tests.
 */
final class InMemoryLineItemStore implements LineItemStore, ResetInterface
{
    /** @var array<string, StoredLineItem> */
    private array $lineItems = [];

    #[Override]
    public function list(string $clientId, string $contextId, ?string $resourceLinkId, ?string $resourceId, ?string $tag, int $limit, int $offset): array
    {
        $found = array_filter($this->lineItems, static fn (StoredLineItem $stored): bool => $stored->clientId === $clientId
            && $stored->contextId === $contextId
            && (null === $resourceLinkId || $stored->lineItem->resourceLinkId === $resourceLinkId)
            && (null === $resourceId || $stored->lineItem->resourceId === $resourceId)
            && (null === $tag || $stored->lineItem->tag === $tag));
        ksort($found, \SORT_STRING);

        return \array_slice(array_values($found), $offset, $limit);
    }

    #[Override]
    public function find(string $clientId, string $contextId, string $id): ?StoredLineItem
    {
        $stored = $this->lineItems[$id] ?? null;

        return null !== $stored && $stored->clientId === $clientId && $stored->contextId === $contextId ? $stored : null;
    }

    #[Override]
    public function save(StoredLineItem $lineItem): void
    {
        $this->lineItems[$lineItem->id] = $lineItem;
    }

    #[Override]
    public function delete(StoredLineItem $lineItem): void
    {
        unset($this->lineItems[$lineItem->id]);
    }

    #[Override]
    public function reset(): void
    {
        $this->lineItems = [];
    }
}
