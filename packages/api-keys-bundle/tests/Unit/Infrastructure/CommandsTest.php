<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\ApiKeysBundle\Application\OwnerTypes;
use Phil\ApiKeysBundle\Domain\Token\TokenOwner;
use Phil\ApiKeysBundle\Infrastructure\Console\CreateTokenCommand;
use Phil\ApiKeysBundle\Infrastructure\Console\ListTokensCommand;
use Phil\ApiKeysBundle\Infrastructure\Console\PurgeExpiredTokensCommand;
use Phil\ApiKeysBundle\Infrastructure\Console\RevokeTokenCommand;
use Phil\ApiKeysBundle\Infrastructure\Memory\InMemoryApiTokenRepository;
use Phil\ApiKeysBundle\Tests\Unit\Fixture\Tokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(CreateTokenCommand::class)]
#[CoversClass(ListTokensCommand::class)]
#[CoversClass(RevokeTokenCommand::class)]
#[CoversClass(PurgeExpiredTokensCommand::class)]
final class CommandsTest extends TestCase
{
    public function testCreatingATokenPrintsItOnce(): void
    {
        $repository = new InMemoryApiTokenRepository();
        $clock = new MockClock(Tokens::NOW);
        $command = new CommandTester(new CreateTokenCommand(Tokens::service($repository, $clock), $clock));

        self::assertSame(Command::SUCCESS, $command->execute(['owner' => 'alice@example.com', 'name' => 'Deploy', '--scope' => ['billing', 'projects:read'], '--expires' => '+10 days']));
        [$token] = $repository->find(null, 10);
        $lines = explode("\n", trim($command->getDisplay()));

        self::assertSame(\sprintf('Token %s created for user alice@example.com, expiring 2026-11-18T10:00:00+00:00. Copy it now: it is not shown again.', $token->id()), $lines[0]);
        self::assertMatchesRegularExpression('/^test_'.$token->id().'_[0-9A-Za-z]{38}$/', $lines[1]);
        self::assertCount(2, $lines);
        self::assertSame(['projects:read', 'billing'], $token->scopes());

        $definition = new CreateTokenCommand(Tokens::service(), $clock)->getDefinition();
        self::assertTrue($definition->getOption('scope')->isArray());
        self::assertTrue($definition->getOption('scope')->isValueRequired());

        self::assertSame(Command::SUCCESS, $command->execute(['owner' => 'bob', 'name' => 'Default']));
        self::assertEquals(new DateTimeImmutable('2026-12-08 10:00:00 UTC'), $repository->find(TokenOwner::user('bob'), 1)[0]->expiresAt());

        self::assertSame(Command::SUCCESS, $command->execute(['owner' => 'carol', 'name' => 'Dated', '--expires' => '2027-01-31']));
        self::assertEquals(new DateTimeImmutable('2027-01-31 10:00:00 UTC'), $repository->find(TokenOwner::user('carol'), 1)[0]->expiresAt());
        self::assertSame(TokenOwner::USER, $definition->getOption('owner-type')->getDefault());
    }

    public function testCreatingATokenForAnotherOwnerType(): void
    {
        $repository = new InMemoryApiTokenRepository();
        $clock = new MockClock(Tokens::NOW);
        $tokens = Tokens::service($repository, $clock, owners: new OwnerTypes(['organization' => ['billing']]));
        $command = new CommandTester(new CreateTokenCommand($tokens, $clock));

        self::assertSame(Command::SUCCESS, $command->execute(['owner' => 'acme', 'name' => 'Export', '--owner-type' => 'organization', '--scope' => ['billing']]));
        [$token] = $repository->find(new TokenOwner('organization', 'acme'), 1);
        self::assertStringStartsWith(\sprintf('Token %s created for organization acme, expiring', $token->id()), $command->getDisplay());

        self::assertSame(Command::INVALID, $command->execute(['owner' => 'acme', 'name' => 'Export', '--owner-type' => 'organization', '--scope' => ['projects:read']]));
        self::assertSame("A token of the owner type \"organization\" does not hold the scope \"projects:read\".\n", $command->getDisplay());
        self::assertSame(Command::INVALID, $command->execute(['owner' => 'acme', 'name' => 'Export', '--owner-type' => 'team']));
        self::assertSame("The owner type \"team\" is not declared; the declared types are: user, organization.\n", $command->getDisplay());
        self::assertSame(Command::INVALID, $command->execute(['owner' => 'acme', 'name' => 'Export', '--owner-type' => 'Team']));
        self::assertCount(1, $repository->find(null, 10));
    }

