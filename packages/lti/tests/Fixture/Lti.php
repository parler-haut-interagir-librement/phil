<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Fixture;

use Phil\Lti\Jwt\JwtVerifier;
use Phil\Lti\Key\InMemoryKeyStore;
use Phil\Lti\Key\KeySet;
use Phil\Lti\Message\Context;
use Phil\Lti\Message\ContextRole;
use Phil\Lti\Message\LaunchMessage;
use Phil\Lti\Message\MessageType;
use Phil\Lti\Message\Person;
use Phil\Lti\Message\ResourceLink;
use Phil\Lti\Message\Roles;
use Phil\Lti\Platform\LaunchIssuer;
use Phil\Lti\Registration\InMemoryPlatformRegistrations;
use Phil\Lti\Registration\InMemoryToolRegistrations;
use Phil\Lti\Registration\PlatformRegistration;
use Phil\Lti\Registration\ToolRegistration;
use Phil\Lti\Security\InMemoryLoginStates;
use Phil\Lti\Security\InMemoryNonces;
use Phil\Lti\Service\Scope;
use Phil\Lti\Tool\LaunchValidator;
use Phil\Lti\Tool\LoginInitiator;
use Phil\Url\Url;
use Symfony\Component\Clock\MockClock;

/**
 * A platform and a tool registered with each other, on a frozen clock, their keys published by `StaticKeySets`.
 */
final class Lti
{
    public const string ISSUER = 'https://lms.example.org';

    public const string CLIENT = 'client-42';

    public const string DEPLOYMENT = 'deployment-1';

    public const string PLATFORM_JWKS = 'https://lms.example.org/jwks';

    public const string TOOL_JWKS = 'https://tool.example.com/jwks';

    public const string LAUNCH = 'https://tool.example.com/lti/launch';

    public MockClock $clock;

    public StaticKeySets $keySets;

    public InMemoryKeyStore $platformKeys;

    public InMemoryKeyStore $toolKeys;

    public PlatformRegistration $platform;

    public ToolRegistration $tool;

    public InMemoryPlatformRegistrations $platforms;

    public InMemoryToolRegistrations $tools;

    public InMemoryLoginStates $states;

    public InMemoryNonces $nonces;

    public JwtVerifier $verifier;

    public function __construct()
    {
        $this->clock = new MockClock('2026-11-14 12:00:00 UTC');
        $this->platformKeys = new InMemoryKeyStore(Keys::platform());
        $this->toolKeys = new InMemoryKeyStore(Keys::tool());
        $this->keySets = new StaticKeySets()
            ->with(self::PLATFORM_JWKS, $this->platformKeys->publicKeys())
            ->with(self::TOOL_JWKS, $this->toolKeys->publicKeys());
        $this->platform = new PlatformRegistration(self::ISSUER, self::CLIENT, Url::fromString('https://lms.example.org/auth'), Url::fromString('https://lms.example.org/token'), Url::fromString(self::PLATFORM_JWKS));
        $this->tool = new ToolRegistration(self::CLIENT, Url::fromString('https://tool.example.com/lti/login'), [Url::fromString(self::LAUNCH)], Url::fromString(self::TOOL_JWKS), Scope::ALL);
        $this->platforms = new InMemoryPlatformRegistrations()->add($this->platform, [self::DEPLOYMENT]);
        $this->tools = new InMemoryToolRegistrations()->add($this->tool, [self::DEPLOYMENT]);
        $this->states = new InMemoryLoginStates($this->clock);
        $this->nonces = new InMemoryNonces($this->clock);
        $this->verifier = new JwtVerifier($this->keySets, $this->clock);
    }

    public function initiator(): LoginInitiator
    {
        return new LoginInitiator($this->platforms, $this->states, $this->clock);
    }

    public function validator(): LaunchValidator
    {
        return new LaunchValidator($this->platforms, $this->states, $this->nonces, $this->verifier);
    }

    public function issuer(): LaunchIssuer
    {
        return new LaunchIssuer(self::ISSUER, $this->tools, $this->platformKeys, $this->clock);
    }

    public static function message(MessageType $type = MessageType::ResourceLink, string $target = self::LAUNCH): LaunchMessage
    {
        return new LaunchMessage(
            $type,
            self::DEPLOYMENT,
            Url::fromString($target),
            new Person('user-7', 'Ada Lovelace', 'Ada', 'Lovelace', 'ada@example.org'),
            Roles::of([ContextRole::Learner]),
            new ResourceLink('link-1', 'Week 1'),
            new Context('course-1', 'CS101', 'Introduction', [Context::COURSE_OFFERING]),
        );
    }

    public function withPlatformKeys(KeySet ...$sets): void
    {
        $this->keySets->with(self::PLATFORM_JWKS, ...$sets);
    }
}
