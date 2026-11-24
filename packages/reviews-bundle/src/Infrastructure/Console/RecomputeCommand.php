<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Infrastructure\Console;

use Phil\ReviewsBundle\Application\Ratings;
use Phil\ReviewsBundle\Domain\Exception\InvalidReview;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Computes again the ratings of every subject, after a change of the prior of a type or of its minimum.
 */
#[AsCommand(name: 'phil:reviews:recompute', description: 'Computes the ratings of the reviewed subjects again')]
final readonly class RecomputeCommand
{
    public function __construct(
        private Ratings $ratings,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The type of subject; every declared type when omitted')]
        ?string $type = null,
    ): int {
        try {
            $count = $this->ratings->recomputeAll($type);
        } catch (InvalidReview $invalidReview) {
            $io->error($invalidReview->getMessage());

            return Command::INVALID;
        }

        $io->success(1 === $count ? 'The ratings of one subject were computed again.' : \sprintf('The ratings of %d subjects were computed again.', $count));

        return Command::SUCCESS;
    }
}
