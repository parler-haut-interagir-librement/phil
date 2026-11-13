<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Tests\Unit\Message;

use Phil\CommunityNotes\CommunityNotes;
use Phil\CommunityNotes\CrossAgreementRule;
use Phil\CommunityNotes\DecisionReason;
use Phil\CommunityNotes\Memory\InMemoryNoteRepository;
use Phil\CommunityNotes\Memory\InMemoryRatingRepository;
use Phil\CommunityNotes\Message\ReconsiderNote;
use Phil\CommunityNotes\Message\ReconsiderNoteHandler;
use Phil\CommunityNotes\NoteStatus;
use Phil\CommunityNotes\Tests\Unit\Fixture\Members;
use Phil\CommunityNotes\Verdict;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(ReconsiderNote::class)]
#[CoversClass(ReconsiderNoteHandler::class)]
final class ReconsiderNoteHandlerTest extends TestCase
{
    public function testTheNoteNamedIsDecidedAgainUnderTheRuleOfTheHandler(): void
    {
        $notes = new InMemoryNoteRepository();
        $ratings = new InMemoryRatingRepository();
        $clock = new MockClock('2026-11-12 10:00:00 UTC');
        $strict = new CommunityNotes($notes, $ratings, new Members(), $clock);
        $note = $strict->write(ResourceReference::of('post', '1'), ResourceReference::of('user', 'zoe'), 'Context.');
        $strict->rate($note->id, ResourceReference::of('user', 'ann'), Verdict::Helpful);
        $lenient = new CommunityNotes($notes, $ratings, new Members(), $clock, new CrossAgreementRule(minRatings: 1, share: [1, 1], pairs: 1, minShared: 1, maxRaters: 2));
        $handler = new ReconsiderNoteHandler($lenient);
        self::assertSame(DecisionReason::TooFewRatings, $lenient->get($note->id)->decision?->reason);

        $handler(new ReconsiderNote($note->id));
        $handler(new ReconsiderNote('erased'));

        $after = $lenient->get($note->id);
        self::assertSame([NoteStatus::NeedsMoreRatings, DecisionReason::NoCrossAgreement], [$after->status, $after->decision?->reason]);
        self::assertSame('erased', new ReconsiderNote('erased')->note);
        self::assertNull($lenient->find('erased'));
    }
}
