<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Application\Attribute;

use Attribute;

/**
 * Marks a controller as the page of an embed: a request that carries a valid token of that embed is answered for it,
 * and may be framed by the token's origin. A route says the same with its `_embed` default.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class Embeddable
{
    public function __construct(
        public string $embed,
    ) {
    }
}
