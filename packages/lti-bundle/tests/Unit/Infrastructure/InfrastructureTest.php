<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Unit\Infrastructure;

use Phil\Lti\Message\ContextRole;
use Phil\Lti\Message\Launch;
use Phil\Lti\Message\LaunchMessage;
use Phil\Lti\Message\MessageType;
use Phil\Lti\Message\Person;
use Phil\Lti\Message\ResourceLink;
use Phil\Lti\Message\Roles;
use Phil\LtiBundle\Infrastructure\Console\PurgeCommand;
use Phil\LtiBundle\Infrastructure\Console\RotateKeysCommand;
use Phil\LtiBundle\Infrastructure\Dbal\DbalAccessTokens;
use Phil\LtiBundle\Infrastructure\Dbal\DbalKeyStore;
use Phil\LtiBundle\Infrastructure\Dbal\DbalLoginStates;
use Phil\LtiBundle\Infrastructure\Dbal\DbalNonces;
use Phil\LtiBundle\Infrastructure\Dbal\LtiTables;
use Phil\LtiBundle\Infrastructure\Dbal\Row;
use Phil\LtiBundle\Infrastructure\KeyManagement\KeyManagementSealer;
use Phil\LtiBundle\Infrastructure\Security\LtiUser;
use Phil\LtiBundle\Infrastructure\Security\LtiUserProvider;
use Phil\LtiBundle\Tests\Unit\Fixture\PlainSealer;
use Phil\LtiBundle\Tests\Unit\Fixture\Stores;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\SodiumKms;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use UnexpectedValueException;

#[CoversClass(KeyManagementSealer::class)]
#[CoversClass(LtiUser::class)]
#[CoversClass(LtiUserProvider::class)]
#[CoversClass(RotateKeysCommand::class)]
#[CoversClass(PurgeCommand::class)]
#[CoversClass(Row::class)]
#[CoversClass(LtiTables::class)]
final class InfrastructureTest extends TestCase
{
    public function testKeysAreSealedByTheKmsAndBoundToTheirIdentifier(): void
    {
        $sealer = new KeyManagementSealer(new SodiumKms(new InMemoryKeyLoader(['lti' => str_repeat('k', 32)])), 'lti');
        $sealed = $sealer->seal('kid-1', 'pem');

        self::assertSame('pem', $sealer->open('kid-1', $sealed));
        self::assertStringNotContainsString('pem', $sealed);

        foreach ([['kid-2', $sealed], ['kid-1', 'not base 64!'], ['kid-1', base64_encode('garbage')]] as [$kid, $value]) {
            try {
                $sealer->open($kid, $value);
                self::fail('A sealed key opened.');
            } catch (RuntimeException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(RuntimeException::class);
        new KeyManagementSealer(new SodiumKms(new InMemoryKeyLoader([])), 'missing')->seal('kid', 'pem');
    }

    public function testTheUserOfALaunchCarriesItsRoles(): void
    {
        $learner = LtiUser::fromLaunch($this->launch('u', [ContextRole::Learner]));
        $teacher = LtiUser::fromLaunch($this->launch('u', [ContextRole::Instructor]));
        $anonymous = LtiUser::fromLaunch($this->launch(null, []));

        self::assertSame(['ROLE_LTI', 'ROLE_LTI_LEARNER'], $learner->getRoles());
        self::assertSame(['ROLE_LTI', 'ROLE_LTI_INSTRUCTOR'], $teacher->getRoles());
        self::assertSame(['ROLE_LTI'], $anonymous->getRoles());
        self::assertSame(64, \strlen($learner->getUserIdentifier()));
        self::assertMatchesRegularExpression('/^anonymous-[0-9a-f]{32}$/D', $anonymous->getUserIdentifier());

        $provider = new LtiUserProvider();
        self::assertSame($learner, $provider->refreshUser($learner));
        self::assertTrue($provider->supportsClass(LtiUser::class));
        self::assertFalse($provider->supportsClass(InMemoryUser::class));

        try {
            $provider->refreshUser(new InMemoryUser('a', null));
            self::fail('Another user was refreshed.');
        } catch (UnsupportedUserException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(UserNotFoundException::class);
        $provider->loadUserByIdentifier('x');
    }

    public function testTheCommandsRotateAndPurge(): void
    {
        $clock = new MockClock('2026-11-14 12:00:00 UTC');
        $tables = new LtiTables();
        $connection = Stores::sqlite($tables);
        $keys = new DbalKeyStore($connection, $tables, new PlainSealer(), $clock, 3600);
        $command = new RotateKeysCommand($keys, 86_400);

        $output = new BufferedOutput();
        self::assertSame(0, $command(new SymfonyStyle(new ArrayInput([]), $output), true));
        self::assertStringContainsString('The signing key is now', $output->fetch());
        $first = $keys->signingKey()->keyId();

        self::assertSame(0, $command(new SymfonyStyle(new ArrayInput([]), $output), true));
        self::assertStringContainsString('The signing key is not due; 0 retired keys removed.', $output->fetch());
        self::assertSame($first, $keys->signingKey()->keyId());

        $command(new SymfonyStyle(new ArrayInput([]), $output));
        self::assertNotSame($first, $keys->signingKey()->keyId());

        $nonces = new DbalNonces($connection, $tables, $clock);
        $nonces->use('s', 'v', $clock->now());

        $purge = new PurgeCommand($nonces, new DbalLoginStates($connection, $tables, $clock), new DbalAccessTokens($connection, $tables, $clock));
        self::assertSame(0, $purge(new SymfonyStyle(new ArrayInput([]), $output)));
        self::assertStringContainsString('Removed 1 nonces, 0 login states and 0 access tokens.', $output->fetch());
        new PurgeCommand($nonces)(new SymfonyStyle(new ArrayInput([]), $output));
        self::assertStringContainsString('Removed 0 nonces, 0 login states and 0 access tokens.', $output->fetch());
    }

    public function testRowsAreReadWhateverTheDriverGives(): void
    {
        self::assertSame(3, Row::int(['a' => '3'], 'a'));
        self::assertSame(-3, Row::int(['a' => '-3'], 'a'));
        self::assertSame(3, Row::int(['a' => 3], 'a'));
        self::assertNull(Row::optionalInt(['a' => null], 'a'));
        self::assertSame(1.5, Row::float(['a' => '1.5'], 'a'));
        self::assertSame(2.0, Row::float(['a' => 2], 'a'));
        self::assertSame(2.5, Row::float(['a' => 2.5], 'a'));
        self::assertNull(Row::optionalString(['a' => null], 'a'));
        self::assertSame('x', Row::optionalString(['a' => 'x'], 'a'));

        foreach ([static fn (): int => Row::int(['a' => '3.5'], 'a'), static fn (): int => Row::int(['a' => ' 3'], 'a'), static fn (): int => Row::int([], 'a'), static fn (): float => Row::float(['a' => 'x'], 'a'), static fn (): string => Row::string(['a' => 3], 'a')] as $read) {
            try {
                $read();
                self::fail('A wrong value was read.');
            } catch (UnexpectedValueException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectExceptionMessageIsOrContains('is not a prefix of table names');
        new LtiTables('9bad');
    }

    /**
     * @param list<ContextRole> $roles
     */
    private function launch(?string $subject, array $roles): Launch
    {
        return new Launch('https://lms.example.org', 'c', new LaunchMessage(MessageType::ResourceLink, 'd', Url::fromString('https://tool.example.com/'), new Person($subject), Roles::of($roles), new ResourceLink('l')), []);
    }
}
