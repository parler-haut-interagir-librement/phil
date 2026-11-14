<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Testing;

use Phil\PrivacyBundle\Domain\Cookie\CookieInventory;
use Phil\PrivacyBundle\Infrastructure\Http\UndeclaredCookies;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Response;

/**
 * For a functional test of the application, in a `KernelTestCase` or a `WebTestCase`: fails when a response sets a
 * cookie the inventory does not declare.
 *
 * ```php
 * final class PagesTest extends WebTestCase
 * {
 *     use AssertsDeclaredCookies;
 *
 *     public function testTheHomePage(): void
 *     {
 *         $client = self::createClient();
 *         $client->request('GET', '/');
 *
 *         self::assertResponseSetsDeclaredCookiesOnly($client->getResponse());
 *     }
 * }
 * ```
 *
 * The inventory is the application's, read from the test container.
 */
trait AssertsDeclaredCookies
{
    public static function assertResponseSetsDeclaredCookiesOnly(Response $response, string $message = ''): void
    {
        $inventory = static::getContainer()->get(CookieInventory::class);
        Assert::assertInstanceOf(CookieInventory::class, $inventory);

        $undeclared = UndeclaredCookies::in($response, $inventory);

        Assert::assertSame([], $undeclared, '' !== $message ? $message : \sprintf(
            'The response sets cookies the inventory does not declare: %s. Declare each in the cookie inventory, with its category, purpose and duration.',
            implode(', ', $undeclared),
        ));
    }
}
