<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\ApiKeysBundle\Application\ApiTokens;
use Phil\ApiKeysBundle\Application\Exception\TokenNotFound;
use Phil\ApiKeysBundle\Application\Exception\UnknownScope;
use Phil\ApiKeysBundle\Application\IssuedToken;
use Phil\ApiKeysBundle\Application\OwnerTypes;
use Phil\ApiKeysBundle\Domain\Event\TokenRevoked;
use Phil\ApiKeysBundle\Domain\Exception\InvalidTokenValue;
use Phil\ApiKeysBundle\Domain\Model\ApiToken;
use Phil\ApiKeysBundle\Domain\Token\TokenOwner;
use Phil\ApiKeysBundle\Tests\Unit\Fixture\DetachedRepository;
use Phil\ApiKeysBundle\Tests\Unit\Fixture\RecordingDispatcher;
use Phil\ApiKeysBundle\Tests\Unit\Fixture\Tokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tokens of members, organisations and applications.
 */
#[CoversClass(TokenOwner::class)]
#[CoversClass(OwnerTypes::class)]
#[CoversClass(ApiTokens::class)]
#[CoversClass(ApiToken::class)]
final class OwnersTest extends TestCase
{
    public function testAnOwnerIsATypeAndAnIdentifier(): void
    {
        $owner = new TokenOwner('organization', 'acme');
        $member = TokenOwner::user('acme');

        self::assertSame(['organization', 'acme'], [$owner->type, $owner->id]);
        self::assertSame(['user', 'acme'], [$member->type, $member->id]);
        self::assertFalse($owner->isUser());
        self::assertTrue($member->isUser());
        self::assertFalse($owner->equals($member));
        self::assertFalse($owner->equals(new TokenOwner('organization', 'acme2')));
        self::assertTrue($owner->equals(new TokenOwner('organization', 'acme')));
        self::assertSame($owner, TokenOwner::of($owner));
        self::assertTrue(TokenOwner::of('acme')->equals($member));
        self::assertSame('a', new TokenOwner('a', str_repeat('x', 180))->type);
        self::assertSame('a_1'.str_repeat('b', 29), new TokenOwner('a_1'.str_repeat('b', 29), 'x')->type);
        self::assertSame([32, 180], [TokenOwner::TYPE_MAX_LENGTH, TokenOwner::ID_MAX_LENGTH]);
    }

    public function testAnOwnerOutOfShapeIsRefused(): void
    {
        foreach ([
            'The type of a token owner is a lowercase letter followed by up to 31 lowercase letters, digits or "_".' => [['Org', 'a'], ['1org', 'a'], ['', 'a'], ['a'.str_repeat('b', 32), 'a'], ["org\n", 'a'], ['org-x', 'a']],
            'The owner of a token is 1 to 180 bytes.' => [['org', ''], ['org', str_repeat('x', 181)]],
        ] as $message => $cases) {
            foreach ($cases as [$type, $id]) {
                try {
                    new TokenOwner($type, $id);
                    self::fail(\sprintf('"%s" "%s" was accepted.', $type, $id));
                } catch (InvalidTokenValue $invalidTokenValue) {
                    self::assertSame($message, $invalidTokenValue->getMessage());
                }
            }
        }
    }

    public function testTheOwnerTypesAndTheirScopes(): void
    {
        $types = new OwnerTypes(['organization' => ['billing'], 'application' => null]);

        self::assertSame(['user', 'organization', 'application'], $types->names());
        self::assertTrue($types->has('user'));
        self::assertTrue($types->has('application'));
        self::assertFalse($types->has('team'));
        self::assertNull($types->scopes('user'));
        self::assertSame(['billing'], $types->scopes('organization'));
        self::assertNull($types->scopes('application'));
        self::assertSame(['user'], new OwnerTypes()->names());

        $types->check(new TokenOwner('organization', 'acme'), ['billing']);
        $types->check(new TokenOwner('organization', 'acme'), []);
        $types->check(new TokenOwner('application', 'ci'), ['billing', 'projects:write']);
        $types->check(TokenOwner::user('ada'), ['billing', 'projects:write']);

        foreach ([
            'A token of the owner type "organization" does not hold the scope "projects:read".' => static fn () => $types->check(new TokenOwner('organization', 'acme'), ['billing', 'projects:read']),
            'The owner type "team" is not declared; the declared types are: user, organization, application.' => static fn () => $types->check(new TokenOwner('team', 'a'), []),
            'The owner type "team" is not declared; the declared types are: user, organization, application' => static fn (): ?array => $types->scopes('team'),
            'An owner type is a lowercase letter followed by up to 31 lowercase letters, digits or "_"; "Org" is not.' => static fn (): OwnerTypes => new OwnerTypes(['Org' => null]),
        ] as $message => $call) {
            try {
                $call();
                self::fail($message);
            } catch (InvalidTokenValue $invalidTokenValue) {
                self::assertStringStartsWith($message, $invalidTokenValue->getMessage());
            }
        }
    }

