<?php

declare(strict_types=1);

namespace Phil\ContentAccess\Tests\Unit\Condition;

use DateTimeImmutable;
use Phil\ContentAccess\AccessFacts;
use Phil\ContentAccess\Condition\AccessConditions;
use Phil\ContentAccess\Condition\FeatureCondition;
use Phil\ContentAccess\Condition\InstitutionCondition;
use Phil\ContentAccess\Condition\MemberCondition;
use Phil\ContentAccess\Condition\RoleCondition;
use Phil\ContentAccess\Entitlement\Grant;
use Phil\Rules\Condition\Leaf;
use Phil\Rules\Exception\InvalidRule;
use Phil\Rules\Fact\Facts;
use Phil\Rules\Rule;
use Phil\Rules\Type\BuiltIn\FactEquals;
use Phil\Rules\Type\ConditionTypes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MemberCondition::class)]
#[CoversClass(RoleCondition::class)]
#[CoversClass(FeatureCondition::class)]
#[CoversClass(InstitutionCondition::class)]
#[CoversClass(AccessConditions::class)]
final class ConditionsTest extends TestCase
{
    private const string NOW = '2026-11-27T12:00:00+00:00';

    /**
     * @param array<string, mixed> $operands
     * @param array<string, mixed> $facts
     *
     * @return array{bool, string, array<string, scalar|null>}
     */
    private function evaluate(string $type, array $operands, array $facts): array
    {
        $rule = new Rule(new Leaf($type, $operands), AccessConditions::types());
        $step = $rule->evaluate(Facts::of($facts))->trace;

        return ['passed' === $step->outcome->value, (string) $step->reason, $step->details];
    }

    public function testTheTypesAreAddedToTheBuiltInsOrToAGivenRegistry(): void
    {
        self::assertSame(
            ['fact.equals', 'fact.in', 'fact.contains', 'fact.compare', 'fact.present', 'date.compare', 'access.member', 'access.role', 'access.feature', 'access.institution'],
            AccessConditions::types()->names(),
        );
        self::assertSame(['fact.equals', 'access.member', 'access.role', 'access.feature', 'access.institution'], AccessConditions::types(new ConditionTypes(new FactEquals()))->names());
    }

    public function testTheOperandsAreDescribed(): void
    {
        $described = AccessConditions::types()->describe();

        self::assertSame([], $described['access.member']);
        self::assertSame([['name' => 'roles', 'kind' => 'identifier-list', 'required' => true, 'maxItems' => 100]], $described['access.role']);
        self::assertSame([
            ['name' => 'feature', 'kind' => 'identifier', 'required' => true],
            ['name' => 'after_days', 'kind' => 'integer', 'required' => false, 'min' => 0, 'max' => 36500],
            ['name' => 'for_days', 'kind' => 'integer', 'required' => false, 'min' => 1, 'max' => 36500],
        ], $described['access.feature']);
        self::assertSame([
            ['name' => 'domains', 'kind' => 'scalar-list', 'required' => false, 'maxLength' => 253, 'maxItems' => 100],
            ['name' => 'networks', 'kind' => 'scalar-list', 'required' => false, 'maxLength' => 43, 'maxItems' => 100],
        ], $described['access.institution']);
    }

    public function testMember(): void
    {
        self::assertSame([true, 'access.member', []], $this->evaluate('access.member', [], [AccessFacts::VIEWER_ID => 'm']));
        self::assertSame([false, 'access.anonymous', []], $this->evaluate('access.member', [], [AccessFacts::VIEWER_ID => null]));
        self::assertSame([false, 'access.anonymous', []], $this->evaluate('access.member', [], []));
    }

