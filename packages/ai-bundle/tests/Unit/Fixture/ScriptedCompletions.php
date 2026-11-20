<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Unit\Fixture;

use Generator;
use LogicException;
use Override;
use Phil\AiBundle\Application\Completion;
use Phil\AiBundle\Application\ModelCall;
use Phil\AiBundle\Application\Port\Completions;
use SensitiveParameter;
use Symfony\Component\Clock\MockClock;
use Throwable;

/**
 * A model that answers what it was told to, or fails, after the time it was told to take, and remembers each call.
 */
final class ScriptedCompletions implements Completions
{
    /** @var list<array{ModelCall, ?string}> */
    public array $calls = [];

    public function __construct(
        private readonly ?Completion $answer,
        private readonly ?Throwable $failure = null,
        private readonly ?MockClock $clock = null,
        private readonly float $seconds = 0.0,
        /** @var list<string> the pieces a stream yields before it ends, or fails */
        private readonly array $pieces = [],
    ) {
    }

    #[Override]
    public function complete(ModelCall $call, #[SensitiveParameter] ?string $userKey = null): Completion
    {
        $this->calls[] = [$call, $userKey];
        $this->clock?->sleep($this->seconds);

        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        return $this->answer ?? throw new LogicException('No answer scripted.');
    }

    #[Override]
    public function stream(ModelCall $call, #[SensitiveParameter] ?string $userKey = null): Generator
    {
        $this->calls[] = [$call, $userKey];

        foreach ($this->pieces as $piece) {
            yield $piece;
        }

        $this->clock?->sleep($this->seconds);

        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        return $this->answer ?? throw new LogicException('No answer scripted.');
    }
}
