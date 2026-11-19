<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Tests\Unit;

use DateTimeImmutable;
use Phil\GuardianConsent\Actor;
use Phil\GuardianConsent\CalendarDate;
use Phil\GuardianConsent\ConsentProof;
use Phil\GuardianConsent\ConsentRequest;
use Phil\GuardianConsent\Decision;
use Phil\GuardianConsent\Exception\InvalidConsent;
use Phil\GuardianConsent\Feature;
use Phil\GuardianConsent\Guardian;
use Phil\GuardianConsent\GuardianId;
use Phil\GuardianConsent\MemberId;
use Phil\GuardianConsent\Minor;
use Phil\GuardianConsent\NoticeVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsentRequest::class)]
#[CoversClass(ConsentProof::class)]
#[CoversClass(Minor::class)]
final class RecordsTest extends TestCase
{
    private const string REQUEST = 'AAAAAAAAAAAAAAAAAAAAAA';

    private const string PROOF = 'QQQQQQQQQQQQQQQQQQQQQQ';

    public function testARequestKeepsItsInstantsInUtc(): void
    {
        $request = $this->request([new Feature('messages'), new Feature('public-profile')], '2026-11-14 10:00:00-05:00', '2026-11-21 10:00:00-05:00');

        self::assertSame('2026-11-14 15:00:00 +00:00', $request->requestedAt->format('Y-m-d H:i:s P'));
        self::assertSame('2026-11-21 15:00:00 +00:00', $request->expiresAt->format('Y-m-d H:i:s P'));
        self::assertSame(['messages', 'public-profile'], array_map(static fn (Feature $feature): string => $feature->name, $request->features));
        self::assertTrue($request->covers(new Feature('public-profile')));
        self::assertTrue($request->covers(new Feature('messages')));
        self::assertFalse($request->covers(new Feature('classifieds')));
        self::assertFalse($request->isExpired(new DateTimeImmutable('2026-11-21 14:59:59+00:00')));
        self::assertTrue($request->isExpired(new DateTimeImmutable('2026-11-21 15:00:00+00:00')));
        self::assertSame('hash', $request->tokenHash);
    }

    public function testARequestIsNamedByAnIdentifierOfTheLibrary(): void
    {
        $this->expectException(InvalidConsent::class);
        $this->expectExceptionMessageIsOrContains('A request is named by an identifier the library issues.');

        new ConsentRequest('request-1', new MemberId('m-1'), new Guardian(new GuardianId('g-1'), 'p@example.org'), [new Feature('messages')], new NoticeVersion('v1'), new DateTimeImmutable('2026-11-14'), new DateTimeImmutable('2026-11-15'), 'hash');
    }

    public function testARequestAsksAtLeastOneFeature(): void
    {
        $this->expectException(InvalidConsent::class);
        $this->expectExceptionMessageIsOrContains('A request asks 1 to 64 features.');

        $this->request([]);
    }

    public function testARequestAsksAtMost64Features(): void
    {
        $features = array_map(static fn (int $index): Feature => new Feature('f'.$index), range(1, 64));
        self::assertCount(64, $this->request($features)->features);

        $this->expectException(InvalidConsent::class);
        $this->expectExceptionMessageIsOrContains('A request asks 1 to 64 features.');

        $this->request([...$features, new Feature('f65')]);
    }

    public function testARequestAsksAFeatureOnce(): void
    {
        $this->expectException(InvalidConsent::class);
        $this->expectExceptionMessageIsOrContains('A feature is asked once.');

        $this->request([new Feature('messages'), new Feature('messages')]);
    }

    public function testARequestExpiresAfterItIsSent(): void
    {
        $this->expectException(InvalidConsent::class);
        $this->expectExceptionMessageIsOrContains('A request expires after it is sent.');

        $this->request([new Feature('messages')], '2026-11-14 10:00:00', '2026-11-14 10:00:00');
    }

    public function testAProof(): void
    {
        $proof = new ConsentProof(self::PROOF, new MemberId('m-1'), new Feature('messages'), Decision::Granted, Actor::Guardian, new GuardianId('g-1'), new NoticeVersion('v1'), self::REQUEST, new DateTimeImmutable('2026-11-14 10:00:00-05:00'));

        self::assertSame('2026-11-14 15:00:00 +00:00', $proof->recordedAt->format('Y-m-d H:i:s P'));
        self::assertSame('g-1', $proof->guardian?->value);

        $forgotten = $proof->withoutGuardian();
        self::assertNull($forgotten->guardian);
        self::assertSame(self::PROOF, $forgotten->id);
        self::assertSame('m-1', $forgotten->member->value);
        self::assertSame('messages', $forgotten->feature->name);
        self::assertSame(Decision::Granted, $forgotten->decision);
        self::assertSame(Actor::Guardian, $forgotten->actor);
        self::assertSame('v1', $forgotten->notice?->value);
        self::assertSame(self::REQUEST, $forgotten->requestId);
        self::assertSame('2026-11-14 15:00:00', $forgotten->recordedAt->format('Y-m-d H:i:s'));

        $own = new ConsentProof(self::PROOF, new MemberId('m-1'), new Feature('messages'), Decision::Revoked, Actor::Member, null, null, null, new DateTimeImmutable('2026-11-14'));
        self::assertNull($own->requestId);
    }

