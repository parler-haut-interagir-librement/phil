<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Infrastructure\Console;

use Closure;
use Phil\ConsoleBundle\Domain\Attribute\ConfirmEnvironment;
use Phil\ConsoleBundle\Domain\Attribute\ForbidEnvironment;
use Phil\ConsoleBundle\Domain\ValueObject\EnvironmentGuards;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;

/**
 * The guard attributes of a command, read on the class that holds its code and on the method it runs.
 *
 * A lazy or traced command is unwrapped first. The code of an invokable command — a service with `__invoke()`, a
 * method-level command, a command class with `__invoke()` — is the method read with its class; a classic command is its
 * own class. Nothing is kept between two calls.
 */
final readonly class CommandGuardReader
{
    /**
     * The short name of the command FrameworkBundle wraps a command in under `--profile`, a class of the console
     * component internal to Symfony, whose public `command` is the wrapped one.
     */
    private const string TRACED = 'TraceableCommand';

    public function guardsOf(Command $command): EnvironmentGuards
    {
        [$class, $method] = $this->target($command);
        $attributes = [];

        foreach ([ForbidEnvironment::class, ConfirmEnvironment::class] as $name) {
            foreach ($class->getAttributes($name) as $attribute) {
                $attributes[] = $attribute;
            }

            foreach ($method?->getAttributes($name) ?? [] as $attribute) {
                $attributes[] = $attribute;
            }
        }

        return EnvironmentGuards::of(array_map(
            static fn (ReflectionAttribute $attribute): ForbidEnvironment|ConfirmEnvironment => $attribute->newInstance(),
            $attributes,
        ));
    }

    /**
     * @return array{ReflectionClass<object>, ReflectionMethod|null}
     */
    private function target(Command $command): array
    {
        while (true) {
            if ($command instanceof LazyCommand) {
                $command = $command->getCommand();

                continue;
            }

            $traced = $this->isTraced($command) ? get_object_vars($command)['command'] ?? null : null;

            if (!$traced instanceof Command) {
                break;
            }

            $command = $traced;
        }

        $code = $command->getCode();

        if ($code instanceof Closure) {
            $function = new ReflectionFunction($code);
            $holder = $function->getClosureThis();

            if (null !== $holder && method_exists($holder, $function->getName())) {
                return [new ReflectionClass($holder), new ReflectionMethod($holder, $function->getName())];
            }
        } elseif (\is_object($code)) {
            return [new ReflectionClass($code), new ReflectionMethod($code, '__invoke')];
        } elseif (\is_array($code)) {
            return [new ReflectionClass($code[0]), new ReflectionMethod($code[0], $code[1])];
        }

        return [new ReflectionClass($command), null];
    }

    private function isTraced(Command $command): bool
    {
        $class = new ReflectionClass($command);

        return self::TRACED === $class->getShortName() && new ReflectionClass(Command::class)->getNamespaceName() === $class->getNamespaceName();
    }
}
