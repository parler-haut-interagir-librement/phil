<?php

declare(strict_types=1);

namespace Phil\BotDetectionBundle\Tests\Unit\Application\Fixture;

use Phil\BotDetectionBundle\Domain\Counter\RobotVerdictCounter;

final class InMemoryRobotVerdictCounter implements RobotVerdictCounter
{
    public int $count = 0;

    public function increment(): void
    {
        ++$this->count;
    }
}
