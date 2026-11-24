<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Unit\Application;

use Phil\BounceParser\Auth\AuthenticationResults;
use Phil\BounceParser\BounceAction;
use Phil\BounceParser\BounceKind;
use Phil\BounceParser\BounceParser;
use Phil\BounceParser\BounceReport;
use Phil\BounceParser\Mime\Headers;
use Phil\BounceParser\Mime\Part;
use Phil\BounceParser\Recipient;
use Phil\BounceParser\ReportFormat;
use Phil\BounceParser\Verp;
use Phil\EmailAddressBundle\Application\Bounces\BounceNotices;
use Phil\EmailAddressBundle\Application\EmailAddresses;
use Phil\EmailAddressBundle\Domain\NoticeKind;
use Phil\EmailAddressBundle\Domain\NoticeSource;
use Phil\EmailAddressBundle\Domain\ValueObject\BounceNotice;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(BounceNotices::class)]
final class BounceNoticesTest extends TestCase
{
    private const string KEY = 'a signing key of sixteen bytes or more';

    public function testAHardBounceGivesAHardNoticePerFailedAddress(): void
    {
        $notices = $this->notices()->fromReport(new BounceParser()->parse($this->fixture('postfix-hard.eml')), 'raw:1', NoticeSource::Message);

        self::assertCount(1, $notices);
        self::assertSame(NoticeKind::Hard, $notices[0]->kind);
        self::assertSame(NoticeSource::Message, $notices[0]->source);
        self::assertSame('raw:1', $notices[0]->reportKey);
        self::assertSame('Ada@example.org', $notices[0]->address);
        self::assertEquals(new EmailAddresses('key')->fingerprint('ada@example.org'), $notices[0]->fingerprint);
        self::assertFalse($notices[0]->authenticated);
        self::assertSame('2026-11-20 10:00:00', $notices[0]->receivedAt->format('Y-m-d H:i:s'));
        self::assertNull($notices[0]->recipientId);
    }

    public function testADelayGivesASoftNoticeForTheFailedAddressOnly(): void
    {
        $notices = $this->notices()->fromReport(new BounceParser()->parse($this->fixture('delayed.eml')), 'raw:1', NoticeSource::Mailbox);

        self::assertSame([[NoticeKind::Soft, 'grace@example.com', NoticeSource::Mailbox]], array_map(static fn (BounceNotice $notice): array => [$notice->kind, $notice->address, $notice->source], $notices));
    }

    public function testAnAbuseReportIsAComplaintAuthenticatedOnlyWhenTheParserVouchedForIt(): void
    {
        $raw = $this->fixture('arf.eml');
        $vouched = $this->notices()->fromReport(new BounceParser(authenticator: new AuthenticationResults(['mail.example.net'], ['fbl.example.com']))->parse($raw), 'raw:1', NoticeSource::Message);
        $unvouched = $this->notices()->fromReport(new BounceParser()->parse($raw), 'raw:1', NoticeSource::Message);

        self::assertSame([NoticeKind::Complaint, true, 'grace@example.com'], [$vouched[0]->kind, $vouched[0]->authenticated, strtolower((string) $vouched[0]->address)]);
        self::assertSame([NoticeKind::Review, false], [$unvouched[0]->kind, $unvouched[0]->authenticated]);
    }

    public function testAnAutomaticReplyGivesNoNotice(): void
    {
        self::assertSame([], $this->notices()->fromReport(new BounceParser()->parse($this->fixture('out-of-office.eml')), 'raw:1', NoticeSource::Message));
    }

    public function testAMessageThatNamesNoAddressGivesOneNoticeWithoutIt(): void
    {
        $notices = $this->notices()->fromReport($this->report(BounceAction::Review, []), 'raw:1', NoticeSource::Message);

        self::assertCount(1, $notices);
        self::assertSame([NoticeKind::Review, null, null], [$notices[0]->kind, $notices[0]->address, $notices[0]->fingerprint]);
    }

    #[TestWith([BounceAction::CountHard, NoticeKind::Hard])]
    #[TestWith([BounceAction::Suppress, NoticeKind::Hard])]
    #[TestWith([BounceAction::CountSoft, NoticeKind::Soft])]
    #[TestWith([BounceAction::Unsubscribe, NoticeKind::Complaint])]
    #[TestWith([BounceAction::Review, NoticeKind::Review])]
    public function testTheActionDecidesTheKind(BounceAction $action, NoticeKind $kind): void
    {
        $notices = $this->notices()->fromReport($this->report($action, ['ada@example.org', 'grace@example.org']), 'raw:1', NoticeSource::Message);

        self::assertSame([[$kind, 'ada@example.org'], [$kind, 'grace@example.org']], array_map(static fn (BounceNotice $notice): array => [$notice->kind, $notice->address], $notices));
    }

    public function testTheRecipientIsReadFromATagSignedWithTheKey(): void
    {
        $verp = new Verp('bounces', 'example.net');
        $tag = (string) $verp->decode($verp->encodeSigned('m-42', self::KEY));
        $report = $this->report(BounceAction::CountHard, ['ada@example.org'], $tag);

        self::assertSame('m-42', $this->notices($verp, self::KEY)->fromReport($report, 'raw:1', NoticeSource::Message)[0]->recipientId);
        self::assertNull($this->notices($verp, 'another key of sixteen bytes')->fromReport($report, 'raw:1', NoticeSource::Message)[0]->recipientId);
        self::assertNull($this->notices($verp, '')->fromReport($report, 'raw:1', NoticeSource::Message)[0]->recipientId);
        self::assertNull($this->notices(null, self::KEY)->fromReport($report, 'raw:1', NoticeSource::Message)[0]->recipientId);
        self::assertSame('m-42', $this->notices($verp, self::KEY)->fromReport($this->report(BounceAction::Review, [], $tag), 'raw:1', NoticeSource::Message)[0]->recipientId);
    }

    public function testAWebhookNoticeIsAuthenticated(): void
    {
        $notice = $this->notices()->fromWebhook(NoticeKind::Complaint, 'Ada@Example.org', 'evt-1', 'm-42');

        self::assertSame(NoticeKind::Complaint, $notice->kind);
        self::assertSame(NoticeSource::Webhook, $notice->source);
        self::assertSame('webhook:evt-1', $notice->reportKey);
        self::assertSame('Ada@Example.org', $notice->address);
        self::assertEquals(new EmailAddresses('key')->fingerprint('ada@example.org'), $notice->fingerprint);
        self::assertTrue($notice->authenticated);
        self::assertSame('m-42', $notice->recipientId);
        self::assertSame('2026-11-20 10:00:00', $notice->receivedAt->format('Y-m-d H:i:s'));
        self::assertNull($this->notices()->fromWebhook(NoticeKind::Hard, 'ada@example.org', 'evt-1')->recipientId);
    }

    private function notices(?Verp $verp = null, string $key = ''): BounceNotices
    {
        return new BounceNotices(new EmailAddresses('key'), $verp, $key, new MockClock('2026-11-20 10:00:00'));
    }

    /**
     * @param list<string> $addresses
     */
    private function report(BounceAction $action, array $addresses, ?string $tag = null): BounceReport
    {
        $recipients = array_map(static fn (string $address): Recipient => new Recipient($address, BounceKind::Hard), $addresses);

        return new BounceReport(ReportFormat::DeliveryStatus, BounceKind::Hard, $action, new Part(new Headers([]), 'text/plain', [], ''), $recipients, verpTag: $tag);
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2).'/Fixtures/'.$name);
    }
}
