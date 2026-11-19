<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application;

use Override;
use Phil\ReactionsBundle\Application\Contract\EmojiImages;
use Phil\ReactionsBundle\Application\Contract\ReactionSource;
use Phil\ReactionsBundle\Domain\Exception\InvalidReaction;
use Phil\ReactionsBundle\Domain\ValueObject\ReactionDefinition;
use Phil\ReactionsBundle\Domain\ValueObject\ReactionGroup;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The reactions a site offers, source by source: the sources it lists first, in that order, then the others. Read once
 * per request, the addresses of all their images asked at once, and forgotten between two requests of a worker. The list is bounded, since every reaction is asked of the
 * access once per content shown.
 */
final class ReactionCatalog implements ResetInterface
{
    public const int MAX_REACTIONS = 128;

    /** @var array<string, ReactionGroup>|null */
    private ?array $groups = null;

    /** @var array<string, ReactionDefinition> */
    private array $definitions = [];

    /**
     * @param iterable<ReactionSource> $sources
     * @param list<string>             $order   the names of the sources shown first
     */
    public function __construct(
        private readonly iterable $sources,
        private readonly EmojiImages $images,
        private readonly array $order = [],
    ) {
    }

    /**
     * @return list<ReactionGroup>
     *
     * @throws InvalidReaction when two reactions share a code, or there are too many of them
     */
    public function groups(): array
    {
        return array_values($this->groups ??= $this->read());
    }

    /**
     * @throws InvalidReaction when two reactions share a code, or there are too many of them
     */
    public function definition(string $code): ?ReactionDefinition
    {
        $this->groups ??= $this->read();

        return $this->definitions[$code] ?? null;
    }

    #[Override]
    public function reset(): void
    {
        $this->groups = null;
        $this->definitions = [];
    }

    /**
     * @return array<string, ReactionGroup>
     */
    private function read(): array
    {
        $declared = [];
        $images = [];

        foreach ($this->sources as $source) {
            $group = $source->group();
            $declared[$group->name] = $group;

            foreach ($group->definitions as $definition) {
                if (null !== $definition->image) {
                    $images[$definition->image] = $definition->image;
                }
            }
        }

        $urls = [] === $images ? [] : $this->images->urls(...array_values($images));
        $groups = [];

        foreach ($declared as $name => $group) {
            $groups[$name] = new ReactionGroup($group->name, $group->label, $this->usable($group, $urls), $group->domain);
        }

        $ordered = [];

        foreach ($this->order as $name) {
            if (isset($groups[$name])) {
                $ordered[$name] = $groups[$name];
            }
        }

        return $ordered + $groups;
    }

    /**
     * @param array<string, string> $urls the address of each image declaration that has one
     *
     * @return list<ReactionDefinition>
     */
    private function usable(ReactionGroup $group, array $urls): array
    {
        $usable = [];

        foreach ($group->definitions as $definition) {
            if (isset($this->definitions[$definition->code])) {
                throw InvalidReaction::because(\sprintf('Two reactions have the code "%s".', $definition->code));
            }

            $url = null === $definition->image ? null : $urls[$definition->image] ?? null;

            if (null !== $definition->image && null === $url) {
                continue;
            }

            $usable[] = $this->definitions[$definition->code] = null === $url ? $definition : $definition->withImageAt($url);

            if (\count($this->definitions) > self::MAX_REACTIONS) {
                throw InvalidReaction::because(\sprintf('A site offers at most %d reactions.', self::MAX_REACTIONS));
            }
        }

        return $usable;
    }
}
