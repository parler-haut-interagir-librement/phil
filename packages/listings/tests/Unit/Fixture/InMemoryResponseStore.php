<?php

declare(strict_types=1);

namespace Phil\Listings\Tests\Unit\Fixture;

use Override;
use Phil\Listings\Contract\ResponseStore;
use Phil\Listings\ListingId;
use Phil\Listings\MemberId;
use Phil\Listings\Response;

final class InMemoryResponseStore implements ResponseStore
{
    /** @var array<string, Response> */
    public array $responses = [];

    #[Override]
    public function countOpen(ListingId $listing): int
    {
        return \count(array_filter($this->responses, static fn (Response $response): bool => $response->listing->equals($listing) && $response->isOpen()));
    }

    #[Override]
    public function hasOpen(ListingId $listing, MemberId $respondent): bool
    {
        return array_any($this->responses, static fn (Response $response): bool => $response->listing->equals($listing) && $response->respondent->equals($respondent) && $response->isOpen());
    }

    #[Override]
    public function save(Response $response): void
    {
        $this->responses[$response->id->value] = $response;
    }
}
