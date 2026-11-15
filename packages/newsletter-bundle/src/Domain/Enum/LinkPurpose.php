<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Enum;

/**
 * What a signed link of the newsletter is for.
 */
enum LinkPurpose: string
{
    case Confirm = 'confirm';
    case Unsubscribe = 'unsubscribe';
    case Preferences = 'preferences';
    case Click = 'click';
}
