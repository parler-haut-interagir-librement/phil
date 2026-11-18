<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Console;

use Phil\CoursesBundle\Application\SecondChances;
use Phil\CoursesBundle\Domain\Exception\CoursesException;
use Phil\CoursesBundle\Domain\Model\RetryRequest;
use Phil\CoursesBundle\Domain\ValueObject\RetryRequestId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `phil:courses:retries`: lists the requests for a second chance waiting for a decision, or grants or refuses one, or
 * waives its activity, in the name of a teacher, with their note.
 */
#[AsCommand(name: 'phil:courses:retries', description: 'Lists, grants or refuses the requests for a second chance, or waives their activity.')]
final readonly class RetriesCommand
{
    public function __construct(
        private SecondChances $secondChances,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('The request to grant.')]
        ?string $grant = null,
        #[Option('The request to refuse.')]
        ?string $refuse = null,
        #[Option('The request whose activity to waive.')]
        ?string $waive = null,
        #[Option('Why, as the teacher writes it.')]
        string $note = '',
        #[Option('The stable identifier of the teacher who decides.')]
        ?string $by = null,
    ): int {
        $decisions = array_filter(['grant' => $grant, 'refuse' => $refuse, 'waive' => $waive], static fn (?string $request): bool => null !== $request);

        if ([] === $decisions) {
            $io->table(['Request', 'Enrollment', 'Activity', 'Asked at (UTC)', 'Reason'], array_map(static fn (RetryRequest $request): array => [
                $request->id->value,
                $request->enrollment->value,
                $request->activity->value,
                $request->requestedAt->format('Y-m-d H:i'),
                mb_substr($request->reason, 0, 80),
            ], $this->secondChances->waiting()));

            return Command::SUCCESS;
        }

        if (\count($decisions) > 1 || null === $by) {
            $io->error('Give one of --grant, --refuse or --waive, and name the teacher who decides with --by.');

            return Command::INVALID;
        }

        $decision = array_key_first($decisions);

        try {
            $request = match ($decision) {
                'grant' => $this->secondChances->grant(RetryRequestId::of($decisions[$decision]), $by, $note),
                'refuse' => $this->secondChances->refuse(RetryRequestId::of($decisions[$decision]), $by, $note),
                default => $this->secondChances->waive(RetryRequestId::of($decisions[$decision]), $by, $note),
            };
        } catch (CoursesException $coursesException) {
            $io->error($coursesException->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('The request %s is %s.', $request->id->value, $request->decision->value));

        return Command::SUCCESS;
    }
}
