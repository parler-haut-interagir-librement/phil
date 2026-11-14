<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\GuestRequest;

/**
 * What a person without an account asks for: a copy of what the site keeps about them, or its erasure.
 */
enum GuestRequestKind: string
{
    case Access = 'access';
    case Erasure = 'erasure';
}
