<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\GuardianConsent\Actor;
use Phil\GuardianConsent\AgeBracket;
use Phil\GuardianConsent\CalendarDate;
use Phil\GuardianConsent\ConfirmationToken;
use Phil\GuardianConsent\ConsentProof;
use Phil\GuardianConsent\ConsentRequest;
use Phil\GuardianConsent\ConsentStatus;
use Phil\GuardianConsent\Decision;
use Phil\GuardianConsent\Event\ConsentDecided;
use Phil\GuardianConsent\Event\ConsentRequested;
use Phil\GuardianConsent\Event\ConsentRevoked;
use Phil\GuardianConsent\Event\HandoverCompleted;
use Phil\GuardianConsent\Event\HandoverDue;
use Phil\GuardianConsent\Event\RequestErased;
use Phil\GuardianConsent\Exception\ConsentNotRequired;
use Phil\GuardianConsent\Exception\ExpiredRequest;
use Phil\GuardianConsent\Exception\InvalidConfiguration;
use Phil\GuardianConsent\Exception\InvalidConsent;
use Phil\GuardianConsent\Exception\InvalidToken;
use Phil\GuardianConsent\Exception\NotAGuardian;
use Phil\GuardianConsent\Feature;
use Phil\GuardianConsent\Guardian;
use Phil\GuardianConsent\GuardianConsent;
use Phil\GuardianConsent\GuardianId;
use Phil\GuardianConsent\Jurisdiction;
use Phil\GuardianConsent\Jurisdictions;
use Phil\GuardianConsent\MemberId;
use Phil\GuardianConsent\Memory\InMemoryMinorStore;
use Phil\GuardianConsent\Memory\InMemoryProofStore;
use Phil\GuardianConsent\Memory\InMemoryRequestStore;
use Phil\GuardianConsent\Minor;
use Phil\GuardianConsent\NoGuardianLink;
use Phil\GuardianConsent\NoticeVersion;
use Phil\GuardianConsent\SweepReport;
use Phil\GuardianConsent\Tests\Unit\Fixture\FixedGuardianLink;
use Phil\GuardianConsent\Tests\Unit\Fixture\RacingRequestStore;
use Phil\GuardianConsent\Tests\Unit\Fixture\RecordingEvents;
use Phil\GuardianConsent\Tests\Unit\Fixture\RecordingNotifier;
use Phil\GuardianConsent\TokenHasher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(GuardianConsent::class)]
#[CoversClass(SweepReport::class)]
#[CoversClass(NoGuardianLink::class)]
#[CoversClass(ConsentRequested::class)]
#[CoversClass(ConsentDecided::class)]
#[CoversClass(ConsentRevoked::class)]
#[CoversClass(RequestErased::class)]
#[CoversClass(HandoverDue::class)]
#[CoversClass(HandoverCompleted::class)]
#[CoversClass(ConsentNotRequired::class)]
#[CoversClass(ExpiredRequest::class)]
#[CoversClass(NotAGuardian::class)]
final class GuardianConsentTest extends TestCase
{
    private const string KEY = 'a key of thirty-two bytes, or so';

    private MockClock $clock;

    private InMemoryMinorStore $minors;

    private InMemoryRequestStore $requests;

    private InMemoryProofStore $proofs;

    private RecordingNotifier $notifier;

    private RecordingEvents $events;

    private GuardianConsent $consent;

    private MemberId $kid;

    private Guardian $parent;

    private Feature $messages;

    private Feature $profile;

    private NoticeVersion $notice;

    #[Override]
    protected function setUp(): void
    {
        // Noon in Quebec; the member turns 14 on 2026-11-20.
        $this->clock = new MockClock('2026-11-14 12:00:00', 'America/Toronto');
        $this->minors = new InMemoryMinorStore();
        $this->requests = new InMemoryRequestStore();
        $this->proofs = new InMemoryProofStore();
        $this->notifier = new RecordingNotifier();
        $this->events = new RecordingEvents();
        $this->consent = $this->consent();
        $this->kid = new MemberId('member-1');
        $this->parent = new Guardian(new GuardianId('guardian-1'), 'parent@example.org');
        $this->messages = new Feature('messages');
        $this->profile = new Feature('public-profile');
        $this->notice = new NoticeVersion('2026-11');
    }

