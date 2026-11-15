<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Application;

use Phil\Lti\Contract\PlatformRegistrations;
use Phil\Lti\Exception\InvalidRequest;
use Phil\Lti\Exception\ServiceFailed;
use Phil\Lti\Exception\UnknownRegistration;
use Phil\Lti\Message\AgsEndpoint;
use Phil\Lti\Message\Launch;
use Phil\Lti\Registration\PlatformRegistration;
use Phil\Lti\Service\Ags\AgsClient;
use Phil\Lti\Service\Ags\Score;
use Phil\Lti\Service\Scope;
use Phil\LtiBundle\Application\Contract\PendingScores;
use Phil\LtiBundle\Application\Event\ScorePublished;
use Phil\LtiBundle\Application\Message\PublishScore;
use Phil\Url\Url;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Sends the scores of the tool to the platforms: a score is kept as pending, then sent by a message of the bus that
 * carries its identifier only — or at once, without a bus —, and forgotten once the platform takes it. A failure of the
 * network or of the platform throws, so that the bus tries again; a score that can never be sent — its platform no
 * longer registered — is forgotten.
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
     * Sends a score to the line item of the launch, or to the one given.
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
            $this->bus->dispatch(new PublishScore($pending->id));
        }

        return $pending->id;
    }

    /**
     * Sends a pending score; nothing when it was already sent.
     *
     * @throws ServiceFailed       when the platform cannot be reached or refuses, to be tried again
     * @throws UnknownRegistration when the platform is no longer registered: the score is forgotten
     * @throws InvalidRequest      when the score cannot be sent as it is
     */
    public function send(string $id): void
    {
        $pending = $this->pending->find($id);

        if (!$pending instanceof PendingScore) {
            return;
        }

        $platform = $this->platforms->find($pending->issuer, $pending->clientId);

        if (!$platform instanceof PlatformRegistration) {
            $this->pending->delete($id);

            throw UnknownRegistration::platform($pending->issuer, $pending->clientId);
        }

        $this->ags->publishScore($platform, new AgsEndpoint([Scope::SCORE], null, $pending->lineItem), $pending->score);
        $this->pending->delete($id);
        $this->events?->dispatch(new ScorePublished($pending));
    }
}
