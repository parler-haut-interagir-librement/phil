<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Cookie;

use Phil\PrivacyBundle\Domain\Cookie\DeclaredCookie;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeclaredCookie::class)]
final class DeclaredCookieTest extends TestCase
{
    public function testAPurposeInAnotherLocaleFallsBackOnEnglishEvenWhenItIsNotFirst(): void
    {
        $cookie = new DeclaredCookie('a', null, ['fr' => 'Premier', 'en' => 'English', 'es' => 'Segundo'], 'P1D');

        self::assertSame('English', $cookie->purpose('de'));
        self::assertSame('Premier', $cookie->purpose('fr'));
    }
}
