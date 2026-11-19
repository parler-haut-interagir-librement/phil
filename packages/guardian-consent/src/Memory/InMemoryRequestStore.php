<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Memory;

use DateTimeImmutable;
use Override;
use Phil\GuardianConsent\ConsentRequest;
use Phil\GuardianConsent\Contract\RequestStore;
use Phil\GuardianConsent\GuardianId;
use Phil\GuardianConsent\MemberId;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The requests in an array, for tests and for a single process. They are lost with the object, or on `reset()`.
 */
final class InMemoryRequestStore implements RequestStore, ResetInterface
{
    /** @var array<string, ConsentRequest> */
    private array $requests = [];

    #[Override]
    public function get(string $id): ?ConsentRequest
    {
        return $this->requests[$id] ?? null;
    }

    #[Override]
    public function save(ConsentRequest $request): void
    {
        $this->requests[$request->id] = $request;
    }

    #[Override]
    public function consume(string $id): bool
    {
        if (!isset($this->requests[$id])) {
            return false;
        }

        unset($this->requests[$id]);

        return true;
    }

    #[Override]
    public function ofMember(MemberId $member): array
    {
        return $this->sorted(array_filter($this->requests, static fn (ConsentRequest $request): bool => $request->member->equals($member)), 'requestedAt');
    }

    #[Override]
    public function ofGuardian(GuardianId $guardian): array
    {
        return $this->sorted(array_filter($this->requests, static fn (ConsentRequest $request): bool => $request->guardian->id->equals($guardian)), 'requestedAt');
    }

    #[Override]
    public function expiredBy(DateTimeImmutable $before, int $limit): array
    {
        return \array_slice($this->sorted(array_filter($this->requests, static fn (ConsentRequest $request): bool => $request->expiresAt <= $before), 'expiresAt'), 0, $limit);
    }

    #[Override]
    public function reset(): void
    {
        $this->requests = [];
    }

    /**
     * @param array<string, ConsentRequest> $requests
     * @param 'expiresAt'|'requestedAt'     $by
     *
     * @return list<ConsentRequest>
     */
    private function sorted(array $requests, string $by): array
    {
        usort($requests, static fn (ConsentRequest $a, ConsentRequest $b): int => [$a->{$by}->format('U.u'), $a->id] <=> [$b->{$by}->format('U.u'), $b->id]);

        return $requests;
    }
}
