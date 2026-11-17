<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Application;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use Phil\Lti\Contract\PlatformRegistrations;
use Phil\Lti\Exception\InvalidRequest;
use Phil\Lti\Exception\LtiException;
use Phil\Lti\Exception\ServiceFailed;
use Phil\Lti\Exception\TokenRequestRefused;
use Phil\Lti\Exception\UnknownRegistration;
use Phil\Lti\Message\AgsEndpoint;
use Phil\Lti\Message\Launch;
use Phil\Lti\Registration\PlatformRegistration;
use Phil\Lti\Service\Ags\AgsClient;
use Phil\Lti\Service\Ags\Score;
use Phil\Lti\Service\Scope;
use Phil\LtiBundle\Application\Contract\PendingScores;
use Phil\LtiBundle\Application\Event\ScorePublished;
use Phil\LtiBundle\Application\Event\ScoreRefused;
use Phil\LtiBundle\Application\Message\PublishScore;
use Phil\Url\Url;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Sends the scores of the tool to the platforms: a score is kept as pending, then sent by a message of the bus that
 * carries its identifier only — or at once, without a bus —, and forgotten once the platform takes it.
 *
 * The bus taking the message is recorded on the score. A score whose message was never taken — the process stopped
 * between the save and the dispatch, the transport failed, or, without a bus, the platform could not be reached — is
 * sent again by `recover()`, which `phil:lti:recover-scores` runs. A failure of the network or of the platform throws, so
 * that the bus tries again; a score the platform refused for good — its platform no longer registered, a status of 400
 * to 499 other than 408 and 429, a request that cannot be sent — is marked refused with its reason and never sent again,
 * until `phil:lti:purge` removes it. Sending a score twice is harmless: the platform keeps the latest by its timestamp.
 */
final readonly class ScorePublisher
{
    public function __construct(
        private PendingScores $pending,
        private PlatformRegistrations $platforms,
        private AgsClient $ags,
        private ClockInterface $clock,
        private ?MessageBusInterface $bus = null,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * Sends a score to the line item of the launch, or to the one given. A transport that refuses the message leaves
     * the score saved, without acknowledgement, for `recover()`: its identifier is returned all the same.
     *
     * @throws InvalidRequest when the launch does not grant the scope of scores, or names no line item
     */
    public function publish(Launch $launch, Score $score, ?Url $lineItem = null): string
    {
        $endpoint = $launch->message->ags;

        if (!$endpoint instanceof AgsEndpoint || !$endpoint->allows(Scope::SCORE)) {
            throw InvalidRequest::because(\sprintf('The launch does not grant the scope "%s".', Scope::SCORE));
        }

        $target = $lineItem ?? $endpoint->lineItem ?? throw InvalidRequest::because('The launch names no line item to score, and none is given.');
        $pending = new PendingScore(sodium_bin2hex(random_bytes(16)), $launch->issuer, $launch->clientId, $target, $score, $this->clock->now());
        $this->pending->save($pending);

        if (!$this->bus instanceof MessageBusInterface) {
            $this->send($pending->id);
        } else {
            $this->dispatch($pending->id);
        }

        return $pending->id;
    }

    /**
     * Sends a pending score; nothing when it was already sent, or refused.
     *
     * @throws ServiceFailed       when the platform cannot be reached or refuses; a refusal it will repeat marks the
     *                             score refused
     * @throws UnknownRegistration when the platform is no longer registered: the score is marked refused
     * @throws InvalidRequest      when the score cannot be sent as it is: the score is marked refused
     * @throws TokenRequestRefused when the platform gives no access token, to be tried again
     */
    public function send(string $id): void
    {
        $pending = $this->pending->find($id);

        if (!$pending instanceof PendingScore || $pending->refusedAt instanceof DateTimeImmutable) {
            return;
        }

        $platform = $this->platforms->find($pending->issuer, $pending->clientId);

        try {
            if (!$platform instanceof PlatformRegistration) {
                throw UnknownRegistration::platform($pending->issuer, $pending->clientId);
            }

            $this->ags->publishScore($platform, new AgsEndpoint([Scope::SCORE], null, $pending->lineItem), $pending->score);
        } catch (UnknownRegistration|InvalidRequest|ServiceFailed $exception) {
            if (self::isFinal($exception)) {
                $this->refuse($pending, $exception);
            }

            throw $exception;
        }

        $this->pending->delete($id);
        $this->events?->dispatch(new ScorePublished($pending));
    }

    /**
     * Sends again the scores whose message the bus never took, saved or last claimed `$delay` seconds ago or more: each
     * is claimed first, so that two recoveries at once never send the same score twice within the delay. Without a
     * bus, a score the platform still cannot take stays for the next recovery.
     *
     * @param int          $delay seconds since the save or the last claim, 1 at least
     * @param positive-int $limit the most scores sent by this call
     *
     * @return int the scores dispatched, or sent without a bus
     */
    public function recover(int $delay, int $limit = 100): int
    {
        if ($delay < 1) {
            throw new InvalidArgumentException('A recovery waits a second at least: a score saved now may still be on its way.');
        }

        $now = $this->clock->now();
        $sent = 0;

        foreach ($this->pending->claim($now->sub(new DateInterval('PT'.$delay.'S')), $now, $limit) as $pending) {
            try {
                if ($this->bus instanceof MessageBusInterface) {
                    $this->dispatch($pending->id, false);
                } else {
                    $this->send($pending->id);
                }

                ++$sent;
            } catch (TransportException|LtiException) {
                // Claimed, the score waits for the next recovery; a refusal for good was marked by send().
            }
        }

        return $sent;
    }

    /**
     * Whether a failure to send is one the platform will repeat: the score is then refused for good.
     */
    public static function isFinal(LtiException $exception): bool
    {
        if (!$exception instanceof ServiceFailed) {
            return $exception instanceof UnknownRegistration || $exception instanceof InvalidRequest;
        }

        $status = $exception->status();

        return $status >= 400 && $status < 500 && !\in_array($status, [408, 429], true);
    }

    /**
     * @throws TransportException when `$tolerant` is false and the transport refuses the message
     */
    private function dispatch(string $id, bool $tolerant = true): void
    {
        try {
            $this->bus?->dispatch(new PublishScore($id));
        } catch (TransportException $transportException) {
            if ($tolerant) {
                return;
            }

            throw $transportException;
        }

        $this->pending->markDispatched($id, $this->clock->now());
    }

    private function refuse(PendingScore $pending, LtiException $exception): void
    {
        $at = $this->clock->now();
        $reason = PendingScore::refusal($exception->getMessage());
        $this->pending->markRefused($pending->id, $reason, $at);
        $this->events?->dispatch(new ScoreRefused(new PendingScore($pending->id, $pending->issuer, $pending->clientId, $pending->lineItem, $pending->score, $pending->createdAt, $pending->dispatchedAt, $at, $reason)));
    }
}