    public function testABadTokenIsNotCreated(): void
    {
        $repository = new InMemoryApiTokenRepository();
        $clock = new MockClock(Tokens::NOW);
        $command = new CommandTester(new CreateTokenCommand(Tokens::service($repository, $clock), $clock));

        self::assertSame(Command::INVALID, $command->execute(['owner' => 'alice', 'name' => 'n', '--expires' => 'whenever']));
        self::assertSame("The expiry is a date, or a relative time such as \"+30 days\".\n", $command->getDisplay());
        self::assertSame(Command::INVALID, $command->execute(['owner' => 'alice', 'name' => 'n', '--scope' => ['admin']]));
        self::assertStringStartsWith('The scope "admin" is not declared', $command->getDisplay());
        self::assertSame(Command::INVALID, $command->execute(['owner' => 'alice', 'name' => ' ']));
        self::assertSame("The name of a token is 1 to 100 characters.\n", $command->getDisplay());
        self::assertSame(Command::INVALID, $command->execute(['owner' => 'alice', 'name' => 'n', '--expires' => '+2 years']));
        self::assertSame("A token lasts at most 365 days.\n", $command->getDisplay());
        self::assertSame([], $repository->find(null, 10));
    }

    public function testListingTokens(): void
    {
        $clock = new MockClock(Tokens::NOW);
        $tokens = Tokens::service(clock: $clock);
        $active = $tokens->create('alice', 'Active', ['projects:read', 'billing'], new DateTimeImmutable('2026-12-01 00:00:00 UTC'))->token;
        $clock->sleep(60);
        $revoked = $tokens->create('alice', 'Revoked', [])->token;
        $tokens->revoke($revoked->id());
        $clock->sleep(60);
        $expired = $tokens->create('bob', 'Expired', [], new DateTimeImmutable('2026-11-08 10:03:00 UTC'));
        $tokens->verify($expired->plainText);
        $clock->sleep(60);
        $command = new CommandTester(new ListTokensCommand($tokens, $clock));

        self::assertSame(Command::SUCCESS, $command->execute([]));
        $display = $command->getDisplay();
        self::assertStringContainsString('| ID           | Owner type | Owner | Name    | Scopes                | Created          | Expires          | Last used        | State   |', $display);
        self::assertStringContainsString(\sprintf('| %s | user       | bob   | Expired |                       | 2026-11-08 10:02 | 2026-11-08 10:03 | 2026-11-08 10:02 | expired |', $expired->token->id()), $display);
        self::assertStringContainsString(\sprintf('| %s | user       | alice | Revoked |                       | 2026-11-08 10:01 | 2026-12-08 10:01 | never            | revoked |', $revoked->id()), $display);
        self::assertStringContainsString(\sprintf('| %s | user       | alice | Active  | projects:read billing | 2026-11-08 10:00 | 2026-12-01 00:00 | never            | active  |', $active->id()), $display);
        self::assertLessThan(strpos($display, $revoked->id()), strpos($display, $expired->token->id()));

        self::assertSame(Command::SUCCESS, $command->execute(['--owner' => 'bob']));
        self::assertStringNotContainsString($active->id(), $command->getDisplay());
        self::assertStringContainsString($expired->token->id(), $command->getDisplay());
        self::assertSame(Command::SUCCESS, $command->execute(['--owner' => 'bob', '--owner-type' => 'organization']));
        self::assertStringNotContainsString($expired->token->id(), $command->getDisplay());
        self::assertSame(Command::INVALID, $command->execute(['--owner' => 'bob', '--owner-type' => 'Bad']));
        self::assertStringStartsWith('The type of a token owner is', $command->getDisplay());
        self::assertSame(Command::SUCCESS, $command->execute(['--limit' => '1']));
        self::assertStringNotContainsString($revoked->id(), $command->getDisplay());
        self::assertStringContainsString($expired->token->id(), $command->getDisplay());

        foreach (['0', 'x'] as $limit) {
            self::assertSame(Command::INVALID, $command->execute(['--limit' => $limit]));
            self::assertSame("The limit is a whole number of at least 1.\n", $command->getDisplay());
        }
    }