    public function testATokenActsForATypedOwnerWithinItsScopes(): void
    {
        $tokens = $this->tokens();
        $acme = new TokenOwner('organization', 'acme');
        $issued = $tokens->create($acme, 'Export', ['billing']);

        self::assertSame(['organization', 'acme'], [$issued->token->ownerType(), $issued->token->owner()]);
        self::assertTrue($issued->token->tokenOwner()->equals($acme));
        self::assertTrue($issued->token->belongsTo($acme));
        self::assertFalse($issued->token->belongsTo(TokenOwner::user('acme')));
        self::assertFalse($issued->token->belongsTo(new TokenOwner('organization', 'other')));
        self::assertSame($issued->token->id(), $tokens->verify($issued->plainText)->id());
        self::assertSame('user', $tokens->create('ada', 'Mine', [])->token->ownerType());

        foreach ([
            static fn (): IssuedToken => $tokens->create($acme, 'Write', ['projects:write']),
            static fn (): IssuedToken => $tokens->create(new TokenOwner('team', 'a'), 'Team', []),
        ] as $call) {
            try {
                $call();
                self::fail('The token was created.');
            } catch (InvalidTokenValue) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(UnknownScope::class);
        $tokens->create($acme, 'Unknown', ['admin']);
    }

    public function testATokenIsFoundAndRevokedOnlyForItsOwner(): void
    {
        $tokens = $this->tokens();
        $acme = new TokenOwner('organization', 'acme');
        $organization = $tokens->create($acme, 'Org', [])->token;
        $member = $tokens->create('acme', 'Member', [])->token;

        self::assertSame([$organization->id()], array_map(static fn (ApiToken $token): string => $token->id(), $tokens->find($acme)));
        self::assertSame([$member->id()], array_map(static fn (ApiToken $token): string => $token->id(), $tokens->find('acme')));
        self::assertSame($organization->id(), $tokens->get($organization->id(), $acme)->id());
        self::assertSame($member->id(), $tokens->get($member->id(), 'acme')->id());

        foreach ([[$organization->id(), 'acme'], [$member->id(), $acme], [$organization->id(), new TokenOwner('application', 'acme')]] as [$id, $owner]) {
            try {
                $tokens->revoke($id, $owner);
                self::fail('Another owner revoked the token.');
            } catch (TokenNotFound) {
                self::addToAssertionCount(1);
            }
        }

        self::assertTrue($tokens->revoke($organization->id(), $acme)->isRevoked());
    }

    public function testEveryActiveTokenIsRevokedWithoutADispatcher(): void
    {
        $repository = new DetachedRepository();
        $tokens = $this->tokens($repository);
        $acme = new TokenOwner('organization', 'acme');
        $token = $tokens->create($acme, 'One', [])->token;

        self::assertSame(1, $tokens->revokeAll($acme));
        self::assertTrue($repository->get($token->id())?->isRevoked());
    }

    public function testEveryActiveTokenOfAnOwnerIsRevoked(): void
    {
        $repository = new DetachedRepository();
        $events = new RecordingDispatcher();
        $tokens = $this->tokens($repository, $events);
        $acme = new TokenOwner('organization', 'acme');
        $first = $tokens->create($acme, 'One', [])->token;
        $second = $tokens->create($acme, 'Two', [])->token;
        $already = $tokens->create($acme, 'Revoked', [])->token;
        $tokens->revoke($already->id());
        $member = $tokens->create('acme', 'Member', [])->token;
        $events->clear();

        self::assertSame(2, $tokens->revokeAll($acme));
        self::assertTrue($repository->get($first->id())?->isRevoked());
        self::assertTrue($repository->get($second->id())?->isRevoked());
        self::assertFalse($repository->get($member->id())?->isRevoked());
        self::assertEqualsCanonicalizing([new TokenRevoked($first->id()), new TokenRevoked($second->id())], $events->events);

        $events->clear();
        self::assertSame(0, $tokens->revokeAll($acme));
        self::assertSame([], $events->events);
        self::assertSame(1, $tokens->revokeAll('acme'));
        self::assertSame(10_000, ApiTokens::REVOKE_ALL_LIMIT);
    }

    public function testARotatedTokenKeepsItsOwner(): void
    {
        $tokens = $this->tokens();
        $acme = new TokenOwner('organization', 'acme');
        $token = $tokens->create($acme, 'Export', ['billing'], new DateTimeImmutable('2026-12-08 10:00:00 UTC'))->token;

        $successor = $tokens->rotate($token->id(), $acme)->token;

        self::assertTrue($successor->belongsTo($acme));
        self::assertSame(['billing'], $successor->scopes());
        self::assertTrue($token->isRevoked());
    }

    private function tokens(?DetachedRepository $repository = null, ?RecordingDispatcher $events = null): ApiTokens
    {
        return Tokens::service($repository, events: $events, owners: new OwnerTypes(['organization' => ['billing'], 'application' => null]));
    }
}
