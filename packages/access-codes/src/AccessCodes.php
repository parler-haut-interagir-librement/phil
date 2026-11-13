<?php

declare(strict_types=1);

namespace Phil\AccessCodes;

use DateTimeImmutable;
use Phil\AccessCodes\Contract\CodeStore;
use Phil\AccessCodes\Exception\InvalidCode;
use Phil\AccessCodes\Exception\InvalidConfiguration;
use Phil\AccessCodes\Exception\UnknownBatch;
use Psr\Clock\ClockInterface;
use Random\Engine\Secure;
use Random\Randomizer;
use SensitiveParameter;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * The library in one object: batches created with their codes given once, codes redeemed once, batches revoked and
 * counted.
 *
 * A redemption first spends one attempt of the caller's rate limiter — keyed by what the application chooses: the
 * client's address, the account — and is refused without any lookup once the attempts are spent. The code is then read
 * by its format, which refuses a mistyped one, hashed under every key, and redeemed by the store in one atomic step.
 *
 * The object keeps no state between calls: it is safe in a worker that serves many requests.
 */
final readonly class AccessCodes
{
    /** The longest redeemer identifier. */
    public const int MAX_REDEEMER_LENGTH = 191;

    private Randomizer $randomizer;

    public function __construct(
        private CodeStore $store,
        private CodeHasher $hasher,
        private RateLimiterFactoryInterface $attempts,
        private ClockInterface $clock,
        private CodeFormat $format = new CodeFormat(),
        ?Randomizer $randomizer = null,
    ) {
        $this->randomizer = $randomizer ?? new Randomizer(new Secure());
    }

    /**
     * Creates a named batch of distinct codes and stores their hashes; the codes come back once, in the created batch.
     *
     * @throws InvalidConfiguration when the name, the size or the expiry is out of bounds
     */
    public function create(string $name, int $size, ?DateTimeImmutable $expiresAt = null): CreatedBatch
    {
        $now = $this->clock->now();
        $batch = new Batch($this->randomizer->getBytesFromString('0123456789abcdef', 32), trim($name), $size, $now, $expiresAt);
        $codes = [];

        while (\count($codes) < $size) {
            $codes[$this->format->generate($this->randomizer)] = true;
        }

        $codes = array_map(strval(...), array_keys($codes));
        $this->store->add($batch, array_map($this->hasher->hash(...), $codes));

        return new CreatedBatch($batch, $this->format, $codes);
    }

    /**
     * Redeems a code as typed for a redeemer, named by a stable identifier of the application — never an email
     * address.
     *
     * @param string $attemptKey what the rate limiter counts attempts by: the client's address, an account
     *
     * @throws InvalidConfiguration when the redeemer is empty, too long, or holds an `@`
     */
    public function redeem(#[SensitiveParameter] string $code, string $redeemer, string $attemptKey): Redemption
    {
        if ('' === trim($redeemer) || \strlen($redeemer) > self::MAX_REDEEMER_LENGTH || str_contains($redeemer, '@')) {
            throw InvalidConfiguration::because(\sprintf('A redeemer is a stable identifier of 1 to %d bytes, never an email address.', self::MAX_REDEEMER_LENGTH));
        }

        $limit = $this->attempts->create($attemptKey)->consume();

        if (!$limit->isAccepted()) {
            return Redemption::tooManyAttempts($limit->getRetryAfter());
        }

        try {
            $candidates = $this->hasher->candidates($this->format->parse($code));
        } catch (InvalidCode) {
            return Redemption::refused(RedemptionOutcome::Unknown);
        }

        $now = $this->clock->now();
        $batchId = $this->store->redeem($candidates, $redeemer, $now);

        if (null !== $batchId) {
            return Redemption::redeemed($batchId, $now);
        }

        $state = $this->store->find($candidates);

        return match (true) {
            !$state instanceof CodeState => Redemption::refused(RedemptionOutcome::Unknown),
            $state->redeemedAt instanceof DateTimeImmutable => Redemption::refused(RedemptionOutcome::AlreadyRedeemed, $state->batchId, $state->redeemedAt),
            $state->revokedAt instanceof DateTimeImmutable => Redemption::refused(RedemptionOutcome::Revoked, $state->batchId),
            default => Redemption::refused(RedemptionOutcome::Expired, $state->batchId),
        };
    }

    /**
     * Revokes a batch: none of its codes not yet redeemed can be redeemed any more. Returns how many were revoked.
     *
     * @throws UnknownBatch
     */
    public function revoke(string $batchId): int
    {
        $this->batch($batchId);

        return $this->store->revoke($batchId, $this->clock->now());
    }

    /**
     * @throws UnknownBatch
     */
    public function batch(string $batchId): Batch
    {
        return $this->store->batch($batchId) ?? throw UnknownBatch::withId($batchId);
    }

    /**
     * @throws UnknownBatch
     */
    public function counts(string $batchId): BatchCounts
    {
        $this->batch($batchId);

        return $this->store->counts($batchId);
    }
}
