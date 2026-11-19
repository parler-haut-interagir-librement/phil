<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceSubscriptionBundle\Domain\Model\Gift;
use Phil\CommerceSubscriptionBundle\Domain\Repository\GiftRepository;

final class InMemoryGifts implements GiftRepository
{
    /** @var list<Gift> */
    public array $gifts = [];

    /** @var list<string> the hashes looked up, in order */
    public array $lookups = [];

    #[Override]
    public function find(string $id): ?Gift
    {
        return array_find($this->gifts, static fn (Gift $gift): bool => $gift->id === $id);
    }

    #[Override]
    public function findByCodeHash(string $hash): ?Gift
    {
        $this->lookups[] = $hash;

        return array_find($this->gifts, static fn (Gift $gift): bool => $gift->codeHash() === $hash);
    }

    #[Override]
    public function add(Gift $gift): void
    {
        $this->gifts[] = $gift;
    }
}
