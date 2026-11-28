<?php

declare(strict_types=1);

namespace Phil\ContentAccess\Tests\Unit;

use DateTimeImmutable;
use LogicException;
use Phil\ContentAccess\Access;
use Phil\ContentAccess\AccessDecider;
use Phil\ContentAccess\AccessFacts;
use Phil\ContentAccess\AccessPolicy;
use Phil\ContentAccess\Condition\AccessConditions;
use Phil\ContentAccess\Decision;
use Phil\ContentAccess\Entitlement\Entitlements;
use Phil\ContentAccess\Entitlement\Grant;
use Phil\ContentAccess\Entitlement\InMemoryEntitlements;
use Phil\ContentAccess\Exception\ReservedFact;
use Phil\ContentAccess\Reason;
use Phil\ContentAccess\Viewer;
use Phil\ContentAccess\WhenDenied;
use Phil\Rules\Json\RuleReader;
use Phil\Rules\Rule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(AccessDecider::class)]
#[CoversClass(Decision::class)]
#[CoversClass(AccessFacts::class)]
#[CoversClass(ReservedFact::class)]
final class AccessDeciderTest extends TestCase
{
    private const string NOW = '2026-11-27T12:00:00+00:00';

    private function rule(string $condition): Rule
    {
        return new RuleReader(AccessConditions::types())->fromJson('{"version": 1, "condition": '.$condition.'}');
    }

    private function decider(?Entitlements $entitlements = null): AccessDecider
    {
        return new AccessDecider(
            $entitlements ?? new InMemoryEntitlements()->with('gold', new Grant('tier:gold', new DateTimeImmutable('2026-01-01'))),
            new MockClock(new DateTimeImmutable(self::NOW)),
        );
    }

    public function testAPublicContentIsReadByEveryoneWithoutReadingTheViewer(): void
    {
        $decision = $this->decider($this->refusingEntitlements())->decide(AccessPolicy::public(), Viewer::anonymous());

        self::assertSame(Access::Full, $decision->access);
        self::assertSame(Reason::Public, $decision->reason);
        self::assertFalse($decision->personal);
        self::assertNull($decision->changesAt);
        self::assertSame([], $decision->explanation);
        self::assertTrue($decision->isGranted());
        self::assertTrue($decision->isVisible());
    }

    public function testAnEndedEarlyAccessIsPublicForGood(): void
    {
        $rule = $this->rule('{"type": "access.feature", "operands": {"feature": "tier:gold"}}');
        $policy = AccessPolicy::restricted($rule)->withPublicFrom(new DateTimeImmutable(self::NOW))->withWhenDenied(WhenDenied::Hidden);

        $decision = $this->decider($this->refusingEntitlements())->decide($policy, Viewer::anonymous());

        self::assertSame(Access::Full, $decision->access);
        self::assertSame(Reason::EarlyAccessEnded, $decision->reason);
        self::assertFalse($decision->personal);
        self::assertNull($decision->changesAt);
    }

    public function testAMemberReadsAContentForMembers(): void
    {
        $from = new DateTimeImmutable('2026-12-01T00:00:00+00:00');
        $decision = $this->decider($this->refusingEntitlements())->decide(AccessPolicy::members()->withPublicFrom($from), Viewer::member('m'));

        self::assertSame(Access::Full, $decision->access);
        self::assertSame(Reason::Member, $decision->reason);
        self::assertTrue($decision->personal);
        self::assertSame($from, $decision->changesAt);
    }

    /**
     * @return iterable<string, array{AccessPolicy, Access, bool}>
     */
    public static function denials(): iterable
    {
        yield 'locked' => [AccessPolicy::members(), Access::Locked, true];
        yield 'preview' => [AccessPolicy::members()->withPreview(), Access::Preview, true];
        yield 'hidden' => [AccessPolicy::members()->withWhenDenied(WhenDenied::Hidden), Access::Hidden, false];
    }

    #[DataProvider('denials')]
    public function testAVisitorIsDeniedAContentForMembersAsThePolicySays(AccessPolicy $policy, Access $access, bool $visible): void
    {
        $decision = $this->decider()->decide($policy, Viewer::anonymous());

        self::assertSame($access, $decision->access);
        self::assertSame(Reason::Anonymous, $decision->reason);
        self::assertTrue($decision->personal);
        self::assertFalse($decision->isGranted());
        self::assertSame($visible, $decision->isVisible());
    }

    public function testARestrictedContentIsReadWhenItsRuleHolds(): void
    {
        $policy = AccessPolicy::restricted($this->rule('{"any": [{"type": "access.role", "operands": {"roles": ["staff"]}}, {"type": "access.feature", "operands": {"feature": "tier:gold"}}]}'));

        $decision = $this->decider()->decide($policy, Viewer::member('gold'));

        self::assertSame(Access::Full, $decision->access);
        self::assertSame(Reason::RulePassed, $decision->reason);
        self::assertTrue($decision->personal);
        self::assertNull($decision->changesAt);
        self::assertCount(1, $decision->explanation);
        self::assertSame('access.feature-held', $decision->explanation[0]->reason);
    }

