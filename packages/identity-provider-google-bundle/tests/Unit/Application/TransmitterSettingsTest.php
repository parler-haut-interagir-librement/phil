<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Tests\Unit\Application;

use InvalidArgumentException;
use Phil\IdentityProviderGoogleBundle\Application\TransmitterSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransmitterSettings::class)]
final class TransmitterSettingsTest extends TestCase
{
    public function testGoogle(): void
    {
        $google = TransmitterSettings::google(['client']);

        self::assertSame('google', $google->provider);
        self::assertSame('https://accounts.google.com/', $google->issuer);
        self::assertSame(['client'], $google->audiences);
        self::assertSame(['https://accounts.google.com', 'accounts.google.com'], $google->subjectIssuers);
        self::assertSame(604_800, $google->maxAge);
        self::assertSame(300, $google->clockSkew);
        self::assertSame(3600, TransmitterSettings::google(['client'], 3600)->maxAge);
    }

    public function testIssuersAreComparedWithoutATrailingSlash(): void
    {
        $google = TransmitterSettings::google(['client']);

        self::assertTrue($google->isIssuer('https://accounts.google.com'));
        self::assertTrue($google->isIssuer('https://accounts.google.com/'));
        self::assertFalse($google->isIssuer('https://accounts.google.com.evil'));
        self::assertTrue($google->acceptsSubjectIssuer('https://accounts.google.com/'));
        self::assertTrue($google->acceptsSubjectIssuer('accounts.google.com'));
        self::assertFalse($google->acceptsSubjectIssuer('https://idp.example'));
        self::assertFalse($google->acceptsSubjectIssuer(null));
        self::assertTrue(new TransmitterSettings('p', 'i', ['a'], ['https://idp.example/'])->acceptsSubjectIssuer('https://idp.example'));
    }

    public function testOneAudienceInCommonIsEnough(): void
    {
        $settings = new TransmitterSettings('p', 'i', ['a', 'b'], ['s']);

        self::assertTrue($settings->acceptsAudience(['x', 'b']));
        self::assertFalse($settings->acceptsAudience(['x']));
        self::assertFalse($settings->acceptsAudience([]));
    }

    public function testTheBoundsAreAccepted(): void
    {
        self::assertSame(60, new TransmitterSettings('p', 'i', ['a'], ['s'], 60, 0)->maxAge);
        self::assertSame(600, new TransmitterSettings('p', 'i', ['a'], ['s'], 2_592_000, 600)->clockSkew);
    }

    /**
     * @return iterable<string, array{callable(): TransmitterSettings, string}>
     */
    public static function invalid(): iterable
    {
        $incomplete = 'A transmitter has an issuer, one audience at least and the issuers of its subjects.';

        yield 'no issuer' => [static fn (): TransmitterSettings => new TransmitterSettings('p', '', ['a'], ['s']), $incomplete];
        yield 'no audience' => [static fn (): TransmitterSettings => new TransmitterSettings('p', 'i', [], ['s']), $incomplete];
        yield 'an empty audience' => [static fn (): TransmitterSettings => new TransmitterSettings('p', 'i', ['a', ''], ['s']), $incomplete];
        yield 'no subject issuer' => [static fn (): TransmitterSettings => new TransmitterSettings('p', 'i', ['a'], []), $incomplete];
        yield 'an age too short' => [static fn (): TransmitterSettings => new TransmitterSettings('p', 'i', ['a'], ['s'], 59), 'A security event token may be 60 seconds to 30 days old.'];
        yield 'an age too long' => [static fn (): TransmitterSettings => new TransmitterSettings('p', 'i', ['a'], ['s'], 2_592_001), 'A security event token may be 60 seconds to 30 days old.'];
        yield 'a negative skew' => [static fn (): TransmitterSettings => new TransmitterSettings('p', 'i', ['a'], ['s'], 60, -1), 'The clock skew is 0 to 600 seconds.'];
        yield 'a skew too large' => [static fn (): TransmitterSettings => new TransmitterSettings('p', 'i', ['a'], ['s'], 60, 601), 'The clock skew is 0 to 600 seconds.'];
    }

    /**
     * @param callable(): TransmitterSettings $settings
     */
    #[DataProvider('invalid')]
    public function testInvalidSettingsAreRefused(callable $settings, string $message): void
    {
        $this->expectExceptionObject(new InvalidArgumentException($message));

        $settings();
    }

    public function testTheDefaultAgeIsAWeek(): void
    {
        self::assertSame(604_800, new TransmitterSettings('p', 'i', ['a'], ['s'])->maxAge);
        self::assertSame(300, new TransmitterSettings('p', 'i', ['a'], ['s'])->clockSkew);
    }
}
