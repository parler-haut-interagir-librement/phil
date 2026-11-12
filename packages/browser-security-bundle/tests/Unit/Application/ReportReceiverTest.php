<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\BrowserSecurityBundle\Application\Event\ViolationReported;
use Phil\BrowserSecurityBundle\Application\Receipt;
use Phil\BrowserSecurityBundle\Application\ReportParser;
use Phil\BrowserSecurityBundle\Application\ReportReceiver;
use Phil\BrowserSecurityBundle\Domain\ValueObject\ViolationReport;
use Phil\BrowserSecurityBundle\Tests\Unit\Fixture\ListedThrottle;
use Phil\BrowserSecurityBundle\Tests\Unit\Fixture\MemoryReports;
use Phil\BrowserSecurityBundle\Tests\Unit\Fixture\RecordingDispatcher;
use Phil\BrowserSecurityBundle\Tests\Unit\Fixture\StrictReports;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(ReportReceiver::class)]
#[CoversClass(ViolationReported::class)]
#[CoversClass(Receipt::class)]
final class ReportReceiverTest extends TestCase
{
    private const string TYPE = 'application/reports+json';

    private MockClock $clock;

    private MemoryReports $reports;

    private RecordingDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-08 12:00:00');
        $this->reports = new MemoryReports();
        $this->dispatcher = new RecordingDispatcher();
    }

    public function testEachReportIsStoredAndAnnouncedOncePerBody(): void
    {
        $throttle = new ListedThrottle();
        $receiver = $this->receiver($throttle);
        $body = json_encode([
            ['type' => 'csp-violation', 'body' => ['documentURL' => 'https://example.com/a', 'blockedURL' => 'inline', 'effectiveDirective' => 'script-src-elem', 'sample' => 'first']],
            ['type' => 'csp-violation', 'body' => ['documentURL' => 'https://example.com/a', 'blockedURL' => 'inline', 'effectiveDirective' => 'script-src-elem', 'sample' => 'second']],
            ['type' => 'csp-violation', 'body' => ['documentURL' => 'https://example.com/b', 'blockedURL' => 'inline', 'effectiveDirective' => 'script-src-elem']],
        ], \JSON_THROW_ON_ERROR);

        self::assertSame(Receipt::Accepted, $receiver->receive(self::TYPE, $body, '192.0.2.10'));

        $a = new ViolationReport('https://example.com/a', 'inline', 'script-src-elem', sample: 'first');
        $b = new ViolationReport('https://example.com/b', 'inline', 'script-src-elem');
        StrictReports::assertSame([$a, $b], array_map(static fn (array $recorded): ViolationReport => $recorded[0], $this->reports->recorded));
        self::assertSame(['anon:192.0.2.10', 'anon:192.0.2.10'], array_column($this->reports->recorded, 1));
        self::assertEquals([new DateTimeImmutable('2026-11-08 12:00:00'), new DateTimeImmutable('2026-11-08 12:00:00')], array_column($this->reports->recorded, 2));
        self::assertEquals([new ViolationReported($a, 'anon:192.0.2.10'), new ViolationReported($b, 'anon:192.0.2.10')], $this->dispatcher->events);
        self::assertSame([$a->fingerprint(), $b->fingerprint()], $throttle->fingerprints);
        self::assertSame([hash_hmac('sha256', '192.0.2.10', 'secret')], $throttle->clients);
    }

    public function testAViolationAnnouncedWithinTheWindowIsStoredOnly(): void
    {
        $known = new ViolationReport('https://example.com/a', 'inline', 'script-src-elem');
        $receiver = $this->receiver(new ListedThrottle([], [$known->fingerprint()]));

        self::assertSame(Receipt::Accepted, $receiver->receive(self::TYPE, $this->body(['https://example.com/a', 'https://example.com/b']), null));
        self::assertCount(2, $this->reports->recorded);
        self::assertNull($this->reports->recorded[0][1]);
        self::assertCount(1, $this->dispatcher->events);
    }

    public function testWithoutCollaboratorsEveryReportIsAnnounced(): void
    {
        $receiver = new ReportReceiver(new ReportParser(), $this->clock, 'secret', dispatcher: $this->dispatcher);

        self::assertSame(Receipt::Accepted, $receiver->receive(self::TYPE, $this->body(['https://example.com/a']), '192.0.2.10'));
        self::assertEquals([new ViolationReported(new ViolationReport('https://example.com/a', 'inline', 'script-src-elem'))], $this->dispatcher->events);
        self::assertSame(Receipt::Accepted, new ReportReceiver(new ReportParser(), $this->clock, 'secret')->receive(self::TYPE, $this->body(['https://example.com/a']), null));
    }

    public function testAnUnsupportedMediaType(): void
    {
        self::assertSame(Receipt::UnsupportedType, $this->receiver(new ListedThrottle())->receive('text/plain', $this->body(['https://example.com/a']), null));
        self::assertSame([], $this->reports->recorded);
    }

    public function testABodyTooLarge(): void
    {
        $throttle = new ListedThrottle();
        $body = $this->body(['https://example.com/a']);
        $receiver = new ReportReceiver(new ReportParser(), $this->clock, 'secret', \strlen($body) - 1, $throttle, repository: $this->reports);

        self::assertSame(Receipt::TooLarge, $receiver->receive(self::TYPE, $body, null));
        self::assertSame([], $throttle->clients);
        self::assertSame(\strlen($body) - 1, $receiver->maxBytes());
        self::assertSame(Receipt::Accepted, new ReportReceiver(new ReportParser(), $this->clock, 'secret', \strlen($body))->receive(self::TYPE, $body, null));
        self::assertSame(ReportReceiver::DEFAULT_MAX_BYTES, new ReportReceiver(new ReportParser(), $this->clock, 'secret')->maxBytes());
    }

    public function testAThrottledClient(): void
    {
        $receiver = $this->receiver(new ListedThrottle([hash_hmac('sha256', '', 'secret')]));

        self::assertSame(Receipt::TooManyRequests, $receiver->receive(self::TYPE, $this->body(['https://example.com/a']), null));
        self::assertSame([], $this->reports->recorded);
    }

    public function testAMalformedBody(): void
    {
        self::assertSame(Receipt::Malformed, $this->receiver(new ListedThrottle())->receive(self::TYPE, '{', null));
    }

    public function testTheStatusOfEachReceipt(): void
    {
        self::assertSame([204, 400, 413, 415, 429], array_map(static fn (Receipt $receipt): int => $receipt->value, Receipt::cases()));
    }

    private function receiver(ListedThrottle $throttle): ReportReceiver
    {
        return new ReportReceiver(new ReportParser(), $this->clock, 'secret', ReportReceiver::DEFAULT_MAX_BYTES, $throttle, $throttle, $throttle, $this->reports, $this->dispatcher);
    }

    /**
     * @param list<string> $documents
     */
    private function body(array $documents): string
    {
        return json_encode(array_map(static fn (string $document): array => ['type' => 'csp-violation', 'body' => ['documentURL' => $document, 'blockedURL' => 'inline', 'effectiveDirective' => 'script-src-elem']], $documents), \JSON_THROW_ON_ERROR);
    }
}
