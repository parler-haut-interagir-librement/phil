<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Application;

use Phil\VirtualClassroom\Contract\VirtualClassroom;
use Phil\VirtualClassroomBundle\Domain\Exception\UnknownProvider;
use Psr\Container\ContainerInterface;

/**
 * The conferencing servers of the configuration, by name, and the one a room is scheduled on when none is named.
 * Each is built when first asked for.
 */
final readonly class Providers
{
    /**
     * @param list<string> $names
     */
    public function __construct(
        private ContainerInterface $providers,
        private array $names,
        private ?string $default,
    ) {
    }

    /**
     * @throws UnknownProvider when no server has this name
     */
    public function get(string $name): VirtualClassroom
    {
        $provider = \in_array($name, $this->names, true) ? $this->providers->get($name) : null;

        if (!$provider instanceof VirtualClassroom) {
            throw UnknownProvider::of($name);
        }

        return $provider;
    }

    public function has(string $name): bool
    {
        return \in_array($name, $this->names, true);
    }

    /**
     * @throws UnknownProvider when no server is configured
     */
    public function defaultName(): string
    {
        return $this->default ?? throw UnknownProvider::of('(default)');
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return $this->names;
    }
}
