<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\AccessBundle\Infrastructure\Console\PurgeCommand;
use Phil\AccessBundle\Infrastructure\Tenancy\TenancyTupleScope;
use Phil\AccessBundle\Tests\Fixtures\ListedPartitions;
use Phil\AccessBundle\Tests\Fixtures\RecordingActivation;
use Phil\AccessBundle\Tests\Fixtures\Stage;
use Phil\Resource\ResourceReference;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * `phil:access:purge --all-tenants` on two tenants that keep tuples in partitions of their own.
 */
#[CoversClass(PurgeCommand::class)]
final class PurgeEveryTenantTest extends TestCase
{
    private RecordingActivation $activation;

    private TenantContext $tenants;

    private Stage $stage;

    public function testEachTenantIsEnteredPurgedAndLeftInTurn(): void
    {
        $tester = $this->purge(new PurgeCommand($this->stage->store, $this->tenants, new ListedPartitions(['acme', 'globex'])));

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertMatchesRegularExpression('/acme\s+2\s+purged/', $display);
        self::assertMatchesRegularExpression('/globex\s+1\s+purged/', $display);
        self::assertMatchesRegularExpression('/Tenant\s+Expired tuples deleted\s+Outcome/', $display);
        self::assertStringContainsString('2 tenants purged.', $display);
        self::assertSame(['enter acme', 'leave', 'enter globex', 'leave'], $this->activation->calls);
        self::assertNull($this->tenants->current());
        self::assertSame(['acme' => 0, 'globex' => 0], $this->expiredLeft());
        self::assertSame(['acme' => 1, 'globex' => 1], $this->unexpired(), 'The tuples that have not expired stay, each in its tenant.');
    }

    public function testATenantThatFailsIsLeftReportedAndTheOthersArePurged(): void
    {
        $this->activation->failsOn = 'globex';
        $tester = $this->purge(new PurgeCommand($this->stage->store, $this->tenants, new ListedPartitions(['globex', 'acme'])));

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertMatchesRegularExpression('/globex\s+0\s+failed: The database of <globex> is down\./', $display, 'The message is printed as text, its tags escaped.');
        self::assertMatchesRegularExpression('/acme\s+2\s+purged/', $display);
        self::assertStringContainsString('1 of 2 tenants failed; the others were purged.', $display);
        self::assertSame(['enter globex', 'leave', 'leave', 'enter acme', 'leave'], $this->activation->calls, 'The failed activation is undone, then the tenant is left, before the next one is entered.');
        self::assertNull($this->tenants->current());

        self::assertSame(0, $this->tenants->within(TenantId::fromString('acme'), $this->stage->store->purgeExpired(...)));
        $this->activation->failsOn = null;
        self::assertSame(1, $this->tenants->within(TenantId::fromString('globex'), $this->stage->store->purgeExpired(...)), 'The failed tenant kept its expired tuple.');
    }

    public function testAPartitionThatNamesNoTenantIsReportedAndTheOthersArePurged(): void
    {
        $tester = $this->purge(new PurgeCommand($this->stage->store, $this->tenants, new ListedPartitions([str_repeat('x', TenantId::MAX_LENGTH + 1), 'acme'])));

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('failed: A tenant reference is at most', $tester->getDisplay());
        self::assertSame(['enter acme', 'leave'], $this->activation->calls);
    }

    public function testNoPartitionIsNothingToDo(): void
    {
        $tester = $this->purge(new PurgeCommand($this->stage->store, $this->tenants, new ListedPartitions([])));

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No tenant holds tuples.', $tester->getDisplay());
        self::assertStringNotContainsString('Tenant', $tester->getDisplay());
    }

    public function testTheOptionIsRefusedWithoutTenancy(): void
    {
        $tester = $this->purge(new PurgeCommand($this->stage->store, partitions: new ListedPartitions(['acme', 'globex'])));

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('--all-tenants purges the partition of each tenant', $tester->getDisplay());
        self::assertSame([], $this->activation->calls);
        self::assertSame(['acme' => 2, 'globex' => 1], $this->expiredLeft());
    }

    public function testTheOptionIsRefusedWithinATenant(): void
    {
        $this->tenants->enter(TenantId::fromString('acme'));
        $tester = $this->purge(new PurgeCommand($this->stage->store, $this->tenants, new ListedPartitions(['acme', 'globex'])));

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Give --all-tenants or --tenant, not both.', $tester->getDisplay());
        self::assertSame('acme', $this->tenants->current()?->value);
        self::assertSame(['enter acme'], $this->activation->calls);
    }

    public function testTheOptionIsRefusedWithoutTheListOfPartitions(): void
    {
        $tester = $this->purge(new PurgeCommand($this->stage->store, $this->tenants));

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('--all-tenants needs the partitions that hold tuples', $tester->getDisplay());
        self::assertSame([], $this->activation->calls);
    }

    public function testWithoutTheOptionThePurgeStaysInTheCommandsTenant(): void
    {
        $this->tenants->enter(TenantId::fromString('globex'));
        $tester = $this->purge(new PurgeCommand($this->stage->store, $this->tenants, new ListedPartitions(['acme', 'globex'])), []);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('1 expired tuples deleted.', $tester->getDisplay());
        $this->tenants->leave();
        self::assertSame(['acme' => 2, 'globex' => 0], $this->expiredLeft());
    }

    /**
     * Two tenants, under the same identifiers: in `acme`, two memberships that expired and one that has not; in
     * `globex`, one of each.
     */
    #[Override]
    protected function setUp(): void
    {
        $this->activation = new RecordingActivation();
        $this->tenants = new TenantContext([$this->activation]);
        $this->stage = new Stage(scope: new TenancyTupleScope($this->tenants));
        $group = ResourceReference::of('group', 'supporters');
        $soon = $this->stage->clock->now()->modify('+1 hour');

        foreach (['acme' => ['a', 'b'], 'globex' => ['a']] as $tenant => $expiring) {
            $this->tenants->within(TenantId::fromString($tenant), function () use ($group, $soon, $expiring): void {
                foreach ($expiring as $member) {
                    $this->stage->writer->grant($group, 'member', ResourceReference::of('member', $member), $soon);
                }

                $this->stage->writer->grant($group, 'member', ResourceReference::of('member', 'c'));
            });
        }

        $this->stage->clock->modify('+1 hour');
        $this->activation->calls = [];
    }

    /**
     * @param array<string, bool> $input
     */
    private function purge(PurgeCommand $command, array $input = ['--all-tenants' => true]): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($application->find('phil:access:purge'));
        $tester->execute($input);

        return $tester;
    }

    /**
     * The members whose membership has not expired, in each tenant.
     *
     * @return array<string, int>
     */
    private function unexpired(): array
    {
        $members = [];

        foreach (['acme', 'globex'] as $tenant) {
            $members[$tenant] = $this->tenants->within(TenantId::fromString($tenant), fn (): int => \count($this->stage->store->subjects(ResourceReference::of('group', 'supporters'), 'member', 100)));
        }

        return $members;
    }

    /**
     * The expired tuples each tenant still had: purged now, through the same context.
     *
     * @return array<string, int>
     */
    private function expiredLeft(): array
    {
        $left = [];

        foreach (['acme', 'globex'] as $tenant) {
            $left[$tenant] = $this->tenants->within(TenantId::fromString($tenant), $this->stage->store->purgeExpired(...));
        }

        return $left;
    }
}
