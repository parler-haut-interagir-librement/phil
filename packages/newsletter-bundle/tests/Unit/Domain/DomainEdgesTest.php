<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\NewsletterBundle\Domain\Enum\ClickTracking;
use Phil\NewsletterBundle\Domain\Enum\SubscriberStatus;
use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;
use Phil\NewsletterBundle\Domain\Model\Campaign;
use Phil\NewsletterBundle\Domain\Model\Delivery;
use Phil\NewsletterBundle\Domain\Model\MailingList;
use Phil\NewsletterBundle\Domain\Model\Subscriber;
use Phil\NewsletterBundle\Domain\Model\Template;
use Phil\NewsletterBundle\Domain\ValueObject\SendWindow;
use Phil\NewsletterBundle\Domain\ValueObject\Site;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MailingList::class)]
#[CoversClass(Template::class)]
#[CoversClass(Site::class)]
#[CoversClass(Delivery::class)]
#[CoversClass(Campaign::class)]
#[CoversClass(Subscriber::class)]
#[CoversClass(SendWindow::class)]
final class DomainEdgesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function strays(): iterable
    {
        yield 'a suffix' => ['ab!'];
        yield 'a trailing new line' => ["ab\n"];
        yield 'a prefix' => ['!ab'];
    }

    #[DataProvider('strays')]
    public function testAKeyIsAnchoredAtBothEnds(string $key): void
    {
        $at = new DateTimeImmutable('@1800000000');

        self::assertSame('ab', Site::of('ab')->key);
        self::assertSame('ab', new MailingList('l', Site::default(), 'ab', 'Name', false, 'default', $at)->key);
        self::assertSame('ab', new Template('ab', null, 'Subject', 'Source', $at)->key);

        $refused = 0;

        foreach ([
            static fn (): Site => Site::of($key),
            static fn (): MailingList => new MailingList('l', Site::default(), $key, 'Name', false, 'default', $at),
            static fn (): Template => new Template($key, null, 'Subject', 'Source', $at),
        ] as $build) {
            try {
                $build();
                self::fail('A key with something around it is refused.');
            } catch (InvalidNewsletterValue) {
                ++$refused;
            }
        }

        self::assertSame(3, $refused);
    }

    public function testTheNameOfAListIsCountedInCharacters(): void
    {
        $at = new DateTimeImmutable('@1800000000');

        self::assertSame(str_repeat('é', 120), new MailingList('l', Site::default(), 'ab', str_repeat('é', 120), false, 'default', $at)->name);

        $this->expectException(InvalidNewsletterValue::class);
        new MailingList('l', Site::default(), 'ab', str_repeat('é', 121), false, 'default', $at);
    }

    public function testTheSubjectOfATemplateIsCountedInCharacters(): void
    {
        $at = new DateTimeImmutable('@1800000000');

        self::assertSame(str_repeat('é', 200), new Template('ab', null, str_repeat('é', 200), 'Source', $at)->subject);

        $this->expectException(InvalidNewsletterValue::class);
        new Template('ab', null, str_repeat('é', 201), 'Source', $at);
    }

    public function testTheIdOfAMessageIsDrawnFromItsParts(): void
    {
        self::assertSame(substr(hash('sha256', "campaign\0c1\0s1"), 0, 32), Delivery::idFor('c1', 's1'));
        self::assertSame(substr(hash('sha256', "single\0key-1"), 0, 32), Delivery::idForKey('key-1'));
        self::assertNotSame(Delivery::idFor('c1', 's1'), Delivery::idFor('c1s', '1'));
        self::assertNotSame(Delivery::idForKey('ab'), Delivery::idForKey('a'));
    }

    public function testADraftIsStartedAndItsSubjectIsCheckedWhenItIsEdited(): void
    {
        $campaign = new Campaign('c', Site::default(), 'l', 'Hello', 'Body');
        $campaign->start(false);

        self::assertSame('sending', $campaign->status->value);

        $draft = new Campaign('c', Site::default(), 'l', 'Hello', 'Body');

        $this->expectException(InvalidNewsletterValue::class);
        $draft->edit(' ', 'Body', '', null, null, null, ClickTracking::Off);
    }

    public function testAnAddressIsKeyedInLowercaseWhateverItsLetters(): void
    {
        self::assertSame('école@example.com', Subscriber::keyOf('ÉCOLE@Example.COM'));
    }

    public function testASubscriberWhoLeftIsNotReachableWhateverHisPause(): void
    {
        $at = new DateTimeImmutable('@1800000000');
        $subscriber = Subscriber::restore('s', Site::default(), 'ada@example.com', null, SubscriberStatus::Unsubscribed, $at, null, $at->modify('-1 day'), 0);

        self::assertFalse($subscriber->canReceive($at));
    }

    public function testEveryDayAndHourIsTheWindowOfAlways(): void
    {
        $always = SendWindow::always();

        self::assertSame([1, 2, 3, 4, 5, 6, 7], $always->days);
        self::assertSame(0, $always->from);
        self::assertSame(1440, $always->until);
        self::assertSame('UTC', $always->timezone->getName());
        self::assertSame(1440, SendWindow::of([1], '00:00', '24:00')->until);
    }

    #[DataProvider('hours')]
    public function testAnHourIsAnchoredAtBothEnds(string $hour): void
    {
        self::assertSame(570, SendWindow::of([1], '09:30', '10:00')->from);

        $this->expectException(InvalidNewsletterValue::class);
        SendWindow::of([1], $hour, '23:59');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function hours(): iterable
    {
        yield 'a suffix' => ['09:30!'];
        yield 'a trailing new line' => ["09:30\n"];
        yield 'a prefix' => ['!09:30'];
    }

    public function testTheNextOpeningMayBeAWeekAway(): void
    {
        $window = SendWindow::of([1], '09:00', '17:00');

        self::assertSame('2026-11-23 09:00', $window->next(new DateTimeImmutable('2026-11-16 18:00:00 UTC'))->format('Y-m-d H:i'));
    }

    public function testTheNextOpeningKeepsItsLateHour(): void
    {
        $window = SendWindow::of([1, 2, 3, 4, 5, 6, 7], '23:45', '24:00');

        self::assertSame('2026-11-13 23:45', $window->next(new DateTimeImmutable('2026-11-13 10:00:00 UTC'))->format('Y-m-d H:i'));
    }
}
