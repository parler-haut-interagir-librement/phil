<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Infrastructure;

use Phil\OrganizationsBundle\Application\OwnershipCheck;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Infrastructure\Console\CheckOwnersCommand;
use Phil\OrganizationsBundle\Infrastructure\Console\ListOrganizationsCommand;
use Phil\OrganizationsBundle\Infrastructure\Console\PurgeInvitationsCommand;
use Phil\OrganizationsBundle\Infrastructure\Console\ShowOrganizationCommand;
use Phil\OrganizationsBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ListOrganizationsCommand::class)]
#[CoversClass(ShowOrganizationCommand::class)]
#[CoversClass(PurgeInvitationsCommand::class)]
#[CoversClass(CheckOwnersCommand::class)]
final class CommandsTest extends TestCase
{
    private Stage $stage;

    private Organization $acme;

    protected function setUp(): void
    {
        $this->stage = new Stage();
        $this->acme = $this->stage->acme();
        $this->stage->organizations->useDomain($this->acme->id, Stage::member('ann'), 'acme.org');
    }

    public function testList(): void
    {
        $other = $this->stage->organizations->create('Bravo', Stage::member('ann'));
        $this->stage->organizations->archive($other->id, Stage::member('ann'));
        $tester = new CommandTester(new ListOrganizationsCommand($this->stage->organizations));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringContainsString($this->acme->id->value, $display);
        self::assertMatchesRegularExpression('/acme-guild\s+\|\s+Acme Guild\s+\|\s+active\s+\|\s+acme\.org/', $display);
        self::assertMatchesRegularExpression('/bravo\s+\|\s+Bravo\s+\|\s+archived\s+\|\s+\|/', $display);
        self::assertLessThan(strpos($display, 'bravo'), strpos($display, 'acme-guild'));

        $tester->execute(['--status' => 'archived']);
        self::assertStringNotContainsString('acme-guild', $tester->getDisplay());
        self::assertStringContainsString('bravo', $tester->getDisplay());

        $tester->execute(['--limit' => '1']);
        self::assertStringNotContainsString('bravo', $tester->getDisplay());

        $tester->execute(['--after' => 'acme-guild']);
        self::assertStringNotContainsString('acme-guild', $tester->getDisplay());
        self::assertStringContainsString('bravo', $tester->getDisplay());

        foreach ([['--status' => 'gone'], ['--limit' => '0'], ['--limit' => '1001'], ['--limit' => 'many']] as $input) {
            self::assertSame(Command::INVALID, $tester->execute($input));
            self::assertSame('The status is "active" or "archived", the limit a whole number from 1 to 1000.'.\PHP_EOL, $tester->getDisplay());
        }

        self::assertSame(Command::SUCCESS, $tester->execute(['--limit' => '1000']));
    }

    public function testShow(): void
    {
        $this->stage->teams->create($this->acme->id, Stage::member('ann'), 'Design');
        $this->stage->invitations->create($this->acme->id, Stage::member('ann'), ['member']);

        $expired = $this->stage->invitations->create($this->acme->id, Stage::member('ann'), ['member'], $this->stage->clock->now()->modify('+1 minute'));
        $this->stage->clock->modify('+2 minutes');
        $tester = new CommandTester(new ShowOrganizationCommand($this->stage->organizations, $this->stage->store, $this->stage->ownership, $this->stage->clock));

        self::assertSame(Command::SUCCESS, $tester->execute(['organization' => 'acme-guild']));
        $display = $tester->getDisplay();

        foreach ([
            'Identifier: '.$this->acme->id->value,
            'Slug:       acme-guild',
            'Name:       Acme Guild',
            'Status:     active',
            'Created:    '.$this->acme->createdAt->format(\DATE_ATOM),
            'Domain:     acme.org',
            'Members:    3',
            'Owners:     1',
            'Teams:      1',
            'Invitations usable: 1',
            '  owner ann',
            'Warning: 1 owners, 2 recommended, so that the organization is never left without one.',
        ] as $line) {
            self::assertStringContainsString($line, $display);
        }

        self::assertStringNotContainsString('owner bob', $display);
        self::assertNotNull($expired);

        $this->stage->members->changeRoles($this->acme->id, Stage::member('ann'), Stage::member('bob'), ['owner']);
        $this->stage->organizations->useDomain($this->acme->id, Stage::member('ann'), null);

        $tester->execute(['organization' => $this->acme->id->value]);
        self::assertStringNotContainsString('Warning', $tester->getDisplay());
        self::assertStringContainsString('Domain:     -', $tester->getDisplay());
        self::assertStringContainsString('  owner bob', $tester->getDisplay());

        self::assertSame(Command::FAILURE, $tester->execute(['organization' => 'ghost']));
        self::assertSame('No organization "ghost".'.\PHP_EOL, $tester->getDisplay());
    }

