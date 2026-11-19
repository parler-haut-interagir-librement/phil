<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\GuardianConsent\ConsentRequest;
use Phil\GuardianConsent\Contract\RequestStore;
use Phil\GuardianConsent\GuardianId;
use Phil\GuardianConsent\MemberId;
use Phil\GuardianConsent\Memory\InMemoryRequestStore;

/**
 * A store where another process always uses a request up first: `consume()` removes it and says it was gone.
 */
final readonly class RacingRequestStore implements RequestStore
{
    public function __construct(
        private InMemoryRequestStore $inner = new InMemoryRequestStore(),
    ) {
    }

    #[Override]
    public function get(string $id): ?ConsentRequest
    {
        return $this->inner->get($id);
    }

    #[Override]
    public function save(ConsentRequest $request): void
    {
        $this->inner->save($request);
    }

    #[Override]
    public function consume(string $id): bool
    {
        $this->inner->consume($id);

        return false;
    }

    #[Override]
    public function ofMember(MemberId $member): array
    {
        return $this->inner->ofMember($member);
    }

    #[Override]
    public function ofGuardian(GuardianId $guardian): array
    {
        return $this->inner->ofGuardian($guardian);
    }

    #[Override]
    public function expiredBy(DateTimeImmutable $before, int $limit): array
    {
        return $this->inner->expiredBy($before, $limit);
    }
}
