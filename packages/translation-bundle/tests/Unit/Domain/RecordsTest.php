<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\TranslationBundle\Domain\Exception\InvalidRecord;
use Phil\TranslationBundle\Domain\Exception\TranslationBundleException;
use Phil\TranslationBundle\Domain\Model\CommentRecord;
use Phil\TranslationBundle\Domain\Model\MemoryRecord;
use Phil\TranslationBundle\Domain\Model\SegmentRecord;
use Phil\TranslationBundle\Domain\Model\TermRecord;
use Phil\TranslationMemory\Check\Flags;
use Phil\TranslationMemory\Glossary\Term;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Memory\MemoryUnit;
use Phil\TranslationMemory\Segment;
use Phil\TranslationMemory\SegmentState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SegmentRecord::class)]
#[CoversClass(MemoryRecord::class)]
#[CoversClass(TermRecord::class)]
#[CoversClass(CommentRecord::class)]
#[CoversClass(InvalidRecord::class)]
final class RecordsTest extends TestCase
{
    public function testASegmentRowKeepsEveryValueOfItsSegment(): void
    {
        $now = new DateTimeImmutable('2026-11-06 10:00:00 UTC');
        $segment = new Segment('b1/text', 'Hello')->translate('Bonjour')->withSource('Hello!');
        $record = SegmentRecord::of('page:1', LanguageTag::of('en'), LanguageTag::of('fr-ca'), 3, $segment, $now);

        self::assertSame(['page:1', 'b1/text', 3, 'en', 'fr-CA', $segment->fingerprint->value, $now], [$record->document(), $record->key(), $record->position(), $record->sourceLanguage()->value, $record->targetLanguage()->value, $record->fingerprint(), $record->updatedAt()]);
        self::assertEquals($segment, $record->segment());
        self::assertSame(SegmentState::Stale, $record->segment()->state);
    }

    public function testASegmentRowTakesTheNewStateOfItsSegment(): void
    {
        $record = SegmentRecord::of('page:1', LanguageTag::of('en'), LanguageTag::of('fr'), 0, new Segment('k', 'Hello'), new DateTimeImmutable('@1'));
        $later = new DateTimeImmutable('@2');
        $segment = new Segment('k', 'Hello you')->translate('Salut')->review();

        $record->update($segment, 4, $later);

        self::assertEquals($segment, $record->segment());
        self::assertSame([4, $segment->fingerprint->value, $later], [$record->position(), $record->fingerprint(), $record->updatedAt()]);
    }

    public function testASegmentRowRefusesAnotherKey(): void
    {
        $record = SegmentRecord::of('page:1', LanguageTag::of('en'), LanguageTag::of('fr'), 0, new Segment('a', 'Hello'), new DateTimeImmutable('@1'));

        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains('The row of segment "a" cannot take segment "b".');

        $record->update(new Segment('b', 'Hello'), 0, new DateTimeImmutable('@2'));
    }

    public function testASegmentRowRefusesADocumentOutOfBounds(): void
    {
        self::assertSame(255, \strlen(SegmentRecord::of(str_repeat('d', 255), LanguageTag::of('en'), LanguageTag::of('fr'), 0, new Segment('k', 'x'), new DateTimeImmutable('@1'))->document()));

        foreach (['', str_repeat('d', 256)] as $document) {
            try {
                SegmentRecord::of($document, LanguageTag::of('en'), LanguageTag::of('fr'), 0, new Segment('k', 'x'), new DateTimeImmutable('@1'));
                self::fail('The document was accepted.');
            } catch (InvalidRecord $invalidRecord) {
                self::assertInstanceOf(TranslationBundleException::class, $invalidRecord);
                self::assertSame('A document is referred to by 1 to 255 bytes.', $invalidRecord->getMessage());
            }
        }
    }

    public function testAMemoryRowKeepsItsUnitAndTakesANewerTranslation(): void
    {
        $unit = new MemoryUnit(LanguageTag::of('en'), LanguageTag::of('fr'), '<b>Été</b> chaud', 'Hot summer', 'page:1');
        $record = MemoryRecord::of($unit, new DateTimeImmutable('@1'));

        self::assertEquals($unit, $record->unit());
        self::assertSame([$unit->fingerprint->value, 9, 1], [$record->fingerprint(), $record->length(), $record->updatedAt()->getTimestamp()]);

        $record->replace(new MemoryUnit(LanguageTag::of('en'), LanguageTag::of('fr'), '<b>Été</b> chaud', 'Summer heat'), new DateTimeImmutable('@2'));

        self::assertSame(['Summer heat', null, 2], [$record->unit()->target, $record->unit()->origin, $record->updatedAt()->getTimestamp()]);
    }

    public function testATermRowKeepsItsTermAndTakesItsNewTranslations(): void
    {
        $term = new Term('hit points', ['points de vie'], ['vie'], true, 'Always plural.');
        $record = TermRecord::of(LanguageTag::of('en'), LanguageTag::of('fr'), $term);

        self::assertEquals($term, $record->term());
        self::assertSame(['hit points', 'en', 'fr'], [$record->source(), $record->sourceLanguage()->value, $record->targetLanguage()->value]);

        $changed = new Term('hit points', ['PV']);
        $record->change($changed);

        self::assertEquals($changed, $record->term());
    }

