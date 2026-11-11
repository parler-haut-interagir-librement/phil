<?php

declare(strict_types=1);

namespace Phil\Listings\Contract;

use Phil\Listings\ListingId;
use Phil\Listings\MemberId;
use Phil\Listings\Response;

/**
 * Where the responses to the listings live.
 */
interface ResponseStore
{
    /**
     * How many responses to the listing are open: pending or accepted.
     */
    public function countOpen(ListingId $listing): int;

    /**
     * Whether the member has an open response to the listing.
     */
    public function hasOpen(ListingId $listing, MemberId $respondent): bool;

    public function save(Response $response): void;
}
