<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\EmailTracking;

use DateTimeImmutable;
use Override;
use Phil\PrivacyBundle\Application\Contract\EmailTrackingStore;
use Phil\PrivacyBundle\Application\EmailTracking\EmailTrackingConsents;
use Phil\PrivacyBundle\Domain\EmailTracking\EmailTrackingChoice;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(EmailTrackingConsents::class)]
final class EmailTrackingConsentsTest extends TestCase
{
    private EmailTrackingStore $store;

    private MockClock $clock;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-14 12:00:00 UTC');
        $this->store = new class implements EmailTrackingStore {
            /** @var array<string, EmailTrackingChoice> */
            public array $choices = [];

            #[Override]
            public function find(DataSubject $subject): ?EmailTrackingChoice
            {
                return $this->choices[$subject->value] ?? null;
            }

            #[Override]
            public function save(EmailTrackingChoice $choice): void
            {
                $this->choices[$choice->subject->value] = $choice;
            }

            #[Override]
            public function forget(DataSubject $subject): void
            {
                unset($this->choices[$subject->value]);
            }

            #[Override]
            public function store(): string
            {
                return 'memory';
            }
        };
    }

    public function testNothingIsTrackedWithoutAChoice(): void
    {
        $consents = $this->consents();

        self::assertFalse($consents->opens('member-1'));
        self::assertFalse($consents->clicks('member-1'));
        self::assertNull($consents->current(DataSubject::fromString('member-1')));
    }

    public function testARecipientNoMemberCanBeAnswersNo(): void
    {
        $consents = $this->consents();

        foreach (['', "member-1\n", str_repeat('a', 256)] as $recipient) {
            self::assertFalse($consents->opens($recipient));
            self::assertFalse($consents->clicks($recipient));
        }
    }

    public function testAChoiceIsDatedAndAnsweredPurposeByPurpose(): void
    {
        $consents = $this->consents();
        $choice = $consents->decide(DataSubject::fromString('member-1'), true, false);

        self::assertEquals(new DateTimeImmutable('2026-11-14 12:00:00 UTC'), $choice->decidedAt);
        self::assertSame('2', $choice->version);
        self::assertTrue($consents->opens('member-1'));
        self::assertFalse($consents->clicks('member-1'));
        self::assertFalse($consents->opens('member-2'));
        self::assertSame($choice, $consents->current(DataSubject::fromString('member-1')));

        $consents->decide(DataSubject::fromString('member-1'), false, true);

        self::assertFalse($consents->opens('member-1'));
        self::assertTrue($consents->clicks('member-1'));
    }

    public function testANewTextStopsTheEarlierChoiceUntilTheMemberChoosesAgain(): void
    {
        $this->consents()->decide(DataSubject::fromString('member-1'), true, true);
        $next = new EmailTrackingConsents($this->store, $this->clock, '3');

        self::assertFalse($next->opens('member-1'));
        self::assertFalse($next->clicks('member-1'));
        self::assertNull($next->current(DataSubject::fromString('member-1')));
        self::assertSame('3', $next->version());
    }

    public function testTheChoiceIsExportedAndErased(): void
    {
        $consents = $this->consents();
        $subject = DataSubject::fromString('member-1');

        self::assertSame('email_tracking', $consents->module()->value);
        self::assertSame([], $consents->export($subject));

        $consents->decide($subject, false, true);
        self::assertSame(['version' => '2', 'opens' => false, 'clicks' => true, 'decided_at' => '2026-11-14T12:00:00+00:00'], $consents->export($subject));

        $report = $consents->erase($subject);
        self::assertSame('email_tracking', $report->module->value);
        self::assertSame(['memory' => ErasureEffect::Deleted], $report->effects);
        self::assertSame([], $consents->export($subject));
        self::assertSame(['memory' => ErasureEffect::Deleted], $consents->erase($subject)->effects, 'An erasure can be replayed.');
    }

    public function testAVersionMustBeOneOfATextOfTheSite(): void
    {
        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains('This is not a version of the text about tracked emails.');

        new EmailTrackingConsents($this->store, $this->clock, 'version 2');
    }

    private function consents(): EmailTrackingConsents
    {
        return new EmailTrackingConsents($this->store, $this->clock, '2');
    }
}