    public function testATermRowRefusesAnotherTerm(): void
    {
        $record = TermRecord::of(LanguageTag::of('en'), LanguageTag::of('fr'), new Term('a'));

        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains('The row of term "a" cannot take term "b".');

        $record->change(new Term('b'));
    }

    public function testATermRowRefusesATermTooLong(): void
    {
        self::assertSame(255, \strlen(TermRecord::of(LanguageTag::of('en'), LanguageTag::of('fr'), new Term(str_repeat('t', 255)))->source()));

        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains('A glossary term is at most 255 bytes.');

        TermRecord::of(LanguageTag::of('en'), LanguageTag::of('fr'), new Term(str_repeat('t', 256)));
    }

    public function testASegmentRowSaysWhoWroteAndReviewedItsTarget(): void
    {
        $record = SegmentRecord::of('page:1', LanguageTag::of('en'), LanguageTag::of('fr'), 0, new Segment('a', 'One'), new DateTimeImmutable('@0'));

        self::assertSame([null, null, false, []], [$record->translatedBy(), $record->reviewedBy(), $record->isMachine(), $record->flags()->all()]);

        $record->attribute(str_repeat('p', 255), 'b', true);
        self::assertSame([str_repeat('p', 255), 'b', true], [$record->translatedBy(), $record->reviewedBy(), $record->isMachine()]);

        foreach ([['', null], [null, ''], [str_repeat('p', 256), null], [null, str_repeat('p', 256)]] as [$translatedBy, $reviewedBy]) {
            try {
                $record->attribute($translatedBy, $reviewedBy, false);
                self::fail('A person out of bounds is refused.');
            } catch (InvalidRecord $invalidRecord) {
                self::assertSame('A person is referred to by 1 to 255 bytes.', $invalidRecord->getMessage());
            }
        }

        self::assertSame([str_repeat('p', 255), 'b', true], [$record->translatedBy(), $record->reviewedBy(), $record->isMachine()]);
    }

    public function testASegmentRowKeepsFlagsUpTo1024Bytes(): void
    {
        $record = SegmentRecord::of('page:1', LanguageTag::of('en'), LanguageTag::of('fr'), 0, new Segment('a', 'One'), new DateTimeImmutable('@0'));
        $long = array_map(static fn (int $index): string => \sprintf('f%02d', $index).str_repeat('x', 29).':'.str_repeat('1', 32), range(1, 15));

        $record->flag(new Flags([...$long, 'last'.str_repeat('z', 15)]));
        self::assertSame(1024, \strlen($record->flags()->toString()));
        self::assertSame('last'.str_repeat('z', 15), $record->flags()->all()[15]);

        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains('The flags of a segment are at most 1024 bytes.');
        $record->flag(new Flags([...$long, 'last'.str_repeat('z', 16)]));
    }

    public function testACommentIsOneToFourThousandCharactersOfASegment(): void
    {
        $now = new DateTimeImmutable('@0');
        $comment = CommentRecord::of('page:1', LanguageTag::of('fr'), 'a', null, str_repeat('é', 4000), $now);

        self::assertSame([null, 'page:1', 'fr', 'a', null, 4000, 0], [$comment->id(), $comment->document(), $comment->targetLanguage()->value, $comment->key(), $comment->author(), mb_strlen($comment->body()), $comment->createdAt()->getTimestamp()]);
        $comment->number(7);
        $comment->number(8);
        self::assertSame(7, $comment->id());
        self::assertSame(str_repeat('d', 255), CommentRecord::of(str_repeat('d', 255), LanguageTag::of('fr'), str_repeat('k', 255), str_repeat('p', 255), 'x', $now)->document());

        foreach ([
            ['', 'a', null, 'x', 'A comment belongs to a segment of a document, each referred to by 1 to 255 bytes.'],
            [str_repeat('d', 256), 'a', null, 'x', 'A comment belongs to a segment of a document, each referred to by 1 to 255 bytes.'],
            ['page:1', '', null, 'x', 'A comment belongs to a segment of a document, each referred to by 1 to 255 bytes.'],
            ['page:1', str_repeat('k', 256), null, 'x', 'A comment belongs to a segment of a document, each referred to by 1 to 255 bytes.'],
            ['page:1', 'a', '', 'x', 'A person is referred to by 1 to 255 bytes.'],
            ['page:1', 'a', str_repeat('p', 256), 'x', 'A person is referred to by 1 to 255 bytes.'],
            ['page:1', 'a', null, "  \n ", 'A comment is 1 to 4000 characters of UTF-8.'],
            ['page:1', 'a', null, str_repeat('é', 4001), 'A comment is 1 to 4000 characters of UTF-8.'],
            ['page:1', 'a', null, "\xFF", 'A comment is 1 to 4000 characters of UTF-8.'],
        ] as [$document, $key, $author, $body, $message]) {
            try {
                CommentRecord::of($document, LanguageTag::of('fr'), $key, $author, $body, $now);
                self::fail($message);
            } catch (InvalidRecord $invalidRecord) {
                self::assertSame($message, $invalidRecord->getMessage());
            }
        }
    }
}
