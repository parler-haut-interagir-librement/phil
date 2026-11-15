<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Unit\Security;

use DateTimeImmutable;
use InvalidArgumentException;
use Phil\Lti\Exception\UnknownRegistration;
use Phil\Lti\Registration\InMemoryPlatformRegistrations;
use Phil\Lti\Registration\InMemoryToolRegistrations;
use Phil\Lti\Registration\PlatformRegistration;
use Phil\Lti\Registration\ToolRegistration;
use Phil\Lti\Security\InMemoryLoginStates;
use Phil\Lti\Security\InMemoryNonces;
use Phil\Lti\Security\LoginState;
use Phil\Lti\Security\Random;
use Phil\Lti\Service\Scope;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(InMemoryNonces::class)]
#[CoversClass(InMemoryLoginStates::class)]
#[CoversClass(Random::class)]
#[CoversClass(InMemoryPlatformRegistrations::class)]
#[CoversClass(InMemoryToolRegistrations::class)]
#[CoversClass(PlatformRegistration::class)]
#[CoversClass(ToolRegistration::class)]
#[CoversClass(UnknownRegistration::class)]
final class StoresTest extends TestCase
{
    public function testANonceIsUsedOnceUntilItExpires(): void
    {
        $clock = new MockClock('2026-11-14 12:00:00 UTC');
        $nonces = new InMemoryNonces($clock);
        $expiry = new DateTimeImmutable('2026-11-14 12:01:00 UTC');

        self::assertTrue($nonces->use('a', 'n', $expiry));
        self::assertFalse($nonces->use('a', 'n', $expiry));
        self::assertTrue($nonces->use('b', 'n', $expiry));
        self::assertTrue($nonces->use('a', "n\nb", $expiry));
        $clock->sleep(60);
        self::assertTrue($nonces->use('a', 'n', $expiry->modify('+1 minute')));
        self::assertFalse($nonces->use('a', 'n', $expiry));
        $nonces->reset();
        self::assertTrue($nonces->use('a', 'n', $expiry));
    }

    public function testAStateIsTakenOnce(): void
    {
        $clock = new MockClock('2026-11-14 12:00:00 UTC');
        $states = new InMemoryLoginStates($clock);
        $state = new LoginState('s', 'n', 'i', 'c', 't', new DateTimeImmutable('2026-11-14 12:01:00 UTC'));
        $states->save($state);

        self::assertSame($state, $states->take('s'));
        self::assertNull($states->take('s'));

        $states->save($state);
        $clock->sleep(60);
        self::assertNull($states->take('s'));
        $states->save($state);
        $states->reset();

        $clock->sleep(-1);
        self::assertNull($states->take('s'));
    }

    public function testRandomValuesAreLongAndDistinct(): void
    {
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/D', Random::token());
        self::assertNotSame(Random::token(), Random::token());
    }

    public function testPlatformsAreFoundByIssuerAndClient(): void
    {
        $a = $this->platform('a');
        $b = $this->platform('b');
        $platforms = new InMemoryPlatformRegistrations()->add($a, ['d1'])->add($b);

        self::assertSame($a, $platforms->find('https://lms.example.org', 'a'));
        self::assertNull($platforms->find('https://lms.example.org'));
        self::assertNull($platforms->find('https://other.example.org', 'a'));
        self::assertTrue($platforms->hasDeployment($a, 'd1'));
        self::assertFalse($platforms->hasDeployment($b, 'd1'));
        self::assertSame($a, new InMemoryPlatformRegistrations()->add($a)->find('https://lms.example.org'));
        self::assertSame(hash('sha256', "https://lms.example.org\na"), $a->key());
        self::assertSame('https://lms.example.org/token', $a->audience());
    }

    public function testToolsAreFoundByClient(): void
    {
        $tool = new ToolRegistration('c', Url::fromString('https://tool.example.com/login'), [Url::fromString('https://tool.example.com/launch'), Url::fromString('https://app.example.com:8443/launch')], Url::fromString('https://tool.example.com/jwks'), [Scope::SCORE]);
        $tools = new InMemoryToolRegistrations()->add($tool, ['d1']);

        self::assertSame($tool, $tools->find('c'));
        self::assertNull($tools->find('x'));
        self::assertTrue($tools->hasDeployment($tool, 'd1'));
        self::assertFalse($tools->hasDeployment($tool, 'd2'));
        self::assertTrue($tool->allowsRedirect(Url::fromString('https://tool.example.com/launch')));
        self::assertFalse($tool->allowsRedirect(Url::fromString('https://tool.example.com/launch?x=1')));
        self::assertTrue($tool->owns(Url::fromString('https://tool.example.com/anything')));
        self::assertTrue($tool->owns(Url::fromString('https://app.example.com:8443/x')));
        self::assertFalse($tool->owns(Url::fromString('https://app.example.com/x')));
        self::assertFalse($tool->owns(Url::fromString('http://tool.example.com/x')));
        self::assertFalse($tool->owns(Url::fromString('https://evil.example.com/x')));
        self::assertTrue($tool->grants(Scope::SCORE));
        self::assertFalse($tool->grants(Scope::LINE_ITEM));
    }

    public function testARegistrationIsChecked(): void
    {
        $url = Url::fromString('https://lms.example.org/x');
        $cases = [
            static fn (): PlatformRegistration => new PlatformRegistration('', 'c', $url, $url, $url),
            static fn (): PlatformRegistration => new PlatformRegistration(str_repeat('i', 1025), 'c', $url, $url, $url),
            static fn (): PlatformRegistration => new PlatformRegistration('i', '', $url, $url, $url),
            static fn (): PlatformRegistration => new PlatformRegistration('i', str_repeat('c', 256), $url, $url, $url),
            static fn (): ToolRegistration => new ToolRegistration('', $url, [$url], $url),
            static fn (): ToolRegistration => new ToolRegistration(str_repeat('c', 256), $url, [$url], $url),
            static fn (): ToolRegistration => new ToolRegistration('c', $url, [], $url),
        ];

        foreach ($cases as $case) {
            try {
                $case();
                self::fail('An invalid registration was made.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }

        self::assertSame(1024, \strlen(new PlatformRegistration(str_repeat('i', 1024), str_repeat('c', 255), $url, $url, $url)->issuer));
        self::assertSame(255, \strlen(new ToolRegistration(str_repeat('c', 255), $url, [$url], $url)->clientId));
    }

    private function platform(string $client): PlatformRegistration
    {
        return new PlatformRegistration('https://lms.example.org', $client, Url::fromString('https://lms.example.org/auth'), Url::fromString('https://lms.example.org/token'), Url::fromString('https://lms.example.org/jwks'));
    }
}
