<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application\Contract;

/**
 * The addresses of the images of reactions, from what their declarations give, all asked at once so that an adapter
 * reads them in one query. A declaration without an address is left out, and its reaction is then not offered.
 */
interface EmojiImages
{
    /**
     * @return array<string, string> the address of each declaration that has one, by declaration
     */
    public function urls(string ...$images): array;
}
