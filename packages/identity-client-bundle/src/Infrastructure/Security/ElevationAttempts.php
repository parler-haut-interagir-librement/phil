<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\Security;

use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevel;
use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevels;
use Phil\IdentityClientBundle\Application\Event\ElevationRefused;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Remembers, in the session, the elevation under way and the one the provider just declined.
 *
 * A provider is free to answer without reaching the requested class, or to ignore `prompt=login`: without this memory
 * the voter would send the browser back to it forever.
 */
final readonly class ElevationAttempts
{
    private const string PENDING = '_phil_identity_client.elevation.pending';

    private const string REFUSED = '_phil_identity_client.elevation.refused';

    public function __construct(
        private AssuranceLevels $levels,
        private AssuranceEvaluator $evaluator,
        private EventDispatcherInterface $dispatcher,
        private ClockInterface $clock,
        private int $retryAfter,
    ) {
    }

    public function begin(SessionInterface $session, string $level): void
    {
        $session->set(self::PENDING, $level);
    }

    public function forget(SessionInterface $session): void
    {
        $session->remove(self::PENDING);
    }

    /**
     * Records whether the sign-in that just completed reached the level that was pending.
     */
    public function settle(SessionInterface $session, TokenInterface $token): void
    {
        $pending = $session->remove(self::PENDING);
        $level = \is_string($pending) ? $this->levels->get($pending) : null;
        if (!$level instanceof AssuranceLevel || $this->evaluator->isSatisfied($level, $token)) {
            return;
        }

        $this->remember($session, $level->name, $token->getUserIdentifier());
    }

    /**
     * Records that the sign-in failed while a level was pending.
     */
    public function fail(SessionInterface $session): void
    {
        $pending = $session->remove(self::PENDING);
        if (\is_string($pending)) {
            $this->remember($session, $pending, null);
        }
    }

    public function mayRetry(SessionInterface $session, string $level): bool
    {
        $refused = $session->get(self::REFUSED);
        if (!\is_array($refused) || $level !== ($refused['level'] ?? null) || !\is_int($refused['at'] ?? null)) {
            return true;
        }

        return $this->clock->now()->getTimestamp() - $refused['at'] >= $this->retryAfter;
    }

    private function remember(SessionInterface $session, string $level, ?string $subject): void
    {
        $session->set(self::REFUSED, ['level' => $level, 'at' => $this->clock->now()->getTimestamp()]);
        $this->dispatcher->dispatch(new ElevationRefused($subject, $level));
    }
}
