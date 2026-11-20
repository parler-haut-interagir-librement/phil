<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\AiBundle\Domain\Journal\Decision;
use Phil\AiBundle\Domain\Journal\KeySource;
use Phil\AiBundle\Domain\Journal\Output;
use Phil\AiBundle\Domain\Model\JournalEntry;
use Phil\AiBundle\Domain\ValueObject\Cost;
use Phil\AiBundle\Domain\ValueObject\Subject;
use Phil\AiBundle\Domain\ValueObject\TaskKind;
use Phil\AiBundle\Domain\ValueObject\Tokens;
use Phil\AiBundle\Infrastructure\Memory\InMemoryJournalRepository;
use Phil\AiBundle\Infrastructure\Privacy\AiJournalPrivacy;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AiJournalPrivacy::class)]
final class AiJournalPrivacyTest extends TestCase
{
    public function testTheExportListsEachTaskWithoutContent(): void
    {
        $journal = new InMemoryJournalRepository();
        $kind = TaskKind::fromString('draft.rewrite');
        $decided = JournalEntry::succeeded('t-1', $kind, Subject::fromString('member-1'), 'main', 'model-a', KeySource::User, new Tokens(12, 5), new Cost(111, 'USD'), new DateTimeImmutable('1970-01-01 00:00:00 UTC'), 250);
        $decided->decide(Decision::Edited, new DateTimeImmutable('2026-11-06 10:05:00 UTC'));

        $journal->save($decided);
        $journal->save(JournalEntry::failed('t-2', TaskKind::fromString('cover.draw'), Subject::fromString('member-1'), 'images', 'model-b', KeySource::Application, 'USD', new DateTimeImmutable('9999-12-31 23:59:58 UTC'), 30, Output::Image));
        $journal->save(JournalEntry::failed('t-3', $kind, Subject::fromString('member-2'), 'main', 'model-a', KeySource::Application, 'USD', new DateTimeImmutable('2026-11-06 10:00:00 UTC'), 30));

        $privacy = new AiJournalPrivacy($journal);

        self::assertSame('ai', $privacy->module()->value);
        self::assertSame(['tasks' => [
            [
                'id' => 't-1', 'kind' => 'draft.rewrite', 'output' => 'text', 'platform' => 'main', 'model' => 'model-a', 'key' => 'user', 'outcome' => 'succeeded',
                'input_tokens' => 12, 'output_tokens' => 5, 'cost_micros' => 111, 'currency' => 'USD', 'started_at' => '1970-01-01T00:00:00+00:00', 'duration_ms' => 250,
                'decision' => 'edited', 'decided_at' => '2026-11-06T10:05:00+00:00',
            ],
            [
                'id' => 't-2', 'kind' => 'cover.draw', 'output' => 'image', 'platform' => 'images', 'model' => 'model-b', 'key' => 'application', 'outcome' => 'failed',
                'input_tokens' => 0, 'output_tokens' => 0, 'cost_micros' => 0, 'currency' => 'USD', 'started_at' => '9999-12-31T23:59:58+00:00', 'duration_ms' => 30,
                'decision' => 'pending', 'decided_at' => null,
            ],
        ]], $privacy->export(DataSubject::fromString('member-1')));
        self::assertSame([], $privacy->export(DataSubject::fromString('member-3')));
    }

    public function testTheErasureDeletesTheSubjectsEntriesAndCanBeReplayed(): void
    {
        $journal = new InMemoryJournalRepository();
        $journal->save(JournalEntry::failed('t-1', TaskKind::fromString('k'), Subject::fromString('member-1'), 'main', 'm', KeySource::Application, 'USD', new DateTimeImmutable('2026-11-06 10:00:00 UTC'), 30));
        $journal->save(JournalEntry::failed('t-2', TaskKind::fromString('k'), Subject::fromString('member-2'), 'main', 'm', KeySource::Application, 'USD', new DateTimeImmutable('2026-11-06 10:00:00 UTC'), 30));

        $privacy = new AiJournalPrivacy($journal);

        foreach ([1, 2] as $run) {
            $report = $privacy->erase(DataSubject::fromString('member-1'));

            self::assertSame(['ai', [JournalEntry::TABLE => ErasureEffect::Deleted]], [$report->module->value, $report->effects], \sprintf('Run %d', $run));
        }

        self::assertSame([null, 't-2'], [$journal->get('t-1'), $journal->get('t-2')?->id()]);
    }
}
