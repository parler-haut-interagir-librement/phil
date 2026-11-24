<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Unit\Infrastructure;

use Phil\EmailAddress\DisposableDomains;
use Phil\EmailAddressBundle\Infrastructure\DependencyInjection\EmailAddressFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailAddressFactory::class)]
final class EmailAddressFactoryTest extends TestCase
{
    public function testTheDisposableDomainsAreTheLibrarysWithThoseAdded(): void
    {
        $bundled = DisposableDomains::bundled();
        $merged = EmailAddressFactory::disposableDomains(['throwaway.example']);

        self::assertEquals($bundled, EmailAddressFactory::disposableDomains([]));
        self::assertTrue($merged->contains('throwaway.example'));
        self::assertTrue($merged->contains('sub.throwaway.example'));
        self::assertSame($bundled->count() + 1, $merged->count());
    }
}
