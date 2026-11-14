<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Cookie;

/**
 * Where a declared tracker lives in the visitor's browser. The law asks consent for each the same way; the inventory
 * names the place so that the policy says what the visitor will find.
 */
enum CookieStorage: string
{
    case Cookie = 'cookie';
    case LocalStorage = 'local_storage';
    case SessionStorage = 'session_storage';
}
