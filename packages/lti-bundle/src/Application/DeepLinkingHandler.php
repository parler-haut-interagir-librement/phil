<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Application;

use DateInterval;
use InvalidArgumentException;
use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Platform\DeepLinkingResult;
use Phil\LtiBundle\Application\Contract\DeepLinkingReturns;
use Phil\LtiBundle\Application\Event\DeepLinkingReturned;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Hands the deep linking responses the platform verified to the application: each is kept first, for `$retention`
 * seconds, then told by `DeepLinkingReturned` under its key, then marked handled. A listener that throws leaves the
 * response kept and not handled: `resume()` tells it again from the claims kept, without its token, whose nonce was
 * taken once and for all. A listener may so see a response twice, under the same key, and is written to be idempotent.
 */
final readonly class DeepLinkingHandler
{
    public const int DEFAULT_RETENTION = 86_400;

    /**
     * @param int $retention seconds a response is kept, 1 at least
     */
    public function __construct(
        private DeepLinkingReturns $returns,
        private EventDispatcherInterface $events,
        private ClockInterface $clock,
        private int $retention = self::DEFAULT_RETENTION,
        private bool $allowLocalhost = false,
    ) {
        if ($retention < 1) {
            throw new InvalidArgumentException('A deep linking response is kept a second at least.');
        }
    }

    /**
     * Keeps the response, then tells the application.
     */
    public function handle(DeepLinkingResult $result): DeepLinkingReturned
    {
        $now = $this->clock->now();
        $this->returns->save(StoredDeepLinkingReturn::of($result, $now, $now->add(new DateInterval('PT'.$this->retention.'S'))));

        return $this->tell($result, false);
    }

    /**
     * Tells the application again of a response kept and not expired, handled or not; null when there is none.
     *
     * @throws InvalidToken when the claims kept are not those of a response
     */
    public function resume(string $key): ?DeepLinkingReturned
    {
        $stored = $this->returns->find($key, $this->clock->now());

        return $stored instanceof StoredDeepLinkingReturn ? $this->tell($stored->result($this->allowLocalhost), true) : null;
    }

    /**
     * The keys of the responses not handled, received `$delay` seconds ago or more, oldest first: those whose handling
     * failed, or is still running when the delay is short.
     *
     * @param positive-int $delay
     * @param positive-int $limit
     *
     * @return list<string>
     */
    public function unhandled(int $delay, int $limit = 100): array
    {
        $now = $this->clock->now();

        return array_map(static fn (StoredDeepLinkingReturn $return): string => $return->key, $this->returns->unhandled($now->sub(new DateInterval('PT'.$delay.'S')), $now, $limit));
    }

    private function tell(DeepLinkingResult $result, bool $resumed): DeepLinkingReturned
    {
        $event = new DeepLinkingReturned($result, $resumed);
        $this->events->dispatch($event);
        $this->returns->markHandled($result->key, $this->clock->now());

        return $event;
    }
}