    public function testADeniedRuleExplainsWhatRemains(): void
    {
        $from = new DateTimeImmutable('2027-01-01T00:00:00+00:00');
        $policy = AccessPolicy::restricted($this->rule('{"type": "access.feature", "operands": {"feature": "tier:gold", "after_days": 400}}'))->withPublicFrom($from)->withPreview();

        $decision = $this->decider()->decide($policy, Viewer::member('gold'));

        self::assertSame(Access::Preview, $decision->access);
        self::assertSame(Reason::RuleFailed, $decision->reason);
        self::assertSame($from, $decision->changesAt);
        self::assertCount(1, $decision->explanation);
        self::assertSame('access.feature-not-yet', $decision->explanation[0]->reason);
        self::assertSame(['feature' => 'tier:gold', 'opens_at' => '2027-02-05T00:00:00+00:00'], $decision->explanation[0]->details);
    }

    public function testAHiddenDenialExplainsNothing(): void
    {
        $policy = AccessPolicy::restricted($this->rule('{"type": "access.member"}'))->withWhenDenied(WhenDenied::Hidden);

        $decision = $this->decider()->decide($policy, Viewer::anonymous());

        self::assertSame(Access::Hidden, $decision->access);
        self::assertSame(Reason::RuleFailed, $decision->reason);
        self::assertSame([], $decision->explanation);
    }

    public function testALockedDenialKeepsItsExplanation(): void
    {
        $decision = $this->decider()->decide(AccessPolicy::restricted($this->rule('{"type": "access.member"}')), Viewer::anonymous());

        self::assertSame(Access::Locked, $decision->access);
        self::assertSame('access.anonymous', $decision->explanation[0]->reason);
    }

    public function testTheRuleReadsTheViewerAndTheApplicationsFacts(): void
    {
        $rule = $this->rule('{"all": [
            {"type": "fact.equals", "operands": {"fact": "lesson.done", "value": true}},
            {"type": "access.institution", "operands": {"domains": ["library.example"]}},
            {"type": "date.compare", "operands": {"fact": "now", "operator": "before", "date": "2026-12-01"}}
        ]}');

        $viewer = Viewer::member('m', [], 'library.example');

        self::assertTrue($this->decider()->decide(AccessPolicy::restricted($rule), $viewer, ['lesson.done' => true])->isGranted());
        self::assertFalse($this->decider()->decide(AccessPolicy::restricted($rule), $viewer, ['lesson.done' => false])->isGranted());
        self::assertFalse($this->decider()->decide(AccessPolicy::restricted($rule), Viewer::member('m'), ['lesson.done' => true])->isGranted());
    }

    public function testTheGrantsOfAVisitorAreNeverRead(): void
    {
        $rule = $this->rule('{"type": "access.feature", "operands": {"feature": "tier:gold"}}');

        $decision = $this->decider($this->refusingEntitlements())->decide(AccessPolicy::restricted($rule), Viewer::anonymous('192.0.2.1'));

        self::assertSame(Reason::RuleFailed, $decision->reason);
        self::assertSame('access.feature-missing', $decision->explanation[0]->reason);
    }

    public function testTheGrantsAreReadOnlyWhenARuleAsksForThem(): void
    {
        $rule = $this->rule('{"any": [{"type": "access.role", "operands": {"roles": ["staff"]}}, {"type": "access.feature", "operands": {"feature": "tier:gold"}}]}');

        $decision = $this->decider($this->refusingEntitlements())->decide(AccessPolicy::restricted($rule), Viewer::member('m', ['staff']));

        self::assertTrue($decision->isGranted());
    }

    public function testTheFactsHoldTheViewerAndTheInstant(): void
    {
        $now = new DateTimeImmutable(self::NOW);
        $grant = new Grant('a', new DateTimeImmutable('2026-01-01'));
        $facts = AccessFacts::for(Viewer::member('m', ['staff'], 'example.org', '192.0.2.1'), new InMemoryEntitlements(['m' => [$grant]]), $now, ['extra' => 1]);

        self::assertSame('m', $facts->get('viewer.id'));
        self::assertSame(['staff'], $facts->get('viewer.roles'));
        self::assertSame('example.org', $facts->get('viewer.email_domain'));
        self::assertSame('192.0.2.1', $facts->get('viewer.ip'));
        self::assertSame([$grant], $facts->get('viewer.grants'));
        self::assertSame($now, $facts->get('now'));
        self::assertSame(1, $facts->get('extra'));

        $anonymous = AccessFacts::for(Viewer::anonymous(), $this->refusingEntitlements(), $now);

        self::assertNull($anonymous->get('viewer.id'));
        self::assertSame([], $anonymous->get('viewer.grants'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function reserved(): iterable
    {
        foreach (AccessFacts::RESERVED as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('reserved')]
    public function testAnApplicationCannotStandInForTheViewerOrTheClock(string $name): void
    {
        $this->expectException(ReservedFact::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The fact "%s" is set by the library from the viewer and the clock; pass another name.', $name));

        $this->decider()->decide(AccessPolicy::restricted($this->rule('{"type": "access.member"}')), Viewer::member('m'), [$name => 'forged']);
    }

    private function refusingEntitlements(): Entitlements
    {
        return new class implements Entitlements {
            public function grantsOf(string $subject): array
            {
                throw new LogicException('The grants are not read.');
            }
        };
    }
}