    public function testRole(): void
    {
        self::assertSame([true, 'access.role-held', ['role' => 'staff']], $this->evaluate('access.role', ['roles' => ['editor', 'staff']], [AccessFacts::VIEWER_ROLES => ['staff', 'viewer']]));
        self::assertSame([false, 'access.role-missing', []], $this->evaluate('access.role', ['roles' => ['editor']], [AccessFacts::VIEWER_ROLES => ['Editor']]));
        self::assertSame([false, 'access.role-missing', []], $this->evaluate('access.role', ['roles' => ['editor']], [AccessFacts::VIEWER_ROLES => 'editor']));
        self::assertSame([false, 'access.role-missing', []], $this->evaluate('access.role', ['roles' => ['editor']], []));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<mixed>, bool, string, array<string, scalar|null>}>
     */
    public static function features(): iterable
    {
        $start = new DateTimeImmutable('2026-11-17T12:00:00+00:00');
        $held = new Grant('tier:gold', $start);

        yield 'held' => [['feature' => 'tier:gold'], [$held], true, 'access.feature-held', ['feature' => 'tier:gold']];
        yield 'held from the very instant of its start' => [['feature' => 'tier:gold'], [new Grant('tier:gold', new DateTimeImmutable('2026-11-27T11:00:00+00:00'))], true, 'access.feature-held', ['feature' => 'tier:gold']];
        yield 'a grant of another feature before the right one' => [['feature' => 'tier:gold'], [new Grant('tier:silver', $start), $held], true, 'access.feature-held', ['feature' => 'tier:gold']];
        yield 'another feature' => [['feature' => 'tier:silver'], [$held], false, 'access.feature-missing', ['feature' => 'tier:silver']];
        yield 'no grant' => [['feature' => 'tier:gold'], [], false, 'access.feature-missing', ['feature' => 'tier:gold']];
        yield 'not a grant' => [['feature' => 'tier:gold'], ['tier:gold'], false, 'access.feature-missing', ['feature' => 'tier:gold']];
        yield 'ended' => [['feature' => 'tier:gold'], [new Grant('tier:gold', $start, new DateTimeImmutable(self::NOW))], false, 'access.feature-missing', ['feature' => 'tier:gold']];
        yield 'not started' => [['feature' => 'tier:gold'], [new Grant('tier:gold', new DateTimeImmutable('2026-11-28'))], false, 'access.feature-missing', ['feature' => 'tier:gold']];
        yield 'opened by seniority' => [['feature' => 'tier:gold', 'after_days' => 10], [$held], true, 'access.feature-held', ['feature' => 'tier:gold']];
        yield 'open after zero days' => [['feature' => 'tier:gold', 'after_days' => 0], [$held], true, 'access.feature-held', ['feature' => 'tier:gold']];
        yield 'not open yet' => [['feature' => 'tier:gold', 'after_days' => 11], [$held], false, 'access.feature-not-yet', ['feature' => 'tier:gold', 'opens_at' => '2026-11-28T12:00:00+00:00']];
        yield 'the earliest opening' => [
            ['feature' => 'tier:gold', 'after_days' => 30],
            [new Grant('tier:gold', new DateTimeImmutable('2026-11-20T00:00:00+00:00')), new Grant('tier:gold', new DateTimeImmutable('2026-11-10T00:00:00+00:00')), new Grant('tier:gold', new DateTimeImmutable('2026-11-15T00:00:00+00:00'))],
            false, 'access.feature-not-yet', ['feature' => 'tier:gold', 'opens_at' => '2026-12-10T00:00:00+00:00'],
        ];
        yield 'within its rolling duration' => [['feature' => 'tier:gold', 'for_days' => 11], [$held], true, 'access.feature-held', ['feature' => 'tier:gold']];
        yield 'past its rolling duration' => [['feature' => 'tier:gold', 'for_days' => 10], [$held], false, 'access.feature-elapsed', ['feature' => 'tier:gold']];
        yield 'past its rolling duration by a second' => [['feature' => 'tier:gold', 'for_days' => 10], [new Grant('tier:gold', new DateTimeImmutable('2026-11-17T11:59:59+00:00'))], false, 'access.feature-elapsed', ['feature' => 'tier:gold']];
        yield 'a second grant still within' => [['feature' => 'tier:gold', 'for_days' => 10], [$held, new Grant('tier:gold', new DateTimeImmutable('2026-11-20'))], true, 'access.feature-held', ['feature' => 'tier:gold']];
        yield 'not yet wins over elapsed' => [['feature' => 'tier:gold', 'after_days' => 5, 'for_days' => 8], [new Grant('tier:gold', new DateTimeImmutable('2026-11-01T00:00:00+00:00')), new Grant('tier:gold', new DateTimeImmutable('2026-11-25T00:00:00+00:00'))], false, 'access.feature-not-yet', ['feature' => 'tier:gold', 'opens_at' => '2026-11-30T00:00:00+00:00']];
    }

