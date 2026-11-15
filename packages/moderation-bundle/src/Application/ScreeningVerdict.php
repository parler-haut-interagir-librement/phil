<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

/**
 * The verdict of the watched words, from the mildest: published, published and flagged to the moderation, held until
 * the moderation approves it, refused.
 */
enum ScreeningVerdict: string
{
    case Allow = 'allow';
    case Flag = 'flag';
    case Hold = 'hold';
    case Block = 'block';
}
