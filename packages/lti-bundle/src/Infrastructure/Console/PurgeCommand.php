<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Console;

use DateInterval;
use Phil\LtiBundle\Application\Contract\DeepLinkingReturns;
use Phil\LtiBundle\Application\Contract\PendingScores;
use Phil\LtiBundle\Infrastructure\Dbal\DbalAccessTokens;
use Phil\LtiBundle\Infrastructure\Dbal\DbalLoginStates;
use Phil\LtiBundle\Infrastructure\Dbal\DbalNonces;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Removes what expired, as a scheduled task runs it: the nonces; the login states and the scores the platforms refused
 * for good more than `$refusedRetention` seconds ago when the tool side is enabled; the access tokens and the deep
 * linking responses kept when the platform side is. Each refused score removed is written out — its identifier,
 * platform, client, moment and reason, never the score —, the last trace of it.
 */
#[AsCommand(name: 'phil:lti:purge', description: 'Removes the expired LTI nonces, login states, access tokens, deep linking responses and refused scores')]
final readonly class PurgeCommand
{
    /**
     * The refused scores removed per round, until none is left.
     */
    public const int BATCH = 100;

    public function __construct(
        private DbalNonces $nonces,
        private ClockInterface $clock,
        private ?DbalLoginStates $states = null,
        private ?DbalAccessTokens $tokens = null,
        private ?PendingScores $pending = null,
        private ?DeepLinkingReturns $returns = null,
        private int $refusedRetention = 2_592_000,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $now = $this->clock->now();
        $refused = 0;

        if ($this->pending instanceof PendingScores) {
            $before = $now->sub(new DateInterval('PT'.$this->refusedRetention.'S'));

            do {
                $batch = $this->pending->refused($before, self::BATCH);

                foreach ($batch as $score) {
                    $io->writeln(\sprintf('Refused score %s of %s, client %s, refused at %s: %s', $score->id, $score->issuer, $score->clientId, $score->refusedAt?->format(\DATE_ATOM) ?? '', $score->refusal ?? ''));
                    $this->pending->delete($score->id);
                    ++$refused;
                }
            } while (self::BATCH === \count($batch));
        }

        $io->success(\sprintf(
            'Removed %d nonces, %d login states, %d access tokens, %d deep linking responses and %d refused scores.',
            $this->nonces->purge(),
            $this->states?->purge() ?? 0,
            $this->tokens?->purge() ?? 0,
            $this->returns?->purge($now) ?? 0,
            $refused,
        ));

        return Command::SUCCESS;
    }
}
