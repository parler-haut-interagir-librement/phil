<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Http;

use Phil\PrivacyBundle\Domain\Cookie\CookieInventory;
use Phil\PrivacyBundle\Domain\Cookie\DeclaredCookie;

/**
 * The application's cookie inventory with the line of the cookie the consent writes itself, which every site has.
 */
final class CookieInventoryFactory
{
    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $data the inventory as its YAML file maps it
     */
    public static function create(array $data, string $consentCookie, string $lifetime): CookieInventory
    {
        return CookieInventory::fromArray($data)->with(new DeclaredCookie(
            $consentCookie,
            null,
            ['en' => 'Keeps your cookie choices, so that you are not asked again.', 'fr' => 'Garde vos choix de témoins, pour ne pas vous les demander de nouveau.'],
            $lifetime,
        ));
    }
}
