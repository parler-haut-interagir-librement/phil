<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\Address\Address;
use Phil\CommerceSubscriptionBundle\Application\ConfiguredPhoneRequirement;
use Phil\CommerceSubscriptionBundle\Application\DeliveryAddresses;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Infrastructure\Console\PurgeDeliveryAddressesCommand;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\InMemoryDeliveryAddresses;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\InMemorySubscriptions;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\Offers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

#[CoversClass(PurgeDeliveryAddressesCommand::class)]
final class PurgeDeliveryAddressesCommandTest extends TestCase
{
    private InMemoryDeliveryAddresses $copies;

    private DeliveryAddresses $addresses;

    protected function setUp(): void
    {
        $subscriptions = new InMemorySubscriptions();
        $this->copies = new InMemoryDeliveryAddresses($subscriptions);
        $this->addresses = new DeliveryAddresses($subscriptions, $this->copies, new ConfiguredPhoneRequirement(), 30);
        $subscription = Subscription::start(Offers::team(), 'account-1', new DateTimeImmutable('2026-01-01'));
        $subscriptions->subscriptions[] = $subscription;
        $this->addresses->change($subscription, Address::of('CA', ['locality' => 'Montréal']), 'post');
        $this->addresses->freeze($subscription, '2026-0001', new DateTimeImmutable('2026-01-01'));
    }

    public function testItPurgesAtTheClocksTime(): void
    {
        $tester = $this->tester(new PurgeDeliveryAddressesCommand($this->addresses, new LockFactory(new InMemoryStore()), new MockClock('2026-03-01')));

        self::assertSame(Command::SUCCESS, $tester->execute(['--limit' => '7']));
        self::assertStringContainsString('0 addresses of subscriptions and 1 copies in orders erased, kept 30 days after their last shipment.', $this->display($tester));
        self::assertEquals([[new DateTimeImmutable('2026-01-30'), 7]], $this->copies->purges);
    }

    public function testItPurgesNowWithoutClockNorLock(): void
    {
        $tester = $this->tester(new PurgeDeliveryAddressesCommand($this->addresses));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        [[$cutoff, $limit]] = $this->copies->purges;
        self::assertSame(500, $limit);
        self::assertEqualsWithDelta(new DateTimeImmutable('-30 days')->getTimestamp(), $cutoff->getTimestamp(), 5);
    }

    public function testAPurgeRunningElsewhereIsLeftToFinish(): void
    {
        $store = new InMemoryStore();
        $held = new LockFactory($store)->createLock(PurgeDeliveryAddressesCommand::LOCK);
        $held->acquire();

        $tester = $this->tester(new PurgeDeliveryAddressesCommand($this->addresses, new LockFactory($store)));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('A purge of the delivery addresses is already running.', $this->display($tester));
        self::assertSame([], $this->copies->purges);
    }

    public function testTheLockIsReleasedAfterThePurge(): void
    {
        $store = new InMemoryStore();
        $tester = $this->tester(new PurgeDeliveryAddressesCommand($this->addresses, new LockFactory($store), new MockClock('2026-03-01')));
        $tester->execute([]);

        self::assertTrue(new LockFactory($store)->createLock(PurgeDeliveryAddressesCommand::LOCK)->acquire());
    }

    public function testTheLimitIsBounded(): void
    {
        foreach (['0', '10001'] as $limit) {
            $tester = $this->tester(new PurgeDeliveryAddressesCommand($this->addresses));

            self::assertSame(Command::INVALID, $tester->execute(['--limit' => $limit]));
            self::assertStringContainsString('The limit is 1 to 10000.', $this->display($tester));
        }

        self::assertSame([], $this->copies->purges);
        self::assertSame(Command::SUCCESS, $this->tester(new PurgeDeliveryAddressesCommand($this->addresses))->execute(['--limit' => '1']));
        self::assertSame(Command::SUCCESS, $this->tester(new PurgeDeliveryAddressesCommand($this->addresses))->execute(['--limit' => '10000']));
    }

    private function tester(PurgeDeliveryAddressesCommand $command): CommandTester
    {
        return new CommandTester(new Command(null, $command));
    }

    private function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }
}
