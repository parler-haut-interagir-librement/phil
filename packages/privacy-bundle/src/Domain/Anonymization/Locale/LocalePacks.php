<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Anonymization\Locale;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * The locale packs by code: the built-in ones, and those an application adds or replaces.
 */
final readonly class LocalePacks
{
    /**
     * @param array<string, LocalePack> $packs
     */
    private function __construct(
        private array $packs,
    ) {
    }

    public static function builtIn(): self
    {
        return new self([])->with(BuiltInLocales::all());
    }

    /**
     * These packs added, each replacing a pack of the same code.
     *
     * @param iterable<LocalePack> $packs
     */
    public function with(iterable $packs): self
    {
        $all = $this->packs;

        foreach ($packs as $pack) {
            $all[$pack->code] = $pack;
        }

        return new self($all);
    }

    public function get(string $code): LocalePack
    {
        return $this->packs[$code] ?? throw InvalidPrivacyValue::because(\sprintf('No locale pack answers to "%s"; the packs are %s.', $code, implode(', ', $this->codes())));
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_keys($this->packs);
    }
}
