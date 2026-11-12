<?php

declare(strict_types=1);

namespace Phil\SocialCards\Exception;

use RuntimeException;

/**
 * The image library could not draw or encode a card, or a store could not keep it.
 */
final class CardRenderingFailed extends RuntimeException implements SocialCardsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
