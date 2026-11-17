<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Console;

use Phil\LtiBundle\Application\DeepLinkingHandler;
use Phil\LtiBundle\Application\Event\DeepLinkingReturned;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Tells the application again of the deep linking responses it failed to handle, from the claims the platform kept
 * when it verified them: the one a key names, handled or not, or every one not handled after the delay. The listeners
 * see each under its key, with `resumed` true.
 */
#[AsCommand(name: 'phil:lti:resume-deep-linking', description: 'Handles again the LTI deep linking responses kept, by key or all those not handled')]
final readonly class ResumeDeepLinkingCommand
{
    public function __construct(
        private DeepLinkingHandler $handler,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The key of one response; every response not handled when omitted')]
        ?string $key = null,
        #[Option('The seconds since a response came before it is handled again')]
        int $delay = 300,
        #[Option('The most responses handled by this run')]
        int $limit = 100,
    ): int {
        if ($delay < 60 || $limit < 1 || $limit > 1000) {
            $io->error('The delay is 60 seconds or more, and the limit 1 to 1,000.');

            return Command::INVALID;
        }

        $failed = 0;
        $keys = null === $key ? $this->handler->unhandled($delay, $limit) : [$key];

        foreach ($keys as $one) {
            try {
                if (!$this->handler->resume($one) instanceof DeepLinkingReturned) {
                    $io->warning(\sprintf('No response is kept under "%s".', $one));
                    ++$failed;
                }
            } catch (Throwable $throwable) {
                $io->error(\sprintf('The response "%s" failed again: %s', $one, $throwable->getMessage()));
                ++$failed;
            }
        }

        $io->success(\sprintf('Handled %d responses again; %d failed.', \count($keys) - $failed, $failed));

        return 0 === $failed ? Command::SUCCESS : Command::FAILURE;
    }
}
