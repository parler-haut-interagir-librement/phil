<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Cookie;

use Phil\PrivacyBundle\Domain\Consent\ConsentCategory;

/**
 * The cookies of one consent category, as the banner, the page of the choices and the policy list them. Without a
 * category, the cookies the consent itself writes.
 */
final readonly class CookieGroup
{
    /**
     * @param list<DeclaredCookie> $cookies
     */
    public function __construct(
        public ?ConsentCategory $category,
        public array $cookies,
    ) {
    }
}
