<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Application;

use Phil\EmbedBundle\Domain\Exception\InvalidEmbed;

/**
 * The embeds the configuration declares, by name.
 */
final readonly class Embeds
{
    /** @var array<string, Embed> */
    private array $embeds;

    /**
     * @param iterable<Embed> $embeds
     *
     * @throws InvalidEmbed when two embeds have the same name
     */
    public function __construct(iterable $embeds = [])
    {
        $byName = [];

        foreach ($embeds as $embed) {
            if (isset($byName[$embed->name])) {
                throw InvalidEmbed::because(\sprintf('Two embeds are named "%s".', $embed->name));
            }

            $byName[$embed->name] = $embed;
        }

        $this->embeds = $byName;
    }

    /**
     * @throws InvalidEmbed when no embed has that name
     */
    public function get(string $name): Embed
    {
        return $this->embeds[$name] ?? throw InvalidEmbed::because(\sprintf('No embed is named "%s".', $name));
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->embeds);
    }
}
