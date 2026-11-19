<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\AiBundle\Domain\Exception\AlreadyDecided;
use Phil\AiBundle\Domain\Journal\Decision;
use Phil\AiBundle\Domain\Journal\KeySource;
use Phil\AiBundle\Domain\Journal\Outcome;
use Phil\AiBundle\Domain\Model\JournalEntry;
use Phil\AiBundle\Domain\ValueObject\Cost;
use Phil\AiBundle\Domain\ValueObject\Subject;
use Phil\AiBundle\Domain\ValueObject\TaskKind;
use Phil\AiBundle\Domain\ValueObject\Tokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JournalEntry::class)]
#[CoversClass(AlreadyDecided::class)]
final class JournalEntryTest extends TestCase
{
    public function testATaskThatProposedSomething(): void
    {
        $startedAt = new DateTimeImmutable('2026-11-06 10:00:00 UTC');
        $entry = JournalEntry::succeeded('t-1', TaskKind::fromString('draft.rewrite'), Subject::fromString('project-1'), 'main', 'model-a', KeySource::User, new Tokens(12, 5), new Cost(111, 'USD'), $startedAt, 250);

        self::assertSame('t-1', $entry->id());
        self::assertSame('draft.rewrite', $entry->kind()->value);
        self::assertSame('project-1', $entry->subject()->value);
        self::assertSame(['main', 'model-a'], [$entry->platform(), $entry->model()]);
        self::assertSame(KeySource::User, $entry->keySource());
        self::assertSame(Outcome::Succeeded, $entry->outcome());
        self::assertEquals(new Tokens(12, 5), $entry->tokens());
        self::assertEquals(new Cost(111, 'USD'), $entry->cost());
        self::assertSame($startedAt, $entry->startedAt());
        self::assertSame(250, $entry->durationMs());
        self::assertSame(Decision::Pending, $entry->decision());
        self::assertNull($entry->decidedAt());

        $decidedAt = new DateTimeImmutable('2026-11-06 10:05:00 UTC');
        $entry->decide(Decision::Edited, $decidedAt);

        self::assertSame(Decision::Edited, $entry->decision());
        self::assertSame($decidedAt, $entry->decidedAt());

        try {
            $entry->decide(Decision::Rejected, $decidedAt);
            self::fail('A suggestion was decided twice.');
        } catch (AlreadyDecided $alreadyDecided) {
            self::assertSame('The suggestion "t-1" is already edited.', $alreadyDecided->getMessage());
            self::assertSame(Decision::Edited, $entry->decision());
        }
    }

    public function testATaskThatFailed(): void
    {
        $entry = JournalEntry::failed('t-2', TaskKind::fromString('draft.rewrite'), Subject::fromString('project-1'), 'main', 'model-a', KeySource::Application, 'EUR', new DateTimeImmutable('2026-11-06 10:00:00 UTC'), 40);

        self::assertSame(Outcome::Failed, $entry->outcome());
        self::assertSame(KeySource::Application, $entry->keySource());
        self::assertEquals(Tokens::none(), $entry->tokens());
        self::assertEquals(new Cost(0, 'EUR'), $entry->cost());
        self::assertSame(40, $entry->durationMs());
        self::assertSame(Decision::Pending, $entry->decision());

        $this->expectExceptionObject(AlreadyDecided::failedTask('t-2'));
        $this->expectExceptionMessageIsOrContains('The task "t-2" failed and proposed nothing to decide on.');
        $entry->decide(Decision::Accepted, new DateTimeImmutable());
    }
}
