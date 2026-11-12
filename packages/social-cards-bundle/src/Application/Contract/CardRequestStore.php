<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Application\Contract;

use Phil\SocialCardsBundle\Application\CardRequest;

/**
 * What each card is drawn from, by fingerprint: kept when a page names its card, so that the address of the card can
 * be drawn later — by a worker, or by the first request for the image.
 */
interface CardRequestStore
{
    /**
     * Keeps the request; whether it was not kept yet.
     */
    public function save(string $fingerprint, CardRequest $request): bool;

    public function find(string $fingerprint): ?CardRequest;
}