    public function testAProofIsNamedByAnIdentifierOfTheLibrary(): void
    {
        $this->expectException(InvalidConsent::class);
        $this->expectExceptionMessageIsOrContains('A proof is named by an identifier the library issues.');

        new ConsentProof('proof-1', new MemberId('m-1'), new Feature('messages'), Decision::Granted, Actor::Member, null, null, null, new DateTimeImmutable());
    }

    public function testAMembersDecisionNamesNoGuardian(): void
    {
        $this->expectException(InvalidConsent::class);
        $this->expectExceptionMessageIsOrContains('A decision of the member names no guardian.');

        new ConsentProof(self::PROOF, new MemberId('m-1'), new Feature('messages'), Decision::Granted, Actor::Member, new GuardianId('g-1'), null, null, new DateTimeImmutable());
    }

    public function testAProofNamesARequestOfTheLibrary(): void
    {
        $this->expectException(InvalidConsent::class);
        $this->expectExceptionMessageIsOrContains('A proof names a request by an identifier the library issues.');

        new ConsentProof(self::PROOF, new MemberId('m-1'), new Feature('messages'), Decision::Granted, Actor::Guardian, new GuardianId('g-1'), null, 'request-1', new DateTimeImmutable());
    }

    public function testAMinor(): void
    {
        $minor = new Minor(new MemberId('m-1'), 'CA-QC', CalendarDate::fromString('2026-11-14'), new DateTimeImmutable('2026-01-01 10:00:00-05:00'));

        self::assertSame('2026-01-01 15:00:00 +00:00', $minor->registeredAt->format('Y-m-d H:i:s P'));
        self::assertNull($minor->handoverNoticedAt);
        self::assertNull($minor->handedOverAt);
        self::assertFalse($minor->isHandedOver());
        self::assertFalse($minor->hasReachedConsentAge(CalendarDate::fromString('2026-11-13')));
        self::assertTrue($minor->hasReachedConsentAge(CalendarDate::fromString('2026-11-14')));
        self::assertTrue($minor->hasReachedConsentAge(CalendarDate::fromString('2026-11-15')));

        $noticed = $minor->withHandoverNoticed(new DateTimeImmutable('2026-11-14 01:00:00-05:00'));
        self::assertSame('2026-11-14 06:00:00 +00:00', $noticed->handoverNoticedAt?->format('Y-m-d H:i:s P'));
        self::assertNull($noticed->handedOverAt);
        self::assertSame('CA-QC', $noticed->jurisdiction);
        self::assertSame('2026-11-14', $noticed->consentAgeOn->toString());
        self::assertSame('2026-01-01 15:00:00', $noticed->registeredAt->format('Y-m-d H:i:s'));
        self::assertSame('m-1', $noticed->member->value);

        $handedOver = $noticed->withHandedOver(new DateTimeImmutable('2026-11-15 01:00:00-05:00'));
        self::assertTrue($handedOver->isHandedOver());
        self::assertSame('2026-11-15 06:00:00 +00:00', $handedOver->handedOverAt?->format('Y-m-d H:i:s P'));
        self::assertSame('2026-11-14 06:00:00', $handedOver->handoverNoticedAt?->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-15 06:00:00', $minor->withHandedOver(new DateTimeImmutable('2026-11-15 06:00:00+00:00'))->handedOverAt?->format('Y-m-d H:i:s'));
        self::assertNull($minor->withHandedOver(new DateTimeImmutable())->handoverNoticedAt);
    }

    /**
     * @param list<Feature> $features
     */
    private function request(array $features, string $at = '2026-11-14 10:00:00', string $until = '2026-11-21 10:00:00'): ConsentRequest
    {
        return new ConsentRequest(self::REQUEST, new MemberId('m-1'), new Guardian(new GuardianId('g-1'), 'p@example.org'), $features, new NoticeVersion('v1'), new DateTimeImmutable($at), new DateTimeImmutable($until), 'hash');
    }
}
