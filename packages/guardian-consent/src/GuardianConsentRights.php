<?php

declare(strict_types=1);

namespace Phil\GuardianConsent;

use Override;
use Phil\GuardianConsent\Contract\MinorStore;
use Phil\GuardianConsent\Contract\ProofStore;
use Phil\GuardianConsent\Contract\RequestStore;
use Phil\GuardianConsent\Contract\SubjectRights;
use Phil\GuardianConsent\Exception\InvalidIdentifier;

/**
 * Access and erasure of what the library keeps on a person, read as a member and as a guardian.
 *
 * As a member: their minor record, the requests about them and the proofs of their consents, all erased. As a guardian:
 * the requests sent to them, erased with their address, and the proofs of their decisions, which stay — they are the
 * member's — but no longer name them. A subject the library could not have kept, such as an e-mail address, has nothing.
 */
final readonly class GuardianConsentRights implements SubjectRights
{
    public function __construct(
        private MinorStore $minors,
        private RequestStore $requests,
        private ProofStore $proofs,
    ) {
    }

    #[Override]
    public function export(string $subject): array
    {
        try {
            $member = new MemberId($subject);
            $guardian = new GuardianId($subject);
        } catch (InvalidIdentifier) {
            return [];
        }

        $minor = $this->minors->get($member);
        $asMember = array_filter([
            'minor' => $minor instanceof Minor ? [
                'jurisdiction' => $minor->jurisdiction,
                'consent_age_on' => $minor->consentAgeOn->toString(),
                'registered_at' => $minor->registeredAt->format(\DATE_ATOM),
                'handover_noticed_at' => $minor->handoverNoticedAt?->format(\DATE_ATOM),
                'handed_over_at' => $minor->handedOverAt?->format(\DATE_ATOM),
            ] : [],
            'requests' => array_map(self::request(...), $this->requests->ofMember($member)),
            'proofs' => array_map($this->proof(...), $this->proofs->ofMember($member)),
        ]);
        $asGuardian = array_filter([
            'requests' => array_map(
                static fn (ConsentRequest $request): array => self::request($request) + ['guardian_email' => $request->guardian->email],
                $this->requests->ofGuardian($guardian),
            ),
            'proofs' => array_map($this->proof(...), $this->proofs->ofGuardian($guardian)),
        ]);

        return array_filter(['member' => $asMember, 'guardian' => $asGuardian]);
    }

    #[Override]
    public function erase(string $subject): int
    {
        try {
            $member = new MemberId($subject);
            $guardian = new GuardianId($subject);
        } catch (InvalidIdentifier) {
            return 0;
        }

        $erased = 0;

        if ($this->minors->get($member) instanceof Minor) {
            $this->minors->remove($member);
            ++$erased;
        }

        foreach ([...$this->requests->ofMember($member), ...$this->requests->ofGuardian($guardian)] as $request) {
            $erased += $this->requests->consume($request->id) ? 1 : 0;
        }

        return $erased + $this->proofs->removeMember($member) + $this->proofs->forgetGuardian($guardian);
    }

    /**
     * @return array<string, mixed>
     */
    private static function request(ConsentRequest $request): array
    {
        return [
            'id' => $request->id,
            'member' => $request->member->value,
            'guardian' => $request->guardian->id->value,
            'features' => array_map(static fn (Feature $feature): string => $feature->name, $request->features),
            'notice' => $request->notice->value,
            'requested_at' => $request->requestedAt->format(\DATE_ATOM),
            'expires_at' => $request->expiresAt->format(\DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function proof(ConsentProof $proof): array
    {
        return [
            'id' => $proof->id,
            'member' => $proof->member->value,
            'feature' => $proof->feature->name,
            'decision' => $proof->decision->value,
            'actor' => $proof->actor->value,
            'guardian' => $proof->guardian?->value,
            'notice' => $proof->notice?->value,
            'request' => $proof->requestId,
            'recorded_at' => $proof->recordedAt->format(\DATE_ATOM),
        ];
    }
}
