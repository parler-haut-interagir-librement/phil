<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Application\Contract;

/**
 * Has a card drawn out of the request, by its fingerprint only.
 */
interface CardQueue
{
    public function queue(string $fingerprint): void;
}
