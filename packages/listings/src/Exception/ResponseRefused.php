<?php

declare(strict_types=1);

namespace Phil\Listings\Exception;

use Phil\Listings\Refusal;
use RuntimeException;

/**
 * A member may not respond to a listing, or a response may no longer be accepted; {@see self::$refusal} says why.
 */
final class ResponseRefused extends RuntimeException implements ListingsException
{
    public function __construct(
        public readonly Refusal $refusal,
    ) {
        parent::__construct(\sprintf('The response is refused: %s.', $refusal->value));
    }

    public static function for(Refusal $refusal): self
    {
        return new self($refusal);
    }
}
