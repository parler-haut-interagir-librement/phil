<?php

declare(strict_types=1);

namespace Phil\GuardianConsent;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Phil\GuardianConsent\Contract\GuardianLink;
use Phil\GuardianConsent\Contract\MinorStore;
use Phil\GuardianConsent\Contract\Notifier;
use Phil\GuardianConsent\Contract\ProofStore;
use Phil\GuardianConsent\Contract\RequestStore;
use Phil\GuardianConsent\Contract\Transactions;
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
use Phil\GuardianConsent\Exception\UnknownJurisdiction;
use Phil\GuardianConsent\Internal\RandomId;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Random\Randomizer;
use SensitiveParameter;

/**
 * The parental consent of members below the age of consent, feature by feature.
 *
 * A member is registered from their date of birth, which is read and dropped: only the day they reach the age of
 * consent is kept. Below it, every feature is refused until a guardian grants it, through a request sent by e-mail
 * and confirmed by a signed, single-use link that expires; the guardian may withdraw a consent at any time. From the
 * age of consent, the guardian's consents no longer apply: the member is told, and confirms their own choices.
 *
 * Every decision is kept as a dated proof. The services keep no state between calls: they are safe in a long-running
 * worker.
 */
final readonly class GuardianConsent
{
    public const int MAX_REQUEST_LIFETIME_DAYS = 30;

    public const int MAX_RETENTION_DAYS = 365;

    /** The most requests a member may have waiting at once, so that a guardian's mailbox cannot be flooded. */
    public const int MAX_PENDING_REQUESTS = 5;

    public const int MAX_SWEEP = 1000;

    /**
     * @param int          $requestLifetimeDays the days a request may be answered, 1 to 30
     * @param int          $retentionDays       the days an unanswered request is kept after it expires, 0 to 365, before it is erased
     * @param Transactions $transactions        the transactions of the stores' database, so that an answer or a handover is written whole
     *
     * @throws InvalidConfiguration when a delay is out of bounds
     */
    public function __construct(
        private MinorStore $minors,
        private RequestStore $requests,
        private ProofStore $proofs,
        private Notifier $notifier,
        private TokenHasher $hasher,
        private ClockInterface $clock,
        private Jurisdictions $jurisdictions = new Jurisdictions(),
        private ?EventDispatcherInterface $events = null,
        private GuardianLink $link = new NoGuardianLink(),
        private int $requestLifetimeDays = 7,
        private int $retentionDays = 30,
        private Randomizer $randomizer = new Randomizer(),
        private Transactions $transactions = new NoTransactions(),
    ) {
        if ($requestLifetimeDays < 1 || $requestLifetimeDays > self::MAX_REQUEST_LIFETIME_DAYS) {
            throw InvalidConfiguration::because(\sprintf('A request may be answered for 1 to %d days.', self::MAX_REQUEST_LIFETIME_DAYS));
        }

        if ($retentionDays < 0 || $retentionDays > self::MAX_RETENTION_DAYS) {
            throw InvalidConfiguration::because(\sprintf('An unanswered request is kept 0 to %d days after it expires.', self::MAX_RETENTION_DAYS));
        }
    }

    /**
     * Reads a member's age from their date of birth, which is not kept. A member below the age of consent is kept as a
     * minor, with the day they reach it; a member already kept has that day corrected.
     *
     * @param ?string $jurisdiction the code of the member's jurisdiction; the default one when none is given
     *
     * @throws UnknownJurisdiction
     * @throws InvalidConsent      when the date of birth is after today, or more than 150 years before
     */
    public function register(MemberId $member, CalendarDate $birthDate, ?string $jurisdiction = null): AgeAssessment
    {
        $rules = $this->jurisdictions->get($jurisdiction);
        $assessment = $rules->assess($birthDate, $this->clock);
        $known = $this->minors->get($member);

        if ($known instanceof Minor) {
            $this->minors->save(new Minor($member, $rules->code, $assessment->consentAgeOn, $known->registeredAt, $known->handoverNoticedAt, $known->handedOverAt));
        } elseif ($assessment->needsGuardian()) {
            $this->minors->save(new Minor($member, $rules->code, $assessment->consentAgeOn, $this->now()));
        }

        return $assessment;
    }

    /**
     * The member's record, when they are kept as a minor.
     */
    public function minor(MemberId $member): ?Minor
    {
        return $this->minors->get($member);
    }

    /**
     * Where the member's consent to a feature stands.
     *
     * @throws UnknownJurisdiction when the member's jurisdiction is no longer configured
     */
    public function status(MemberId $member, Feature $feature): ConsentStatus
    {
        $minor = $this->minors->get($member);

        if (!$minor instanceof Minor) {
            return ConsentStatus::NotRequired;
        }

        if ($this->hasReachedConsentAge($minor)) {
            if (!$minor->isHandedOver()) {
                return ConsentStatus::HandoverRequired;
            }

            return Decision::Granted === $this->latest($member, $feature, Actor::Member)?->decision ? ConsentStatus::Granted : ConsentStatus::Refused;
        }

        if (Decision::Granted === $this->latest($member, $feature, Actor::Guardian)?->decision) {
            return ConsentStatus::Granted;
        }

        $now = $this->now();

        foreach ($this->requests->ofMember($member) as $request) {
            if ($request->covers($feature) && !$request->isExpired($now)) {
                return ConsentStatus::Pending;
            }
        }

        return ConsentStatus::Refused;
    }

    /**
     * Whether the member may use the feature: the consent is given, or not required.
     *
     * @throws UnknownJurisdiction when the member's jurisdiction is no longer configured
     */
    public function allows(MemberId $member, Feature $feature): bool
    {
        return $this->status($member, $feature)->allows();
    }

    /**
     * Asks a guardian to consent to features for a member below the age of consent, and sends them the confirmation
     * link through the notifier.
     *
     * @param list<Feature> $features
     *
     * @throws ConsentNotRequired when the member is not kept as a minor, or has reached the age of consent
     * @throws InvalidConsent     when the features are none, more than 64 or given twice, or five requests already wait
     */
    public function request(MemberId $member, Guardian $guardian, array $features, NoticeVersion $notice): ConsentRequest
    {
        $this->belowConsentAge($member);
        $now = $this->now();
        $waiting = array_filter($this->requests->ofMember($member), static fn (ConsentRequest $request): bool => !$request->isExpired($now));

        if (\count($waiting) >= self::MAX_PENDING_REQUESTS) {
            throw InvalidConsent::because(\sprintf('A member has at most %d requests waiting.', self::MAX_PENDING_REQUESTS));
        }

        $token = ConfirmationToken::generate($this->randomizer);
        $request = new ConsentRequest(
            $token->requestId,
            $member,
            $guardian,
            $features,
            $notice,
            $now,
            $now->add(new DateInterval(\sprintf('P%dD', $this->requestLifetimeDays))),
            $this->hasher->hash($token),
        );

        $this->requests->save($request);
        $this->notifier->sendConfirmation($request, $token);
        $this->events?->dispatch(new ConsentRequested($request->id, $member->value));

        return $request;
    }

    /**
     * The request a confirmation link opens, to show the guardian. It decides nothing.
     *
     * @throws InvalidToken   when the token is malformed, unknown, used, or not the one issued
     * @throws ExpiredRequest when the request may no longer be answered
     */
    public function open(#[SensitiveParameter] string $token): ConsentRequest
    {
        $parsed = ConfirmationToken::parse($token);
        $request = $this->requests->get($parsed->requestId);

        if (!$request instanceof ConsentRequest || !$this->hasher->matches($request->tokenHash, $parsed)) {
            throw InvalidToken::rejected();
        }

        if ($request->isExpired($this->now())) {
            throw ExpiredRequest::create();
        }

        return $request;
    }

    /**
     * The guardian's answer to a request: the features granted, the others of the request refused. The token is used
     * up, and a proof recorded for every feature of the request, in one transaction: when a write fails, the token
     * answers again.
     *
     * @param list<Feature> $granted
     *
     * @return non-empty-list<ConsentProof>
     *
     * @throws InvalidToken       when the token is malformed, unknown, used, or not the one issued
     * @throws ExpiredRequest     when the request may no longer be answered
     * @throws InvalidConsent     when a feature granted is not one the request asks, or is given twice
     * @throws ConsentNotRequired when the member is no longer kept as a minor, or has reached the age of consent
     */
    public function decide(#[SensitiveParameter] string $token, array $granted): array
    {
        $request = $this->open($token);
        $grantedNames = $this->names($granted);

        foreach ($granted as $feature) {
            if (!$request->covers($feature)) {
                throw InvalidConsent::because(\sprintf('The request does not ask the feature "%s".', $feature->name));
            }
        }

        try {
            $this->belowConsentAge($request->member);
        } catch (ConsentNotRequired $consentNotRequired) {
            // The request can no longer be answered: it is used up all the same.
            $this->requests->consume($request->id);

            throw $consentNotRequired;
        }

        $now = $this->now();

        // The request is used up and every proof written in one transaction: a write that fails gives the request back.
        $proofs = $this->transactions->run(function () use ($request, $grantedNames, $now): array {
            if (!$this->requests->consume($request->id)) {
                throw InvalidToken::rejected();
            }

            $proofs = [];

            foreach ($request->features as $feature) {
                $proofs[] = $this->record($request->member, $feature, \in_array($feature->name, $grantedNames, true) ? Decision::Granted : Decision::Refused, Actor::Guardian, $request->guardian->id, $request->notice, $request->id, $now);
            }

            return $proofs;
        });

        $this->events?->dispatch(new ConsentDecided($request->id, $request->member->value));

        return $proofs;
    }

    /**
     * Withdraws a consent, at the guardian's request: the feature is refused from now on.
     *
     * @throws ConsentNotRequired when the member is not kept as a minor, or has reached the age of consent
     * @throws NotAGuardian       when the guardian never decided on the member's consents, and the link does not name them
     */
    public function revoke(MemberId $member, Feature $feature, GuardianId $guardian): ConsentProof
    {
        $this->belowConsentAge($member);

        if (!$this->hasDecided($guardian, $member) && !$this->link->manages($guardian, $member)) {
            throw NotAGuardian::create();
        }

        $proof = $this->record($member, $feature, Decision::Revoked, Actor::Guardian, $guardian, null, null, $this->now());
        $this->events?->dispatch(new ConsentRevoked($member->value, $feature->name));

        return $proof;
    }

    /**
     * The member's own choices, once they have reached the age of consent: the features they grant. A feature they
     * granted before and leave out is withdrawn; any other is refused, the default. Called again, it records the
     * changes. The requests still waiting for a guardian are dropped.
     *
     * @param list<Feature> $granted
     *
     * @return list<ConsentProof>
     *
     * @throws ConsentNotRequired when the member is not kept as a minor, or has not reached the age of consent
     * @throws InvalidConsent     when more than 64 features are granted, or one is given twice
     */
    public function handOver(MemberId $member, array $granted, NoticeVersion $notice): array
    {
        $minor = $this->minors->get($member) ?? throw ConsentNotRequired::notAMinor();

        if (!$this->hasReachedConsentAge($minor)) {
            throw ConsentNotRequired::handoverNotReached();
        }

        $grantedNames = $this->names($granted);

        if (\count($granted) > ConsentRequest::MAX_FEATURES) {
            throw InvalidConsent::because(\sprintf('At most %d features are granted at once.', ConsentRequest::MAX_FEATURES));
        }

        $now = $this->now();

        // The member's choices, the requests dropped and the record of the handover are written whole, or not at all.
        $proofs = $this->transactions->run(function () use ($member, $minor, $granted, $grantedNames, $notice, $now): array {
            $proofs = [];

            foreach ($this->latestOf($member, Actor::Member) as $proof) {
                if (Decision::Granted === $proof->decision && !\in_array($proof->feature->name, $grantedNames, true)) {
                    $proofs[] = $this->record($member, $proof->feature, Decision::Revoked, Actor::Member, null, $notice, null, $now);
                }
            }

            foreach ($granted as $feature) {
                $proofs[] = $this->record($member, $feature, Decision::Granted, Actor::Member, null, $notice, null, $now);
            }

            foreach ($this->requests->ofMember($member) as $request) {
                $this->requests->consume($request->id);
            }

            if (!$minor->isHandedOver()) {
                $this->minors->save($minor->withHandedOver($now));
            }

            return $proofs;
        });

        $this->events?->dispatch(new HandoverCompleted($member->value));

        return $proofs;
    }

    /**
     * The work to run once a day, from a scheduler: erases the requests left unanswered past their retention, and
     * tells the members who reached the age of consent. Each part handles at most `$limit` records; a larger backlog
     * is handled by the next runs.
     *
     * @throws InvalidConfiguration when the limit is not 1 to 1000
     * @throws UnknownJurisdiction  when a minor's jurisdiction is no longer configured
     */
    public function sweep(int $limit = 100): SweepReport
    {
        if ($limit < 1 || $limit > self::MAX_SWEEP) {
            throw InvalidConfiguration::because(\sprintf('A sweep handles 1 to %d records of each kind.', self::MAX_SWEEP));
        }

        $now = $this->now();
        $erased = 0;

        foreach ($this->requests->expiredBy($now->sub(new DateInterval(\sprintf('P%dD', $this->retentionDays))), $limit) as $request) {
            if ($this->requests->consume($request->id)) {
                ++$erased;
                $this->events?->dispatch(new RequestErased($request->id, $request->member->value));
            }
        }

        // The latest day it is in any jurisdiction served: the store lists every minor who may have reached the age of
        // consent somewhere, and each is checked in their own jurisdiction.
        $latest = $this->jurisdictions->default->today($this->clock);

        foreach ($this->jurisdictions->codes() as $code) {
            $today = $this->jurisdictions->get($code)->today($this->clock);
            $latest = $today->isAfter($latest) ? $today : $latest;
        }

        $noticed = 0;

        foreach ($this->minors->dueForHandover($latest, $limit) as $minor) {
            if ($this->hasReachedConsentAge($minor)) {
                $this->minors->save($minor->withHandoverNoticed($now));
                $this->notifier->sendHandoverNotice($minor->member);
                $this->events?->dispatch(new HandoverDue($minor->member->value));
                ++$noticed;
            }
        }

        return new SweepReport($erased, $noticed);
    }

    /**
     * @throws ConsentNotRequired
     */
    private function belowConsentAge(MemberId $member): Minor
    {
        $minor = $this->minors->get($member) ?? throw ConsentNotRequired::notAMinor();

        if ($this->hasReachedConsentAge($minor)) {
            throw ConsentNotRequired::handoverReached();
        }

        return $minor;
    }

    private function hasReachedConsentAge(Minor $minor): bool
    {
        return $minor->hasReachedConsentAge($this->jurisdictions->get($minor->jurisdiction)->today($this->clock));
    }

    private function hasDecided(GuardianId $guardian, MemberId $member): bool
    {
        return array_any($this->proofs->ofGuardian($guardian), static fn (ConsentProof $proof): bool => $proof->member->equals($member));
    }

    private function latest(MemberId $member, Feature $feature, Actor $actor): ?ConsentProof
    {
        return $this->latestOf($member, $actor)[$feature->name] ?? null;
    }

    /**
     * The latest proof of each feature decided by the actor, by feature name.
     *
     * @return array<string, ConsentProof>
     */
    private function latestOf(MemberId $member, Actor $actor): array
    {
        $latest = [];

        foreach ($this->proofs->ofMember($member) as $proof) {
            if ($actor === $proof->actor) {
                $latest[$proof->feature->name] = $proof;
            }
        }

        return $latest;
    }

    private function record(MemberId $member, Feature $feature, Decision $decision, Actor $actor, ?GuardianId $guardian, ?NoticeVersion $notice, ?string $requestId, DateTimeImmutable $at): ConsentProof
    {
        $proof = new ConsentProof(RandomId::generate($this->randomizer, ConsentProof::ID_BYTES), $member, $feature, $decision, $actor, $guardian, $notice, $requestId, $at);
        $this->proofs->append($proof);

        return $proof;
    }

    /**
     * @param list<Feature> $features
     *
     * @return list<string>
     *
     * @throws InvalidConsent when a feature is given twice
     */
    private function names(array $features): array
    {
        $names = [];

        foreach ($features as $feature) {
            if (\in_array($feature->name, $names, true)) {
                throw InvalidConsent::because(\sprintf('The feature "%s" is given twice.', $feature->name));
            }

            $names[] = $feature->name;
        }

        return $names;
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
    }
}
