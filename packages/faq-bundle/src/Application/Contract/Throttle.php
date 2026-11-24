<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Application\Contract;

use Phil\FaqBundle\Application\Exception\TooManyRequests;

/**
 * Holds back the votes on one question, or the searches counted in one language: the key is a question or a language,
 * never a person or an address.
 */
interface Throttle
{
    /**
     * @throws TooManyRequests when the key has used its share
     */
    public function consume(string $key): void;
}
