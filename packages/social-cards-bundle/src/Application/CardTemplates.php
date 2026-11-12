<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Application;

use Phil\SocialCards\Card\CardTemplate;
use Phil\SocialCardsBundle\Application\Exception\UnknownCardTemplate;

/**
 * The card templates of the configuration, by name, each read from its fonts and images the first time it is used and
 * kept for the life of the process: a template is the same for every request, so a worker reads its files once.
 */
final class CardTemplates
{
    /** @var array<string, CardTemplate> */
    private array $built = [];

    /**
     * @param array<string, array<string, mixed>> $templates the configuration of each template, as `CardTemplate::fromArray()` reads it
     */
    public function __construct(
        private readonly array $templates,
        private readonly string $default,
    ) {
    }

    /**
     * The template of that name, or the default one.
     */
    public function get(?string $name = null): CardTemplate
    {
        $name ??= $this->default;

        if (!\array_key_exists($name, $this->templates)) {
            throw UnknownCardTemplate::named($name, array_keys($this->templates));
        }

        return $this->built[$name] ??= CardTemplate::fromArray($this->templates[$name]);
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->templates);
    }

    public function default(): string
    {
        return $this->default;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->templates);
    }
}