    public function testPurge(): void
    {
        $this->stage->invitations->create($this->acme->id, Stage::member('ann'), ['member'], $this->stage->clock->now()->modify('+1 day'));
        $this->stage->clock->modify('+2 days');

        $tester = new CommandTester(new PurgeInvitationsCommand($this->stage->invitations, 2));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame('0 invitations deleted.'.\PHP_EOL, $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->execute(['--retention' => '1']));
        self::assertSame('1 invitations deleted.'.\PHP_EOL, $tester->getDisplay());

        foreach (['-1', '36501', 'soon'] as $retention) {
            self::assertSame(Command::INVALID, $tester->execute(['--retention' => $retention]));
            self::assertSame('The retention is a whole number of days, from 0 to 36500.'.\PHP_EOL, $tester->getDisplay());
        }

        self::assertSame(Command::SUCCESS, $tester->execute(['--retention' => '36500']));
        self::assertSame(Command::SUCCESS, $tester->execute(['--retention' => '0']));
    }

    public function testCheckOwners(): void
    {
        $tester = new CommandTester(new CheckOwnersCommand($this->stage->ownership));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame($this->acme->id->value.': 1 of 2 owners recommended'.\PHP_EOL, $tester->getDisplay());

        $this->stage->erasure->erase(Stage::member('ann'));
        self::assertSame(Command::FAILURE, $tester->execute(['--limit' => '100']));
        self::assertSame($this->acme->id->value.': 0 of 2 owners recommended'.\PHP_EOL, $tester->getDisplay());
        self::assertSame(Command::FAILURE, $tester->execute(['--limit' => '1000']));

        foreach (['0', '1001', 'all'] as $limit) {
            self::assertSame(Command::INVALID, $tester->execute(['--limit' => $limit]));
            self::assertSame('The limit is a whole number from 1 to 1000.'.\PHP_EOL, $tester->getDisplay());
        }
    }

    public function testCheckOwnersWhenEveryOrganizationMeetsTheRecommendation(): void
    {
        $tester = new CommandTester(new CheckOwnersCommand(new OwnershipCheck($this->stage->store, 1)));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame('Every organization has at least 1 owners.'.\PHP_EOL, $tester->getDisplay());

        $this->stage->erasure->erase(Stage::member('ann'));
        $this->stage->organizations->create('Other', Stage::member('bob'));
        self::assertSame(Command::FAILURE, $tester->execute(['--limit' => '1']));
        self::assertSame($this->acme->id->value.': 0 of 1 owners recommended'.\PHP_EOL, $tester->getDisplay());
    }

    public function testTheListHasItsHeadersAndTakesALimitOfOne(): void
    {
        $tester = new CommandTester(new ListOrganizationsCommand($this->stage->organizations));

        self::assertSame(Command::SUCCESS, $tester->execute(['--limit' => '1']));
        self::assertMatchesRegularExpression('/\|\s+Identifier\s+\|\s+Slug\s+\|\s+Name\s+\|\s+Status\s+\|\s+Domain\s+\|/', $tester->getDisplay());
    }

    public function testThePurgeKeepsWhatIsYoungerThanTheRetentionByASecond(): void
    {
        $this->stage->invitations->create($this->acme->id, Stage::member('ann'), ['member'], $this->stage->clock->now()->modify('+1 day +1 second'));
        $this->stage->clock->modify('+2 days');

        $tester = new CommandTester(new PurgeInvitationsCommand($this->stage->invitations, 2));

        $tester->execute(['--retention' => '1']);

        self::assertSame('0 invitations deleted.'.\PHP_EOL, $tester->getDisplay());
    }

    public function testShowCountsTheUsableInvitationsAndListsTheOwnersOfTheFirstHundredMembersOnly(): void
    {
        $this->stage->invitations->create($this->acme->id, Stage::member('ann'), ['member']);
        $this->stage->invitations->create($this->acme->id, Stage::member('ann'), ['member']);
        $this->stage->invitations->create($this->acme->id, Stage::member('ann'), ['member'], $this->stage->clock->now()->modify('+1 minute'));
        $this->stage->clock->modify('+2 minutes');

        for ($i = 1; $i <= 101; ++$i) {
            $this->stage->members->add($this->acme->id, Stage::member('ann'), Stage::member(\sprintf('m%03d', $i)), \in_array($i, [97, 98], true) ? ['owner'] : ['member']);
        }

        $tester = new CommandTester(new ShowOrganizationCommand($this->stage->organizations, $this->stage->store, $this->stage->ownership, $this->stage->clock));
        $tester->execute(['organization' => 'acme-guild']);

        $display = $tester->getDisplay();

        self::assertStringContainsString('Invitations usable: 2', $display);
        self::assertStringContainsString('  owner m097', $display);
        self::assertStringNotContainsString('  owner m098', $display);
    }
}
