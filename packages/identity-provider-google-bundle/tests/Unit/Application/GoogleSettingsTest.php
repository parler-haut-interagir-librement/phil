<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Tests\Unit\Application;

use InvalidArgumentException;
use Phil\IdentityProviderGoogleBundle\Application\GoogleSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(GoogleSettings::class)]
final class GoogleSettingsTest extends TestCase
{
    public function testTheDefaultsAskForTheAddressOnly(): void
    {
        $settings = new GoogleSettings('client');

        self::assertSame('client', $settings->clientId);
        self::assertSame([], $settings->hostedDomains);
        self::assertFalse($settings->requestProfile);
        self::assertSame('select_account', $settings->prompt);
        self::assertSame(60, $settings->clockSkew);
        self::assertSame(600, $settings->attemptLifetime);
        self::assertSame('openid email', $settings->scope());
        self::assertNull($settings->hostedDomainHint());
    }

    public function testTheProfileAndTheDomains(): void
    {
        self::assertSame('openid email profile', new GoogleSettings('client', requestProfile: true)->scope());
        self::assertSame('example.com', new GoogleSettings('client', ['example.com'])->hostedDomainHint());
        self::assertSame('*', new GoogleSettings('client', ['example.com', 'xn--bcher-kva.example'])->hostedDomainHint());
    }

    public function testTheBoundsAreAccepted(): void
    {
        $low = new GoogleSettings(str_repeat('c', 255), [], false, null, 0, 60);
        $high = new GoogleSettings('c', ['a.co'], false, 'none', 300, 3600);

        self::assertNull($low->prompt);
        self::assertSame(0, $low->clockSkew);
        self::assertSame(60, $low->attemptLifetime);
        self::assertSame(300, $high->clockSkew);
        self::assertSame(3600, $high->attemptLifetime);
        self::assertSame('consent', new GoogleSettings('c', prompt: 'consent')->prompt);
    }

    /**
     * @return iterable<string, array{callable(): GoogleSettings, string}>
     */
    public static function invalid(): iterable
    {
        yield 'no client ID' => [static fn (): GoogleSettings => new GoogleSettings(''), 'The client ID is 1 to 255 bytes.'];
        yield 'a client ID too long' => [static fn (): GoogleSettings => new GoogleSettings(str_repeat('c', 256)), 'The client ID is 1 to 255 bytes.'];
        yield 'a domain in capitals' => [static fn (): GoogleSettings => new GoogleSettings('c', ['Example.com']), '"Example.com" is not a domain name in lowercase.'];
        yield 'a domain without a dot' => [static fn (): GoogleSettings => new GoogleSettings('c', ['localhost']), '"localhost" is not a domain name in lowercase.'];
        yield 'a domain with a trailing dot' => [static fn (): GoogleSettings => new GoogleSettings('c', ['example.com.']), '"example.com." is not a domain name in lowercase.'];
        yield 'a domain with a prefix' => [static fn (): GoogleSettings => new GoogleSettings('c', ['@example.com']), '"@example.com" is not a domain name in lowercase.'];
        yield 'another prompt' => [static fn (): GoogleSettings => new GoogleSettings('c', prompt: 'login'), 'The prompt is one of "none", "consent", "select_account", or none.'];
        yield 'a negative skew' => [static fn (): GoogleSettings => new GoogleSettings('c', clockSkew: -1), 'The clock skew is 0 to 300 seconds.'];
        yield 'a skew too large' => [static fn (): GoogleSettings => new GoogleSettings('c', clockSkew: 301), 'The clock skew is 0 to 300 seconds.'];
        yield 'a lifetime too short' => [static fn (): GoogleSettings => new GoogleSettings('c', attemptLifetime: 59), 'A sign-in waits 60 to 3600 seconds for its answer.'];
        yield 'a lifetime too long' => [static fn (): GoogleSettings => new GoogleSettings('c', attemptLifetime: 3601), 'A sign-in waits 60 to 3600 seconds for its answer.'];
    }

    /**
     * @param callable(): GoogleSettings $settings
     */
    #[DataProvider('invalid')]
    public function testInvalidSettingsAreRefused(callable $settings, string $message): void
    {
        $this->expectExceptionObject(new InvalidArgumentException($message));

        $settings();
    }
}
