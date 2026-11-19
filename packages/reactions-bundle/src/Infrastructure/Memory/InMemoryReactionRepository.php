<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Infrastructure\Memory;

use Override;
use Phil\ReactionsBundle\Domain\Exception\AlreadyReacted;
use Phil\ReactionsBundle\Domain\Model\Reaction;
use Phil\ReactionsBundle\Domain\Repository\ReactionRepository;
use Phil\Resource\ResourceReference;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Reactions kept in memory, for tests and examples. The bundle tags the store `kernel.reset`: it is emptied between two
 * requests, under PHP-FPM as in a worker, so that nothing a request writes reaches the next one, nor another member. A
 * test that reads what an earlier request wrote uses the Doctrine store on SQLite.
 */
final class InMemoryReactionRepository implements ReactionRepository, ResetInterface
{
    /** @var array<string, Reaction> by identifier, oldest first */
    private array $reactions = [];

    #[Override]
    public function of(ResourceReference $content, string $member): array
    {
        $key = $content->toString();

        return array_values(array_filter($this->reactions, static fn (Reaction $reaction): bool => $key === $reaction->content && $member === $reaction->member));
    }

    #[Override]
    public function add(Reaction $reaction): void
    {
        foreach ($this->reactions as $kept) {
            if ($kept->content === $reaction->content && $kept->member === $reaction->member && $kept->code() === $reaction->code()) {
                throw AlreadyReacted::on($reaction->content, $reaction->code());
            }
        }

        $this->reactions[$reaction->id] = $reaction;
        ksort($this->reactions, \SORT_STRING);
    }

    #[Override]
    public function save(Reaction $reaction): void
    {
        if (isset($this->reactions[$reaction->id])) {
            $this->reactions[$reaction->id] = $reaction;
        }
    }

    #[Override]
    public function remove(Reaction $reaction): void
    {
        unset($this->reactions[$reaction->id]);
    }

    #[Override]
    public function counts(array $contents): array
    {
        $counts = [];

        foreach ($this->in($contents) as $reaction) {
            $counts[$reaction->content][$reaction->code()] = ($counts[$reaction->content][$reaction->code()] ?? 0) + 1;
        }

        return $counts;
    }

    #[Override]
    public function chosenBy(string $member, array $contents): array
    {
        $chosen = [];

        foreach ($this->in($contents) as $reaction) {
            if ($member === $reaction->member) {
                $chosen[$reaction->content][] = $reaction->code();
            }
        }

        return $chosen;
    }

    #[Override]
    public function reactors(array $contents, int $perCode): array
    {
        $reactors = [];

        foreach ($this->in($contents) as $reaction) {
            if (\count($reactors[$reaction->content][$reaction->code()] ?? []) < $perCode) {
                $reactors[$reaction->content][$reaction->code()][] = $reaction->member;
            }
        }

        return $reactors;
    }

    #[Override]
    public function ofMember(string $member): array
    {
        return array_values(array_filter($this->reactions, static fn (Reaction $reaction): bool => $member === $reaction->member));
    }

    #[Override]
    public function eraseMember(string $member): void
    {
        $this->reactions = array_filter($this->reactions, static fn (Reaction $reaction): bool => $member !== $reaction->member);
    }

    #[Override]
    public function eraseContent(ResourceReference $content): void
    {
        $this->reactions = array_filter($this->reactions, static fn (Reaction $reaction): bool => $content->toString() !== $reaction->content);
    }

    #[Override]
    public function reset(): void
    {
        $this->reactions = [];
    }

    /**
     * @param list<string> $contents
     *
     * @return array<string, Reaction>
     */
    private function in(array $contents): array
    {
        return array_filter($this->reactions, static fn (Reaction $reaction): bool => \in_array($reaction->content, $contents, true));
    }
}
