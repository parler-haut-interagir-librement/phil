<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Tests\Unit\Infrastructure;

use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\RouteMenuItem;
use Override;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditViewer\Infrastructure\EasyAdmin\AuditViewerAdmin;
use Phil\AuditViewer\Infrastructure\Security\AuditViewerVoter;
use Phil\AuditViewer\Infrastructure\Security\Contract\AuditAccessPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Strategy\AccessDecisionStrategyInterface;
use Symfony\Component\Security\Core\Authorization\Strategy\AffirmativeStrategy;
use Symfony\Component\Security\Core\Authorization\Strategy\ConsensusStrategy;
use Symfony\Component\Security\Core\Authorization\Strategy\UnanimousStrategy;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Translation\TranslatableMessage;

#[CoversClass(AuditViewerVoter::class)]
#[CoversClass(AuditViewerAdmin::class)]
final class AccessTest extends TestCase
{
    public function testEachAttributeHasItsRole(): void
    {
        $voter = new AuditViewerVoter(new AccessDecisionManager([new RoleVoter()]), 'ROLE_AUDIT_VIEWER', 'ROLE_AUDIT_EXPORT');
        $reader = new UsernamePasswordToken(new InMemoryUser('reader', null, ['ROLE_AUDIT_VIEWER']), 'main', ['ROLE_AUDIT_VIEWER']);
        $exporter = new UsernamePasswordToken(new InMemoryUser('exporter', null, ['ROLE_AUDIT_EXPORT']), 'main', ['ROLE_AUDIT_EXPORT']);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($reader, null, [AuditViewerVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($reader, null, [AuditViewerVoter::EXPORT]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($exporter, null, [AuditViewerVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($exporter, null, [AuditViewerVoter::EXPORT]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote(new NullToken(), null, [AuditViewerVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($reader, null, ['ROLE_AUDIT_VIEWER']));
        self::assertSame(['PHIL_AUDIT_VIEW', 'PHIL_AUDIT_HISTORY', 'PHIL_AUDIT_EXPORT'], [AuditViewerVoter::VIEW, AuditViewerVoter::HISTORY, AuditViewerVoter::EXPORT]);
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($reader, Subject::of('order', '42'), [AuditViewerVoter::HISTORY]), 'The viewer reads every history.');
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($exporter, null, [AuditViewerVoter::HISTORY]));
    }

    public function testTheHistoryHasARoleOfItsOwnBesideTheViewers(): void
    {
        $voter = new AuditViewerVoter(new AccessDecisionManager([new RoleVoter()]), 'ROLE_AUDIT_VIEWER', 'ROLE_AUDIT_EXPORT', 'ROLE_CLERK');
        $clerk = new UsernamePasswordToken(new InMemoryUser('clerk', null, ['ROLE_CLERK']), 'main', ['ROLE_CLERK']);
        $reader = new UsernamePasswordToken(new InMemoryUser('reader', null, ['ROLE_AUDIT_VIEWER']), 'main', ['ROLE_AUDIT_VIEWER']);
        $member = new UsernamePasswordToken(new InMemoryUser('member', null, ['ROLE_USER']), 'main', ['ROLE_USER']);

        $vote = new Vote();
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($clerk, null, [AuditViewerVoter::HISTORY], $vote));
        self::assertSame(['PHIL_AUDIT_HISTORY is granted to ROLE_CLERK.'], $vote->reasons);
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($clerk, null, [AuditViewerVoter::VIEW]), 'The history is not the log.');

        $vote = new Vote();
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($reader, null, [AuditViewerVoter::HISTORY], $vote));
        self::assertSame(['PHIL_AUDIT_HISTORY is granted to ROLE_AUDIT_VIEWER.'], $vote->reasons);

        $vote = new Vote();
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($member, null, [AuditViewerVoter::HISTORY], $vote));
        self::assertSame(['PHIL_AUDIT_HISTORY needs ROLE_CLERK or ROLE_AUDIT_VIEWER.'], $vote->reasons);

        $same = new AuditViewerVoter(new AccessDecisionManager([new RoleVoter()]), 'ROLE_AUDIT_VIEWER', 'ROLE_AUDIT_EXPORT', 'ROLE_AUDIT_VIEWER');
        $vote = new Vote();
        $same->vote($member, null, [AuditViewerVoter::HISTORY], $vote);
        self::assertSame(['PHIL_AUDIT_HISTORY needs ROLE_AUDIT_VIEWER.'], $vote->reasons, 'One role named once.');

        $vote = new Vote();
        new AuditViewerVoter(new AccessDecisionManager([new RoleVoter()]), 'ROLE_AUDIT_VIEWER', 'ROLE_AUDIT_EXPORT')->vote($member, null, [AuditViewerVoter::EXPORT], $vote);
        self::assertSame(['PHIL_AUDIT_EXPORT needs ROLE_AUDIT_EXPORT.'], $vote->reasons);
    }

    /**
     * @return iterable<string, array{AccessDecisionStrategyInterface}>
     */
    public static function strategies(): iterable
    {
        yield 'affirmative' => [new AffirmativeStrategy()];
        yield 'consensus' => [new ConsensusStrategy()];
        yield 'unanimous' => [new UnanimousStrategy()];
    }

    /**
     * The voter beside Symfony's role voter, in an access decision manager of each strategy: what the policy refuses is
     * refused, what the role lacks is refused, and only what both allow is granted. The voter never widens access.
     */
    #[DataProvider('strategies')]
    public function testThePolicyNarrowsTheRolesUnderEveryStrategy(AccessDecisionStrategyInterface $strategy): void
    {
        $policy = new class implements AuditAccessPolicy {
            /** @var list<array{string, mixed}> */
            public array $asked = [];

            #[Override]
            public function allows(string $attribute, mixed $subject, TokenInterface $token): bool
            {
                $this->asked[] = [$attribute, $subject];

                return (!$subject instanceof Subject || 'acme' === $subject->id) && AuditViewerVoter::EXPORT !== $attribute;
            }
        };
        $voter = new AuditViewerVoter(new AccessDecisionManager([new RoleVoter()]), 'ROLE_AUDIT_VIEWER', 'ROLE_AUDIT_EXPORT', 'ROLE_CLERK', $policy);
        $decisions = new AccessDecisionManager([new RoleVoter(), $voter], $strategy);
        $reader = new UsernamePasswordToken(new InMemoryUser('reader', null, ['ROLE_AUDIT_VIEWER', 'ROLE_AUDIT_EXPORT']), 'main', ['ROLE_AUDIT_VIEWER', 'ROLE_AUDIT_EXPORT']);
        $member = new UsernamePasswordToken(new InMemoryUser('member', null, ['ROLE_USER']), 'main', ['ROLE_USER']);

        self::assertTrue($decisions->decide($reader, [AuditViewerVoter::VIEW]));
        self::assertTrue($decisions->decide($reader, [AuditViewerVoter::HISTORY], Subject::of('shop', 'acme')));
        self::assertFalse($decisions->decide($reader, [AuditViewerVoter::HISTORY], Subject::of('shop', 'globex')), 'Refused by the policy, though the role holds.');
        self::assertFalse($decisions->decide($reader, [AuditViewerVoter::EXPORT]), 'Refused by the policy, though the export role holds.');

        foreach ([AuditViewerVoter::VIEW, AuditViewerVoter::HISTORY, AuditViewerVoter::EXPORT] as $attribute) {
            self::assertFalse($decisions->decide($member, [$attribute], Subject::of('shop', 'acme')), $attribute);
            self::assertFalse($decisions->decide(new NullToken(), [$attribute]), $attribute);
        }

        self::assertEquals([
            [AuditViewerVoter::VIEW, null],
            [AuditViewerVoter::HISTORY, Subject::of('shop', 'acme')],
            [AuditViewerVoter::HISTORY, Subject::of('shop', 'globex')],
            [AuditViewerVoter::EXPORT, null],
        ], $policy->asked, 'Asked once a role holds, with the subject; never for an account without the role.');
    }

    public function testARefusalOfThePolicyGivesItsReason(): void
    {
        $policy = new class implements AuditAccessPolicy {
            #[Override]
            public function allows(string $attribute, mixed $subject, TokenInterface $token): bool
            {
                return false;
            }
        };
        $voter = new AuditViewerVoter(new AccessDecisionManager([new RoleVoter()]), 'ROLE_AUDIT_VIEWER', 'ROLE_AUDIT_EXPORT', null, $policy);
        $reader = new UsernamePasswordToken(new InMemoryUser('reader', null, ['ROLE_AUDIT_VIEWER']), 'main', ['ROLE_AUDIT_VIEWER']);

        $vote = new Vote();
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($reader, null, [AuditViewerVoter::VIEW], $vote));
        self::assertSame(['PHIL_AUDIT_VIEW is refused by the access policy to ROLE_AUDIT_VIEWER.'], $vote->reasons);
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($reader, null, [AuditViewerVoter::VIEW]), 'No vote to give the reason to: the refusal stands.');
    }

    public function testTheMenuLinksToTheDashboardsRoutes(): void
    {
        $items = AuditViewerAdmin::menuItems('back_office');

        self::assertCount(2, $items);
        self::assertContainsOnlyInstancesOf(RouteMenuItem::class, $items);
        self::assertSame(['back_office_audit_log', 'back_office_audit_chain'], array_map(static fn (RouteMenuItem $item): ?string => $item->getAsDto()->getRouteName(), $items));
        self::assertSame(['fa fa-list', 'fa fa-link'], array_map(static fn (RouteMenuItem $item): ?string => $item->getAsDto()->getIcon(), $items));
        self::assertEquals([new TranslatableMessage('menu.log', [], 'phil_audit_viewer'), new TranslatableMessage('menu.chain', [], 'phil_audit_viewer')], array_map(static fn (RouteMenuItem $item): mixed => $item->getAsDto()->getLabel(), $items));
        self::assertSame('admin_audit_log', AuditViewerAdmin::menuItems()[0]->getAsDto()->getRouteName());
    }
}
