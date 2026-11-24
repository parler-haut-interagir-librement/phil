<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\EmailAddressBundle\Domain\NoticeKind;
use Phil\EmailAddressBundle\Domain\NoticeSource;
use Phil\EmailAddressBundle\Domain\ValueObject\AddressFingerprint;
use Phil\EmailAddressBundle\Domain\ValueObject\BounceNotice;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BounceNotice::class)]
final class BounceNoticeTest extends TestCase
{
    public function testTheKeyNamesTheReportTheAddressAndTheRecipient(): void
    {
        $fingerprint = AddressFingerprint::of('ada@example.org', 'key');
        $notice = $this->notice('report', $fingerprint, 'm-42');

        self::assertSame(hash('sha256', "report\n".$fingerprint->value."\nm-42"), $notice->key());
        self::assertSame(hash('sha256', "report\n\n"), $this->notice('report', null, null)->key());
        self::assertNotSame($notice->key(), $this->notice('report', AddressFingerprint::of('grace@example.org', 'key'), 'm-42')->key());
        self::assertNotSame($notice->key(), $this->notice('other', $fingerprint, 'm-42')->key());
    }

    public function testTheSubjectIsTheFingerprintElseTheRecipient(): void
    {
        $fingerprint = AddressFingerprint::of('ada@example.org', 'key');

        self::assertSame($fingerprint->value, $this->notice('report', $fingerprint, 'm-42')->subject());
        self::assertSame('recipient:'.hash('sha256', 'm-42'), $this->notice('report', null, 'm-42')->subject());
        self::assertNull($this->notice('report', null, null)->subject());
    }

    private function notice(string $report, ?AddressFingerprint $fingerprint, ?string $recipientId): BounceNotice
    {
        return new BounceNotice(NoticeKind::Hard, NoticeSource::Message, $report, null, $fingerprint, false, new DateTimeImmutable('2026-11-20 10:00'), $recipientId);
    }
}