    /**
     * @param array<string, mixed>       $operands
     * @param list<mixed>                $grants
     * @param array<string, scalar|null> $details
     */
    #[DataProvider('features')]
    public function testFeature(array $operands, array $grants, bool $passed, string $reason, array $details): void
    {
        self::assertSame([$passed, $reason, $details], $this->evaluate('access.feature', $operands, [AccessFacts::VIEWER_GRANTS => $grants, AccessFacts::NOW => new DateTimeImmutable(self::NOW)]));
    }

    public function testFeatureNeedsTheInstantAndAListOfGrants(): void
    {
        self::assertSame([false, 'fact.type', ['fact' => 'now', 'expected' => 'date']], $this->evaluate('access.feature', ['feature' => 'f'], [AccessFacts::VIEWER_GRANTS => []]));
        self::assertSame([false, 'fact.type', ['fact' => 'now', 'expected' => 'date']], $this->evaluate('access.feature', ['feature' => 'f'], [AccessFacts::NOW => '2026-11-27']));
        self::assertSame([false, 'fact.type', ['fact' => 'viewer.grants', 'expected' => 'list']], $this->evaluate('access.feature', ['feature' => 'f'], [AccessFacts::NOW => new DateTimeImmutable(), AccessFacts::VIEWER_GRANTS => 'f']));
        self::assertSame([false, 'access.feature-missing', ['feature' => 'f']], $this->evaluate('access.feature', ['feature' => 'f'], [AccessFacts::NOW => new DateTimeImmutable()]));
    }

    /**
     * @return iterable<string, array{array<string, int>}>
     */
    public static function daysOutOfBounds(): iterable
    {
        yield 'after a negative count' => [['after_days' => -1]];
        yield 'after a century' => [['after_days' => 36501]];
        yield 'for no day' => [['for_days' => 0]];
        yield 'for a century' => [['for_days' => 36501]];
    }

    /**
     * @param array<string, int> $operands
     */
    #[DataProvider('daysOutOfBounds')]
    public function testFeatureRefusesDaysOutOfBounds(array $operands): void
    {
        $this->expectException(InvalidRule::class);

        new Rule(new Leaf('access.feature', ['feature' => 'f', ...$operands]), AccessConditions::types());
    }

