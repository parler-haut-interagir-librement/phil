<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Tests\Unit\Domain;

use Phil\StatusPageBundle\Domain\Status\ComponentStatus;
use Phil\StatusPageBundle\Domain\ValueObject\ComponentState;
use Phil\StatusPageBundle\Domain\ValueObject\StatusPage;
use Phil\StatusPageBundle\Tests\Unit\Support\Fixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatusPage::class)]
final class StatusPageTest extends TestCase
{
    public function testThePageIsAsWellAsItsWorstComponent(): void
    {
        [$website, $api, $signIn] = Fixtures::components();
        $page = new StatusPage('Status', Fixtures::now(), [
            new ComponentState($website, ComponentStatus::Maintenance),
            new ComponentState($api, ComponentStatus::PartialOutage),
            new ComponentState($signIn, ComponentStatus::Degraded),
        ], [], [], [], []);

        self::assertSame(ComponentStatus::PartialOutage, $page->status());
        self::assertSame(ComponentStatus::Operational, new StatusPage('Status', Fixtures::now(), [], [], [], [], [])->status());
    }

    public function testComponentsAreGroupedInTheOrderTheirFirstMemberWasDeclared(): void
    {
        [$website, $api, $signIn] = Fixtures::components();
        $states = [new ComponentState($api, ComponentStatus::Operational), new ComponentState($website, ComponentStatus::Operational), new ComponentState($signIn, ComponentStatus::Operational)];
        $page = new StatusPage('Status', Fixtures::now(), $states, [], [], [], []);

        self::assertSame(['Platform' => [$states[0], $states[2]], '' => [$states[1]]], $page->groups());
    }

    public function testAComponentIsNamedByItsNameElseByItsId(): void
    {
        [$website] = Fixtures::components();
        $page = new StatusPage('Status', Fixtures::now(), [new ComponentState($website, ComponentStatus::Operational)], [], [], [], []);

        self::assertSame('Website', $page->componentName('website'));
        self::assertSame('payments', $page->componentName('payments'));
    }
}
