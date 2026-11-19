<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Tests\Unit;

use Override;
use Phil\GuardianConsent\CalendarDate;
use Phil\GuardianConsent\Feature;
use Phil\GuardianConsent\Guardian;
use Phil\GuardianConsent\GuardianConsent;
use Phil\GuardianConsent\GuardianConsentRights;
use Phil\GuardianConsent\GuardianId;
use Phil\GuardianConsent\MemberId;
use Phil\GuardianConsent\Memory\InMemoryMinorStore;
use Phil\GuardianConsent\Memory\InMemoryProofStore;
use Phil\GuardianConsent\Memory\InMemoryRequestStore;
use Phil\GuardianConsent\NoticeVersion;
use Phil\GuardianConsent\Tests\Unit\Fixture\RacingRequestStore;
use Phil\GuardianConsent\Tests\Unit\Fixture\RecordingNotifier;
use Phil\GuardianConsent\TokenHasher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(GuardianConsentRights::class)]
final class GuardianConsentRightsTest extends TestCase
{
    private MockClock $clock;

    private InMemoryMinorStore $minors;

    private InMemoryRequestStore $requests;

    private InMemoryProofStore $proofs;

    private RecordingNotifier $notifier;

    private GuardianConsent $consent;

    private GuardianConsentRights $rights;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-14 12:00:00', 'America/Toronto');
        $this->minors = new InMemoryMinorStore();
        $this->requests = new InMemoryRequestStore();
        $this->proofs = new InMemoryProofStore();
        $this->notifier = new RecordingNotifier();
        $this->consent = new GuardianConsent($this->minors, $this->requests, $this->proofs, $this->notifier, new TokenHasher(str_repeat('k', 32)), $this->clock);
        $this->rights = new GuardianConsentRights($this->minors, $this->requests, $this->proofs);
    }

    public function testNothingIsKeptOnAStranger(): void
    {
        self::assertSame([], $this->rights->export('member-1'));
        self::assertSame(0, $this->rights->erase('member-1'));
        self::assertSame([], $this->rights->export('parent@example.org'));
        self::assertSame(0, $this->rights->erase('parent@example.org'));
    }

    public function testTheExportOfAMemberAndOfAGuardian(): void
    {
        $this->seed();

        $member = $this->rights->export('member-1');
        self::assertSame(['member'], array_keys($member));
        self::assertSame([
            'minor' => [
                'jurisdiction' => 'CA-QC',
                'consent_age_on' => '2026-11-20',
                'registered_at' => '2026-11-14T17:00:00+00:00',
                'handover_noticed_at' => null,
                'handed_over_at' => null,
            ],
            'requests' => [[
                'id' => $this->notifier->confirmations[1][0]->id,
                'member' => 'member-1',
                'guardian' => 'guardian-1',
                'features' => ['public-profile'],
                'notice' => 'v1',
                'requested_at' => '2026-11-14T17:00:00+00:00',
                'expires_at' => '2026-11-21T17:00:00+00:00',
            ]],
            'proofs' => [[
                'id' => $this->proofs->ofMember(new MemberId('member-1'))[0]->id,
                'member' => 'member-1',
                'feature' => 'messages',
                'decision' => 'granted',
                'actor' => 'guardian',
                'guardian' => 'guardian-1',
                'notice' => 'v1',
                'request' => $this->notifier->confirmations[0][0]->id,
                'recorded_at' => '2026-11-14T17:00:00+00:00',
            ]],
        ], $member['member']);

        $guardian = $this->rights->export('guardian-1');
        self::assertSame(['guardian'], array_keys($guardian));
        self::assertIsArray($guardian['guardian']);
        self::assertSame(['requests', 'proofs'], array_keys($guardian['guardian']));
        self::assertIsArray($guardian['guardian']['requests']);
        self::assertCount(1, $guardian['guardian']['requests']);
        self::assertIsArray($guardian['guardian']['requests'][0]);
        self::assertSame('parent@example.org', $guardian['guardian']['requests'][0]['guardian_email']);
        self::assertSame('member-1', $guardian['guardian']['requests'][0]['member']);
        self::assertIsArray($guardian['guardian']['proofs']);
        self::assertCount(1, $guardian['guardian']['proofs']);

        $this->clock->modify('2026-11-20 12:00:00');
        $this->consent->sweep();
        $this->consent->handOver(new MemberId('member-1'), [], new NoticeVersion('v2'));

        $handedOver = $this->rights->export('member-1')['member'];
        self::assertIsArray($handedOver);
        self::assertIsArray($handedOver['minor']);
        self::assertSame('2026-11-20T17:00:00+00:00', $handedOver['minor']['handover_noticed_at']);
        self::assertSame('2026-11-20T17:00:00+00:00', $handedOver['minor']['handed_over_at']);
        self::assertArrayNotHasKey('requests', $handedOver);
    }

    public function testTheErasureOfAMember(): void
    {
        $this->seed();

        // The record, one request, one proof.
        self::assertSame(3, $this->rights->erase('member-1'));
        self::assertSame(0, $this->rights->erase('member-1'));
        self::assertSame([], $this->rights->export('member-1'));
        self::assertNull($this->minors->get(new MemberId('member-1')));
        self::assertCount(1, $this->proofs->ofMember(new MemberId('member-2')));
    }

    public function testTheErasureOfAGuardian(): void
    {
        $this->seed();

        // One request with the address, one proof that no longer names them.
        self::assertSame(2, $this->rights->erase('guardian-1'));
        self::assertSame(0, $this->rights->erase('guardian-1'));
        self::assertSame([], $this->rights->export('guardian-1'));
        self::assertSame([], $this->requests->ofMember(new MemberId('member-1')));
        self::assertNull($this->proofs->ofMember(new MemberId('member-1'))[0]->guardian);
        self::assertNotNull($this->minors->get(new MemberId('member-1')));
    }

    public function testTheExportOfTheProofsOfAGuardianAndOfAHandover(): void
    {
        $this->seed();
        $this->consent->revoke(new MemberId('member-1'), new Feature('messages'), new GuardianId('guardian-1'));

        $proofs = $this->rights->export('guardian-1')['guardian'];
        self::assertIsArray($proofs);
        self::assertIsArray($proofs['proofs']);
        self::assertCount(2, $proofs['proofs']);
        self::assertIsArray($proofs['proofs'][1]);
        self::assertSame('revoked', $proofs['proofs'][1]['decision']);
        self::assertSame('guardian-1', $proofs['proofs'][1]['guardian']);
        self::assertNull($proofs['proofs'][1]['notice']);
        self::assertNull($proofs['proofs'][1]['request']);

        $this->clock->modify('2026-11-20 12:00:00');
        $this->consent->handOver(new MemberId('member-1'), [new Feature('messages')], new NoticeVersion('v2'));
        $member = $this->rights->export('member-1')['member'];
        self::assertIsArray($member);
        self::assertIsArray($member['proofs']);
        self::assertIsArray($member['proofs'][2]);
        self::assertNull($member['proofs'][2]['guardian']);
        self::assertSame('v2', $member['proofs'][2]['notice']);
        self::assertSame('member', $member['proofs'][2]['actor']);
    }

    public function testARequestUsedUpByAnotherProcessIsNotCountedAsErased(): void
    {
        $requests = new RacingRequestStore();
        $consent = new GuardianConsent($this->minors, $requests, $this->proofs, $this->notifier, new TokenHasher(str_repeat('k', 32)), $this->clock);
        $consent->register(new MemberId('member-1'), CalendarDate::fromString('2012-11-20'));
        $consent->request(new MemberId('member-1'), new Guardian(new GuardianId('guardian-1'), 'parent@example.org'), [new Feature('messages')], new NoticeVersion('v1'));

        $rights = new GuardianConsentRights($this->minors, $requests, $this->proofs);

        // Only the record of the minor: the request was gone when it was used.
        self::assertSame(1, $rights->erase('member-1'));
    }

    private function seed(): void
    {
        $parent = new Guardian(new GuardianId('guardian-1'), 'parent@example.org');
        $this->consent->register(new MemberId('member-1'), CalendarDate::fromString('2012-11-20'));
        $this->consent->request(new MemberId('member-1'), $parent, [new Feature('messages')], new NoticeVersion('v1'));
        $this->consent->decide($this->notifier->lastToken(), [new Feature('messages')]);
        $this->consent->request(new MemberId('member-1'), $parent, [new Feature('public-profile')], new NoticeVersion('v1'));

        $this->consent->register(new MemberId('member-2'), CalendarDate::fromString('2013-01-01'));
        $this->consent->request(new MemberId('member-2'), new Guardian(new GuardianId('guardian-2'), 'other@example.org'), [new Feature('messages')], new NoticeVersion('v1'));
        $this->consent->decide($this->notifier->lastToken(), []);
    }
}