    public function testFeatureAcceptsDaysAtTheirBounds(): void
    {
        $rule = new Rule(new Leaf('access.feature', ['feature' => 'f', 'after_days' => 36500, 'for_days' => 1]), AccessConditions::types());

        self::assertSame(['access.feature'], $rule->typeNames());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>, bool, string, array<string, scalar|null>}>
     */
    public static function institutions(): iterable
    {
        $domain = AccessFacts::VIEWER_EMAIL_DOMAIN;
        $ip = AccessFacts::VIEWER_IP;

        yield 'its domain' => [['domains' => ['Library.example']], [$domain => 'library.example'], true, 'access.institution-domain', []];
        yield 'a subdomain' => [['domains' => ['@library.example']], [$domain => 'staff.library.example'], true, 'access.institution-domain', []];
        yield 'a lookalike' => [['domains' => ['library.example']], [$domain => 'evillibrary.example'], false, 'access.institution-none', []];
        yield 'a parent domain' => [['domains' => ['staff.library.example']], [$domain => 'library.example'], false, 'access.institution-none', []];
        yield 'the second domain' => [['domains' => ['a.example', 'b.example']], [$domain => 'b.example'], true, 'access.institution-domain', []];
        yield 'no domain' => [['domains' => ['library.example']], [], false, 'access.institution-none', []];
        yield 'a network' => [['networks' => ['192.0.2.0/24']], [$ip => '192.0.2.77'], true, 'access.institution-network', []];
        yield 'an address' => [['networks' => ['192.0.2.7']], [$ip => '192.0.2.7'], true, 'access.institution-network', []];
        yield 'outside the network' => [['networks' => ['192.0.2.0/24']], [$ip => '198.51.100.1'], false, 'access.institution-none', []];
        yield 'a v6 network' => [['networks' => ['2001:db8::/32']], [$ip => '2001:db8:1::5'], true, 'access.institution-network', []];
        yield 'no address' => [['networks' => ['192.0.2.0/24']], [], false, 'access.institution-none', []];
        yield 'the domain does not match, the network does' => [['domains' => ['a.example'], 'networks' => ['192.0.2.0/24']], [$domain => 'b.example', $ip => '192.0.2.1'], true, 'access.institution-network', []];
        yield 'domains only and an address' => [['domains' => ['a.example']], [$ip => '192.0.2.1'], false, 'access.institution-none', []];
        yield 'nothing declared' => [[], [$domain => 'a.example'], false, 'access.institution-empty', []];
        yield 'a malformed domain' => [['domains' => ['a.example', 'not a domain']], [$domain => 'a.example'], false, 'access.institution-invalid', ['operand' => 'domains', 'index' => 1]];
        yield 'a numeric domain' => [['domains' => [42]], [$domain => '42'], false, 'access.institution-invalid', ['operand' => 'domains', 'index' => 0]];
        yield 'a malformed network' => [['networks' => ['192.0.2.0/24', '192.0.2.0/33']], [$ip => '192.0.2.1'], false, 'access.institution-invalid', ['operand' => 'networks', 'index' => 1]];
        yield 'a numeric network' => [['networks' => [10]], [$ip => '10.0.0.1'], false, 'access.institution-invalid', ['operand' => 'networks', 'index' => 0]];
    }

    /**
     * @param array<string, mixed>       $operands
     * @param array<string, mixed>       $facts
     * @param array<string, scalar|null> $details
     */
    #[DataProvider('institutions')]
    public function testInstitution(array $operands, array $facts, bool $passed, string $reason, array $details): void
    {
        self::assertSame([$passed, $reason, $details], $this->evaluate('access.institution', $operands, $facts));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function networks(): iterable
    {
        yield 'v4' => ['192.0.2.0', true];
        yield 'v4 /0' => ['0.0.0.0/0', true];
        yield 'v4 /32' => ['192.0.2.1/32', true];
        yield 'v4 /33' => ['192.0.2.1/33', false];
        yield 'v6 /128' => ['2001:db8::1/128', true];
        yield 'v6 /64' => ['2001:db8::/64', true];
        yield 'v6 /129' => ['2001:db8::/129', false];
        yield 'v6 /1000' => ['2001:db8::/1000', false];
        yield 'a leading zero' => ['192.0.2.0/024', false];
        yield 'a sign' => ['192.0.2.0/+24', false];
        yield 'an empty prefix' => ['192.0.2.0/', false];
        yield 'a prefix then a newline' => ["192.0.2.0/24\n", false];
        yield 'two prefixes' => ['192.0.2.0/24/8', false];
        yield 'a host name' => ['example.org/24', false];
        yield 'a short address' => ['192.0.2/24', false];
    }

    #[DataProvider('networks')]
    public function testANetworkIsAnAddressWithAnOptionalPrefixOfItsFamily(string $network, bool $valid): void
    {
        self::assertSame($valid, InstitutionCondition::isNetwork($network));
    }
}