    public function testRevokingTokens(): void
    {
        $tokens = Tokens::service();
        $first = $tokens->create('alice', 'one', [])->token;
        $second = $tokens->create('alice', 'two', [])->token;
        $command = new CommandTester(new RevokeTokenCommand($tokens));
        $argument = new RevokeTokenCommand($tokens)->getDefinition()->getArgument('id');
        self::assertTrue($argument->isArray());
        self::assertFalse($argument->isRequired());

        self::assertSame(Command::SUCCESS, $command->execute(['id' => [$first->id(), $second->id()]]));
        self::assertSame(\sprintf("Token %s revoked.\nToken %s revoked.\n", $first->id(), $second->id()), $command->getDisplay());
        self::assertTrue($first->isRevoked());
        self::assertTrue($second->isRevoked());

        self::assertSame(Command::FAILURE, $command->execute(['id' => ['BBBBBBBBBBBB', $first->id()]]));
        self::assertSame(\sprintf("No token \"BBBBBBBBBBBB\".\nToken %s revoked.\n", $first->id()), $command->getDisplay());
    }

    public function testRevokingEveryTokenOfAnOwner(): void
    {
        $tokens = Tokens::service(owners: new OwnerTypes(['organization' => null]));
        $first = $tokens->create(new TokenOwner('organization', 'acme'), 'one', [])->token;
        $second = $tokens->create(new TokenOwner('organization', 'acme'), 'two', [])->token;
        $member = $tokens->create('acme', 'member', [])->token;
        $command = new CommandTester(new RevokeTokenCommand($tokens));

        self::assertSame(Command::SUCCESS, $command->execute(['--owner' => 'acme', '--owner-type' => 'organization']));
        self::assertSame("2 token(s) of organization acme revoked.\n", $command->getDisplay());
        self::assertTrue($first->isRevoked());
        self::assertTrue($second->isRevoked());
        self::assertFalse($member->isRevoked());

        self::assertSame(Command::SUCCESS, $command->execute(['--owner' => 'acme']));
        self::assertSame("1 token(s) of user acme revoked.\n", $command->getDisplay());
        self::assertTrue($member->isRevoked());

        foreach ([[], ['id' => [$first->id()], '--owner' => 'acme']] as $input) {
            self::assertSame(Command::INVALID, $command->execute($input));
            self::assertSame("Name the tokens to revoke, or their owner with --owner, not both.\n", $command->getDisplay());
        }

        self::assertSame(Command::INVALID, $command->execute(['--owner' => 'acme', '--owner-type' => 'Bad']));
        self::assertStringStartsWith('The type of a token owner is', $command->getDisplay());
    }

    public function testPurgingTokens(): void
    {
        $clock = new MockClock(Tokens::NOW);
        $tokens = Tokens::service(clock: $clock);
        $tokens->create('alice', 'one', [], new DateTimeImmutable('2026-11-09 10:00:00 UTC'));
        $tokens->create('alice', 'two', [], new DateTimeImmutable('2026-11-10 10:00:00 UTC'));

        $clock->modify('2026-11-20 10:00:00 UTC');
        $command = new CommandTester(new PurgeExpiredTokensCommand($tokens, 10));

        self::assertSame('10', new PurgeExpiredTokensCommand($tokens, 10)->getDefinition()->getOption('retention')->getDefault());
        self::assertSame(Command::SUCCESS, $command->execute([]));
        self::assertSame("1 tokens removed.\n", $command->getDisplay());
        self::assertSame(Command::SUCCESS, $command->execute(['--retention' => '0']));
        self::assertSame("1 tokens removed.\n", $command->getDisplay());

        foreach (['-1', 'x'] as $retention) {
            self::assertSame(Command::INVALID, $command->execute(['--retention' => $retention]));
            self::assertSame("The retention is a whole number of days, from 0.\n", $command->getDisplay());
        }
    }
}
