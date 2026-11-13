<?php

declare(strict_types=1);

namespace Phil\AccessCodes\Exception;

use LogicException;

/**
 * The codes of a created batch asked for a second time: they are given once, at creation, then forgotten.
 */
final class CodesAlreadyTaken extends LogicException implements AccessCodesException
{
    public static function forBatch(string $batchId): self
    {
        return new self(\sprintf('The codes of batch "%s" were already taken: they are given once, at its creation.', $batchId));
    }
}