    public function testAMemberBelowTheAgeOfConsentIsKeptWithoutTheirDateOfBirth(): void
    {
        $assessment = $this->consent->register($this->kid, CalendarDate::fromString('2012-11-20'));

        self::assertSame(13, $assessment->age);
        self::assertSame(AgeBracket::BelowConsentAge, $assessment->bracket);

        $minor = $this->consent->minor($this->kid);
        self::assertNotNull($minor);
        self::assertSame('CA-QC', $minor->jurisdiction);
        self::assertSame('2026-11-20', $minor->consentAgeOn->toString());
        self::assertSame('2026-11-14 17:00:00', $minor->registeredAt->format('Y-m-d H:i:s'));
        self::assertSame(ConsentStatus::Refused, $this->consent->status($this->kid, $this->messages));
        self::assertFalse($this->consent->allows($this->kid, $this->messages));
    }

    public function testAMemberAtTheAgeOfConsentIsNotKept(): void
    {
        $assessment = $this->consent->register($this->kid, CalendarDate::fromString('2012-11-14'));

        self::assertSame(AgeBracket::MinorAtConsentAge, $assessment->bracket);
        self::assertNull($this->consent->minor($this->kid));
        self::assertSame(ConsentStatus::NotRequired, $this->consent->status($this->kid, $this->messages));
        self::assertTrue($this->consent->allows($this->kid, $this->messages));
    }

    public function testAMemberOfAnotherJurisdiction(): void
    {
        $consent = $this->consent(new Jurisdictions(Jurisdiction::quebec(), new Jurisdiction('FR', 15, 18, new DateTimeZone('Europe/Paris'))));

        self::assertSame(AgeBracket::BelowConsentAge, $consent->register($this->kid, CalendarDate::fromString('2012-11-14'), 'FR')->bracket);
        $minor = $consent->minor($this->kid);
        self::assertNotNull($minor);
        self::assertSame('FR', $minor->jurisdiction);
        self::assertSame('2027-11-14', $minor->consentAgeOn->toString());
    }

    public function testACorrectedDateOfBirthCorrectsTheRecord(): void
    {
        $this->register();
        $registeredAt = $this->consent->minor($this->kid)?->registeredAt;
        $this->clock->modify('+1 day');

        $this->consent->register($this->kid, CalendarDate::fromString('2012-11-14'));

        $minor = $this->consent->minor($this->kid);
        self::assertNotNull($minor);
        self::assertSame('2026-11-14', $minor->consentAgeOn->toString());
        self::assertSame($registeredAt?->format('U'), $minor->registeredAt->format('U'));
        self::assertSame(ConsentStatus::HandoverRequired, $this->consent->status($this->kid, $this->messages));
    }

    public function testARegistrationAgainKeepsWhatTheRecordSaysOfTheHandover(): void
    {
        $this->register();
        $this->clock->modify('2026-11-20 12:00:00');
        $this->consent->sweep();
        $this->consent->handOver($this->kid, [], $this->notice);

        $before = $this->consent->minor($this->kid);
        $this->clock->modify('+1 day');

        $this->consent->register($this->kid, CalendarDate::fromString('2012-11-20'));

        $after = $this->consent->minor($this->kid);
        self::assertNotNull($before);
        self::assertNotNull($after);
        self::assertSame($before->handoverNoticedAt?->format('U'), $after->handoverNoticedAt?->format('U'));
        self::assertSame($before->handedOverAt?->format('U'), $after->handedOverAt?->format('U'));
        self::assertNotNull($after->handedOverAt);
        self::assertSame('CA-QC', $after->jurisdiction);
    }

