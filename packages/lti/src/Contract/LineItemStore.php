<?php

declare(strict_types=1);

namespace Phil\Lti\Contract;

use Phil\Lti\Platform\Ags\StoredLineItem;

/**
 * The line items the platform keeps, each the tool's that created it or that it was created for.
 */
interface LineItemStore
{
    /**
     * @return list<StoredLineItem>
     */
    public function list(string $clientId, string $contextId, ?string $resourceLinkId, ?string $resourceId, ?string $tag, int $limit, int $offset): array;

    public function find(string $clientId, string $contextId, string $id): ?StoredLineItem;

    public function save(StoredLineItem $lineItem): void;

    /**
     * Removes the line item and its scores.
     */
    public function delete(StoredLineItem $lineItem): void;
}
