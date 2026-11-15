<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Benchmark;

use Phil\ModerationBundle\Domain\Status\DecisionAction;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;
use Phil\ModerationBundle\Domain\ValueObject\TargetReference;
use Phil\ModerationBundle\Tests\Fixture\Stage;

/**
 * What the moderation costs on its two paths, on SQLite in memory with 10,000 reports on 2,000 targets, half of them
 * decided: a page of 25 lines of the queue for a moderator, and the hook a package calls when a member posts.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"moderation"})
 */
final class ModerationBench
{
    private Stage $stage;

    public function __construct()
    {
        $this->setUp();
    }

    public function setUp(): void
    {
        $this->stage = new Stage();
        $this->stage->connection->beginTransaction();

        for ($i = 0; $i < 10_000; ++$i) {
            $this->stage->people->signIn('m-'.$i);
            $this->stage->reports->report(TargetReference::of(0 === $i % 2 ? 'forum.post' : 'comment', (string) ($i % 2_000)), 'spam');
            $this->stage->clock->sleep(1);
        }

        $this->stage->people->signIn('mod-1', $this->stage->moderator);

        for ($i = 0; $i < 1_000; ++$i) {
            $this->stage->decisions->decide(TargetReference::of(0 === $i % 2 ? 'forum.post' : 'comment', (string) $i), DecisionAction::Dismiss);
        }

        $this->stage->connection->commit();
    }

    public function benchQueuePage(): void
    {
        $this->stage->queue->items(25);
        $this->stage->queue->count();
    }

    public function benchSubmission(): void
    {
        $this->stage->submissions->submit(TargetReference::of('comment', 'new'), MemberId::of('m-1'), 'Hello');
    }
}