    public function testARequestExpiresAfterWholeDaysOfTwentyFourHours(): void
    {
        // Quebec leaves daylight saving time on 2026-11-01: seven days are 168 hours all the same.
        $this->clock->modify('2026-10-30 12:00:00');
        $this->register();

        $request = $this->consent->request($this->kid, $this->parent, [$this->messages], $this->notice);

        self::assertSame('2026-10-30 16:00:00', $request->requestedAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-06 16:00:00', $request->expiresAt->format('Y-m-d H:i:s'));
    }

    public function testTwoAnswersRacingGetOneProof(): void
    {
        $requests = new RacingRequestStore();
        $consent = new GuardianConsent($this->minors, $requests, $this->proofs, $this->notifier, new TokenHasher(self::KEY), $this->clock, events: $this->events);
        $this->register();
        $consent->request($this->kid, $this->parent, [$this->messages], $this->notice);

        try {
            $consent->decide($this->notifier->lastToken(), [$this->messages]);
            self::fail('A request used up by another answer was decided.');
        } catch (InvalidToken) {
        }

        self::assertSame([], $this->proofs->ofMember($this->kid));
        self::assertNotInstanceOf(ConsentDecided::class, end($this->events->events));
    }

    public function testARequestIsSentToTheGuardian(): void
    {
        $this->register();
        $request = $this->consent->request($this->kid, $this->parent, [$this->messages, $this->profile], $this->notice);

        self::assertSame('member-1', $request->member->value);
        self::assertSame('parent@example.org', $request->guardian->email);
        self::assertSame('2026-11-14 17:00:00', $request->requestedAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-21 17:00:00', $request->expiresAt->format('Y-m-d H:i:s'));
        self::assertSame($request, $this->requests->get($request->id));
        self::assertCount(1, $this->notifier->confirmations);
        self::assertSame($request, $this->notifier->confirmations[0][0]);

        $token = $this->notifier->confirmations[0][1];
        self::assertSame($request->id, $token->requestId);
        self::assertTrue(new TokenHasher(self::KEY)->matches($request->tokenHash, $token));
        self::assertStringNotContainsString($token->verifier, $request->tokenHash);
        self::assertEquals([new ConsentRequested($request->id, 'member-1')], $this->events->events);

        self::assertSame(ConsentStatus::Pending, $this->consent->status($this->kid, $this->messages));
        self::assertSame(ConsentStatus::Refused, $this->consent->status($this->kid, new Feature('classifieds')));
        self::assertFalse($this->consent->allows($this->kid, $this->messages));
    }

    public function testTheLinkOpensTheRequestWithoutDecidingIt(): void
    {
        $this->register();
        $request = $this->consent->request($this->kid, $this->parent, [$this->messages], $this->notice);

        self::assertSame($request, $this->consent->open($this->notifier->lastToken()));
        self::assertSame($request, $this->consent->open($this->notifier->lastToken()));
        self::assertSame([], $this->proofs->ofMember($this->kid));
    }

    public function testTheGuardianGrantsSomeFeaturesAndRefusesTheOthers(): void
    {
        $this->register();
        $request = $this->consent->request($this->kid, $this->parent, [$this->messages, $this->profile], $this->notice);
        $this->clock->modify('+1 hour');

        $proofs = $this->consent->decide($this->notifier->lastToken(), [$this->profile]);

        self::assertCount(2, $proofs);
        self::assertSame(['messages', 'public-profile'], array_map(static fn (ConsentProof $proof): string => $proof->feature->name, $proofs));
        self::assertSame([Decision::Refused, Decision::Granted], array_map(static fn (ConsentProof $proof): Decision => $proof->decision, $proofs));
        self::assertSame(Actor::Guardian, $proofs[1]->actor);
        self::assertSame('guardian-1', $proofs[1]->guardian?->value);
        self::assertSame('2026-11', $proofs[1]->notice?->value);
        self::assertSame($request->id, $proofs[1]->requestId);
        self::assertSame('member-1', $proofs[1]->member->value);
        self::assertSame('2026-11-14 18:00:00', $proofs[1]->recordedAt->format('Y-m-d H:i:s'));
        self::assertNotSame($proofs[0]->id, $proofs[1]->id);
        self::assertSame($proofs, $this->proofs->ofMember($this->kid));

        self::assertSame(ConsentStatus::Granted, $this->consent->status($this->kid, $this->profile));
        self::assertTrue($this->consent->allows($this->kid, $this->profile));
        self::assertSame(ConsentStatus::Refused, $this->consent->status($this->kid, $this->messages));
        self::assertNull($this->requests->get($request->id));
        self::assertEquals(new ConsentDecided($request->id, 'member-1'), end($this->events->events));
    }

    public function testATokenIsUsedOnce(): void
    {
        $this->register();
        $this->consent->request($this->kid, $this->parent, [$this->messages], $this->notice);
        $token = $this->notifier->lastToken();
        $this->consent->decide($token, [$this->messages]);

        $this->expectException(InvalidToken::class);

        $this->consent->decide($token, []);
    }

    public function testAnotherVerifierOpensNothing(): void
    {
        $this->register();
        $request = $this->consent->request($this->kid, $this->parent, [$this->messages], $this->notice);
        $forged = $request->id.'.'.ConfirmationToken::generate()->verifier;

        try {
            $this->consent->decide($forged, [$this->messages]);
            self::fail('A forged token decided a request.');
        } catch (InvalidToken $invalidToken) {
            self::assertSame('The confirmation link is not valid.', $invalidToken->getMessage());
        }

        self::assertSame($request, $this->requests->get($request->id), 'A forged token does not use up the request.');
    }

    public function testAMalformedOrUnknownTokenOpensNothing(): void
    {
        try {
            $this->consent->open('not a token');
            self::fail('A malformed token opened a request.');
        } catch (InvalidToken) {
        }

        $this->expectException(InvalidToken::class);

        $this->consent->open(ConfirmationToken::generate()->toString());
    }

    public function testAnExpiredRequestCannotBeAnswered(): void
    {
        $this->consent->register($this->kid, CalendarDate::fromString('2013-11-20'));
        $this->consent->request($this->kid, $this->parent, [$this->messages], $this->notice);

        $this->clock->modify('+7 days -1 second');
        self::assertSame(ConsentStatus::Pending, $this->consent->status($this->kid, $this->messages));
        $this->consent->open($this->notifier->lastToken());
        $this->clock->modify('+1 second');

        self::assertSame(ConsentStatus::Refused, $this->consent->status($this->kid, $this->messages));

        $this->expectException(ExpiredRequest::class);
        $this->expectExceptionMessageIsOrContains('The confirmation link has expired: a new request has to be sent.');

        $this->consent->decide($this->notifier->lastToken(), [$this->messages]);
    }

    public function testTheLifetimeOfARequestIsConfigured(): void
    {
        $this->register();
        $request = $this->consent(requestLifetimeDays: 1)->request($this->kid, $this->parent, [$this->messages], $this->notice);

        self::assertSame('2026-11-15 17:00:00', $request->expiresAt->format('Y-m-d H:i:s'));
    }

    public function testAFeatureGrantedMustBeAsked(): void
    {
        $this->register();
        $this->consent->request($this->kid, $this->parent, [$this->messages], $this->notice);

        try {
            $this->consent->decide($this->notifier->lastToken(), [$this->profile]);
            self::fail('A feature not asked was granted.');
        } catch (InvalidConsent $invalidConsent) {
            self::assertSame('The request does not ask the feature "public-profile".', $invalidConsent->getMessage());
        }

        $this->expectException(InvalidConsent::class);
        $this->expectExceptionMessageIsOrContains('The feature "messages" is given twice.');

        $this->consent->decide($this->notifier->lastToken(), [$this->messages, $this->messages]);
    }

    public function testAnAnswerAfterTheAgeOfConsentUsesTheRequestUp(): void
    {
        $this->register();
        $request = $this->consent->request($this->kid, $this->parent, [$this->messages], $this->notice);
        $this->clock->modify('+6 days');

        try {
            $this->consent->decide($this->notifier->lastToken(), [$this->messages]);
            self::fail('A guardian decided after the age of consent.');
        } catch (ConsentNotRequired $consentNotRequired) {
            self::assertSame('The member has reached the age of consent: the consent is theirs to give.', $consentNotRequired->getMessage());
        }

        self::assertNull($this->requests->get($request->id));
        self::assertSame([], $this->proofs->ofMember($this->kid));
    }

    public function testARequestForAMemberWhoIsNotAMinor(): void
    {
        $this->expectException(ConsentNotRequired::class);
        $this->expectExceptionMessageIsOrContains('The member is not below the age of consent: no guardian consents for them.');

        $this->consent->request($this->kid, $this->parent, [$this->messages], $this->notice);
    }

    public function testARequestAfterTheAgeOfConsent(): void
    {
        $this->register();
        $this->clock->modify('+6 days');

        $this->expectException(ConsentNotRequired::class);
        $this->expectExceptionMessageIsOrContains('The member has reached the age of consent: the consent is theirs to give.');

        $this->consent->request($this->kid, $this->parent, [$this->messages], $this->notice);
    }

    public function testAtMostFiveRequestsWait(): void
    {
        $this->register();

        for ($sent = 0; $sent < 5; ++$sent) {
            $this->consent->request($this->kid, $this->parent, [$this->messages], $this->notice);
        }

        try {
            $this->consent->request($this->kid, $this->parent, [$this->messages], $this->notice);
            self::fail('A sixth request was sent.');
        } catch (InvalidConsent $invalidConsent) {
            self::assertSame('A member has at most 5 requests waiting.', $invalidConsent->getMessage());
        }

        $this->consent->register($this->kid, CalendarDate::fromString('2013-11-20'));
        $this->clock->modify('+7 days');
        $this->consent->request($this->kid, $this->parent, [$this->messages], $this->notice);
        self::assertCount(6, $this->notifier->confirmations);
    }

    public function testAGuardianWithdrawsAConsent(): void
    {
        $this->register();
        $this->consent->request($this->kid, $this->parent, [$this->messages], $this->notice);
        $this->consent->decide($this->notifier->lastToken(), [$this->messages]);

        $this->clock->modify('+1 day');

        $proof = $this->consent->revoke($this->kid, $this->messages, $this->parent->id);

        self::assertSame(Decision::Revoked, $proof->decision);
        self::assertSame(Actor::Guardian, $proof->actor);
        self::assertSame('guardian-1', $proof->guardian?->value);
        self::assertNull($proof->notice);
        self::assertNull($proof->requestId);
        self::assertSame('2026-11-15 17:00:00', $proof->recordedAt->format('Y-m-d H:i:s'));
        self::assertSame(ConsentStatus::Refused, $this->consent->status($this->kid, $this->messages));
        self::assertEquals(new ConsentRevoked('member-1', 'messages'), end($this->events->events));
        self::assertCount(2, $this->proofs->ofMember($this->kid));
    }

    public function testAGuardianWhoNeverDecidedCannotWithdraw(): void
    {
        $this->register();
        $this->consent->request($this->kid, $this->parent, [$this->messages], $this->notice);
        $this->consent->decide($this->notifier->lastToken(), [$this->messages]);
        $this->consent->register(new MemberId('member-2'), CalendarDate::fromString('2013-01-01'));
        $this->consent->request(new MemberId('member-2'), new Guardian(new GuardianId('guardian-2'), 'other@example.org'), [$this->messages], $this->notice);
        $this->consent->decide($this->notifier->lastToken(), [$this->messages]);

        $this->expectException(NotAGuardian::class);
        $this->expectExceptionMessageIsOrContains('This guardian has no consent of the member to withdraw.');

        $this->consent->revoke($this->kid, $this->messages, new GuardianId('guardian-2'));
    }

    public function testAnAdultTheLinkNamesMayWithdraw(): void
    {
        $this->register();
        $consent = $this->consent(link: new FixedGuardianLink('guardian-9', 'member-1'));

        self::assertSame(Decision::Revoked, $consent->revoke($this->kid, $this->messages, new GuardianId('guardian-9'))->decision);

        $this->expectException(NotAGuardian::class);

        $consent->revoke($this->kid, $this->messages, new GuardianId('guardian-8'));
    }

    public function testAWithdrawalAfterTheAgeOfConsent(): void
    {
        $this->register();
        $this->clock->modify('+6 days');

        $this->expectException(ConsentNotRequired::class);

        $this->consent->revoke($this->kid, $this->messages, $this->parent->id);
    }

    public function testANewRequestAfterARefusalIsPending(): void
    {
        $this->register();
        $this->consent->request($this->kid, $this->parent, [$this->messages], $this->notice);
        $this->consent->decide($this->notifier->lastToken(), []);
        self::assertSame(ConsentStatus::Refused, $this->consent->status($this->kid, $this->messages));

        $this->consent->request($this->kid, $this->parent, [$this->messages], $this->notice);
        self::assertSame(ConsentStatus::Pending, $this->consent->status($this->kid, $this->messages));
    }

    public function testTheGuardiansConsentsStopAtTheAgeOfConsent(): void
    {
        $this->register();
        $this->consent->request($this->kid, $this->parent, [$this->messages], $this->notice);
        $this->consent->decide($this->notifier->lastToken(), [$this->messages]);

        $this->clock->modify('2026-11-19 23:59:59');
        self::assertSame(ConsentStatus::Granted, $this->consent->status($this->kid, $this->messages));

        $this->clock->modify('2026-11-20 00:00:00');

        self::assertSame(ConsentStatus::HandoverRequired, $this->consent->status($this->kid, $this->messages));
        self::assertFalse($this->consent->allows($this->kid, $this->messages));
    }

    public function testTheMemberConfirmsTheirOwnChoices(): void
    {
        $this->register();
        $sibling = new MemberId('member-2');
        $this->consent->register($sibling, CalendarDate::fromString('2014-01-01'));
        $this->consent->request($this->kid, $this->parent, [$this->messages, $this->profile], $this->notice);
        $this->consent->decide($this->notifier->lastToken(), [$this->messages, $this->profile]);

        $this->clock->modify('2026-11-20 09:00:00');
        $this->consent->request($sibling, $this->parent, [$this->messages], $this->notice);
        $this->events->events = [];

        $own = new NoticeVersion('2026-11-own');
        $proofs = $this->consent->handOver($this->kid, [$this->profile], $own);

        self::assertCount(1, $proofs);
        self::assertSame(Decision::Granted, $proofs[0]->decision);
        self::assertSame(Actor::Member, $proofs[0]->actor);
        self::assertNull($proofs[0]->guardian);
        self::assertSame('2026-11-own', $proofs[0]->notice?->value);
        self::assertSame('2026-11-20 14:00:00', $this->consent->minor($this->kid)?->handedOverAt?->format('Y-m-d H:i:s'));
        self::assertSame(ConsentStatus::Granted, $this->consent->status($this->kid, $this->profile));
        self::assertSame(ConsentStatus::Refused, $this->consent->status($this->kid, $this->messages));
        self::assertEquals([new HandoverCompleted('member-1')], $this->events->events);

        // Called again: a feature left out is withdrawn, a new one granted.
        $this->clock->modify('+1 day');
        $changes = $this->consent->handOver($this->kid, [$this->messages], $own);

        self::assertSame([['public-profile', Decision::Revoked], ['messages', Decision::Granted]], array_map(static fn (ConsentProof $proof): array => [$proof->feature->name, $proof->decision], $changes));
        self::assertSame(Actor::Member, $changes[0]->actor);
        self::assertNull($changes[0]->guardian);
        self::assertSame('2026-11-own', $changes[0]->notice?->value);
        self::assertSame(ConsentStatus::Refused, $this->consent->status($this->kid, $this->profile));
        self::assertSame(ConsentStatus::Granted, $this->consent->status($this->kid, $this->messages));
        self::assertSame('2026-11-20 14:00:00', $this->consent->minor($this->kid)?->handedOverAt?->format('Y-m-d H:i:s'));

        self::assertCount(1, $this->consent->handOver($this->kid, [], $own));
        self::assertSame(ConsentStatus::Refused, $this->consent->status($this->kid, $this->messages));
        self::assertCount(1, $this->requests->ofMember($sibling), "Only the member's own requests are dropped.");
    }

    public function testAHandoverDropsTheWaitingRequests(): void
    {
        $this->register();
        $this->consent->request($this->kid, $this->parent, [$this->messages], $this->notice);
        $this->clock->modify('2026-11-20 09:00:00');

        $this->consent->handOver($this->kid, [], $this->notice);

        self::assertSame([], $this->requests->ofMember($this->kid));
    }

    public function testAHandoverBeforeTheAgeOfConsent(): void
    {
        $this->register();

        $this->expectException(ConsentNotRequired::class);
        $this->expectExceptionMessageIsOrContains('The member has not reached the age of consent: their guardian consents for them.');

        $this->consent->handOver($this->kid, [$this->messages], $this->notice);
    }

    public function testAHandoverOfAMemberWhoIsNotAMinor(): void
    {
        $this->expectException(ConsentNotRequired::class);
        $this->expectExceptionMessageIsOrContains('The member is not below the age of consent: no guardian consents for them.');

        $this->consent->handOver($this->kid, [], $this->notice);
    }

    public function testAHandoverGrantsAtMost64Features(): void
    {
        $this->register();
        $this->clock->modify('2026-11-20 09:00:00');
        $features = array_map(static fn (int $index): Feature => new Feature('f'.$index), range(1, 64));
        self::assertCount(64, $this->consent->handOver($this->kid, $features, $this->notice));

        try {
            $this->consent->handOver($this->kid, [$this->messages, $this->messages], $this->notice);
            self::fail('A feature was granted twice.');
        } catch (InvalidConsent $invalidConsent) {
            self::assertSame('The feature "messages" is given twice.', $invalidConsent->getMessage());
        }

        $this->expectException(InvalidConsent::class);
        $this->expectExceptionMessageIsOrContains('At most 64 features are granted at once.');

        $this->consent->handOver($this->kid, [...$features, new Feature('f65')], $this->notice);
    }

    public function testTheSweepTellsTheMembersWhoReachTheAgeOfConsent(): void
    {
        $this->register();
        $this->consent->register(new MemberId('member-2'), CalendarDate::fromString('2012-11-21'));
        $this->clock->modify('2026-11-19 23:59:59');
        $this->events->events = [];

        self::assertEquals(new SweepReport(0, 0), $this->consent->sweep());

        $this->clock->modify('2026-11-20 00:00:00');
        $report = $this->consent->sweep();

        self::assertSame(1, $report->handoverNotices);
        self::assertSame(0, $report->erasedRequests);
        self::assertSame(['member-1'], $this->notifier->handoverNotices);
        self::assertSame('2026-11-20 05:00:00', $this->consent->minor($this->kid)?->handoverNoticedAt?->format('Y-m-d H:i:s'));
        self::assertEquals([new HandoverDue('member-1')], $this->events->events);

        self::assertSame(0, $this->consent->sweep()->handoverNotices, 'A member is told once.');
        self::assertSame(['member-1'], $this->notifier->handoverNotices);
    }

    public function testTheSweepWaitsForTheBirthdayInTheMembersTimeZone(): void
    {
        $paris = new Jurisdiction('FR', 14, 18, new DateTimeZone('Europe/Paris'));
        $consent = $this->consent(new Jurisdictions(Jurisdiction::quebec(), $paris));
        $consent->register($this->kid, CalendarDate::fromString('2012-11-20'));
        $consent->register(new MemberId('member-2'), CalendarDate::fromString('2012-11-20'), 'FR');

        // 2026-11-20 03:00 in Paris is still 2026-11-19 in Quebec.
        $this->clock->modify('2026-11-19 21:00:00');
        self::assertSame(1, $consent->sweep()->handoverNotices);
        self::assertSame(['member-2'], $this->notifier->handoverNotices);
    }

    public function testTheSweepHandlesAtMostItsLimit(): void
    {
        foreach (range(1, 3) as $index) {
            $this->consent->register(new MemberId('member-'.$index), CalendarDate::fromString('2012-11-20'));
        }

        $this->clock->modify('2026-11-20 12:00:00');

        self::assertSame(2, $this->consent->sweep(2)->handoverNotices);
        self::assertSame(1, $this->consent->sweep(2)->handoverNotices);
        self::assertSame(0, $this->consent->sweep(1)->handoverNotices);
        self::assertSame(0, $this->consent->sweep(1000)->handoverNotices);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidLimits(): iterable
    {
        yield 'zero' => [0];
        yield 'past 1000' => [1001];
    }

    #[DataProvider('invalidLimits')]
    public function testASweepLimitOutOfBounds(int $limit): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('A sweep handles 1 to 1000 records of each kind.');

        $this->consent->sweep($limit);
    }

    public function testTheSweepErasesUnansweredRequestsAfterTheirRetention(): void
    {
        $this->register();
        $request = $this->consent->request($this->kid, $this->parent, [$this->messages], $this->notice);
        $this->events->events = [];

        // Expired after 7 days, kept 30 more.
        $this->clock->modify('+37 days -1 second');
        $this->consent->register($this->kid, CalendarDate::fromString('2013-11-20'));
        self::assertSame(0, $this->consent->sweep()->erasedRequests);
        self::assertSame($request, $this->requests->get($request->id));

        $this->clock->modify('+1 second');
        $report = $this->consent->sweep();

        self::assertSame(1, $report->erasedRequests);
        self::assertNull($this->requests->get($request->id));
        self::assertEquals([new RequestErased($request->id, 'member-1')], $this->events->events);
    }

    public function testTheRetentionIsConfigured(): void
    {
        $this->register();
        $consent = $this->consent(retentionDays: 0);
        $request = $consent->request($this->kid, $this->parent, [$this->messages], $this->notice);
        $this->clock->modify('+7 days');

        self::assertSame(1, $consent->sweep()->erasedRequests);
        self::assertNull($this->requests->get($request->id));
    }

    /**
     * @return iterable<string, array{int, int, string}>
     */
    public static function invalidDelays(): iterable
    {
        yield 'a lifetime of 0 days' => [0, 30, 'A request may be answered for 1 to 30 days.'];
        yield 'a lifetime of 31 days' => [31, 30, 'A request may be answered for 1 to 30 days.'];
        yield 'a negative retention' => [7, -1, 'An unanswered request is kept 0 to 365 days after it expires.'];
        yield 'a retention of 366 days' => [7, 366, 'An unanswered request is kept 0 to 365 days after it expires.'];
    }

    #[DataProvider('invalidDelays')]
    public function testADelayOutOfBounds(int $lifetime, int $retention, string $message): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->consent(requestLifetimeDays: $lifetime, retentionDays: $retention);
    }

    public function testTheLongestDelays(): void
    {
        $this->register();
        $consent = $this->consent(requestLifetimeDays: 30, retentionDays: 365);

        self::assertSame('2026-12-14 17:00:00', $consent->request($this->kid, $this->parent, [$this->messages], $this->notice)->expiresAt->format('Y-m-d H:i:s'));
    }

    public function testWithoutADispatcher(): void
    {
        $consent = new GuardianConsent($this->minors, $this->requests, $this->proofs, $this->notifier, new TokenHasher(self::KEY), $this->clock);
        $consent->register($this->kid, CalendarDate::fromString('2012-11-20'));
        $consent->request($this->kid, $this->parent, [$this->messages], $this->notice);
        $consent->decide($this->notifier->lastToken(), [$this->messages]);
        $consent->revoke($this->kid, $this->messages, $this->parent->id);

        // Past the retention, the age of consent, and the handover: each part of the sweep and the handover goes on.
        $this->clock->modify('2026-11-20 12:00:00');
        self::assertSame(1, $consent->sweep()->handoverNotices);
        $consent->handOver($this->kid, [$this->messages], $this->notice);
        $this->clock->modify('+40 days');
        self::assertSame(0, $consent->sweep()->erasedRequests);
        $stale = new ConsentRequest(str_pad('1', 21, 'A', \STR_PAD_LEFT).'A', $this->kid, $this->parent, [$this->messages], $this->notice, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-08'), 'hash');
        $this->requests->save($stale);
        self::assertSame(1, $consent->sweep()->erasedRequests);

        self::assertSame([], $this->events->events);
        self::assertSame(ConsentStatus::Granted, $consent->status($this->kid, $this->messages));
        self::assertFalse(new NoGuardianLink()->manages($this->parent->id, $this->kid));
    }

    public function testTheDefaultDelays(): void
    {
        $consent = $this->bare();
        $this->register();
        $request = $consent->request($this->kid, $this->parent, [$this->messages], $this->notice);

        self::assertSame('2026-11-21 17:00:00', $request->expiresAt->format('Y-m-d H:i:s'));

        // Expired after 7 days, kept 30 more.
        $this->clock->modify('+37 days -1 second');
        self::assertSame(0, $consent->sweep()->erasedRequests);
        $this->clock->modify('+1 second');
        self::assertSame(1, $consent->sweep()->erasedRequests);
    }

    public function testTheDefaultSweepHandlesOneHundredRecordsOfEachKind(): void
    {
        $consent = $this->bare();

        foreach (range(1, 101) as $index) {
            $this->requests->save(new ConsentRequest(str_pad((string) $index, 21, 'A', \STR_PAD_LEFT).'A', $this->kid, $this->parent, [$this->messages], $this->notice, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-08'), 'hash'));
            $this->minors->save(new Minor(new MemberId('m-'.$index), 'CA-QC', CalendarDate::fromString('2026-11-14'), new DateTimeImmutable('2026-01-01')));
        }

        $report = $consent->sweep();

        self::assertSame(100, $report->erasedRequests);
        self::assertSame(100, $report->handoverNotices);
    }

    public function testAHandoverKeepsEveryFeatureGrantedAgain(): void
    {
        $this->register();
        $this->consent->request($this->kid, $this->parent, [$this->messages, $this->profile, new Feature('ads')], $this->notice);
        $this->clock->modify('2026-11-20 09:00:00');
        $this->consent->handOver($this->kid, [$this->messages, $this->profile], $this->notice);

        $changes = $this->consent->handOver($this->kid, [$this->messages, $this->profile], $this->notice);

        self::assertSame([Decision::Granted, Decision::Granted], array_map(static fn (ConsentProof $proof): Decision => $proof->decision, $changes));
        self::assertSame(ConsentStatus::Granted, $this->consent->status($this->kid, $this->profile));
    }

    public function testAFeatureWithANumericNameIsKeptByAHandover(): void
    {
        $this->register();
        $this->clock->modify('2026-11-20 09:00:00');
        $numeric = new Feature('123');
        $this->consent->handOver($this->kid, [$numeric], $this->notice);

        $changes = $this->consent->handOver($this->kid, [$numeric], $this->notice);

        self::assertSame([Decision::Granted], array_map(static fn (ConsentProof $proof): Decision => $proof->decision, $changes));
    }

    private function bare(): GuardianConsent
    {
        return new GuardianConsent($this->minors, $this->requests, $this->proofs, $this->notifier, new TokenHasher(self::KEY), $this->clock);
    }

    private function register(): void
    {
        $this->consent->register($this->kid, CalendarDate::fromString('2012-11-20'));
    }

    private function consent(
        Jurisdictions $jurisdictions = new Jurisdictions(),
        ?FixedGuardianLink $link = null,
        int $requestLifetimeDays = 7,
        int $retentionDays = 30,
    ): GuardianConsent {
        return new GuardianConsent(
            $this->minors,
            $this->requests,
            $this->proofs,
            $this->notifier,
            new TokenHasher(self::KEY),
            $this->clock,
            $jurisdictions,
            $this->events,
            $link ?? new NoGuardianLink(),
            $requestLifetimeDays,
            $retentionDays,
        );
    }
}
