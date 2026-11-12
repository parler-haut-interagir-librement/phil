<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\TranslationBundle\Domain\Model\CommentRecord;
use Phil\TranslationBundle\Domain\Model\SegmentRecord;
use Phil\TranslationBundle\Domain\Model\TermRecord;
use Phil\TranslationBundle\Domain\Repository\Contract\CommentRepository;
use Phil\TranslationBundle\Domain\Repository\SegmentRepository;
use Phil\TranslationBundle\Domain\Repository\TermRepository;
use Phil\TranslationMemory\Check\Flags;
use Phil\TranslationMemory\Glossary\Term;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Memory\MemoryStore;
use Phil\TranslationMemory\Memory\MemoryUnit;
use Phil\TranslationMemory\Segment;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * What every store of the bundle must do, whatever keeps the rows.
 */
abstract class RepositoryTestCase extends TestCase
{
    public function testKeepsTheSegmentsOfADocumentByPosition(): void
    {
        [$segments] = $this->stores();
        $now = new DateTimeImmutable('2026-11-06 10:00:00 UTC');
        $second = SegmentRecord::of('page:1', LanguageTag::of('en'), LanguageTag::of('fr'), 1, new Segment('b', 'Two')->translate('Deux'), $now);
        $first = SegmentRecord::of('page:1', LanguageTag::of('en'), LanguageTag::of('fr'), 0, new Segment('a', 'One'), $now);
        $last = SegmentRecord::of('page:1', LanguageTag::of('en'), LanguageTag::of('fr'), 2, new Segment('0', 'Three'), $now);
        $german = SegmentRecord::of('page:1', LanguageTag::of('en'), LanguageTag::of('de'), 0, new Segment('a', 'One'), $now);
        $other = SegmentRecord::of('page:2', LanguageTag::of('en'), LanguageTag::of('fr'), 0, new Segment('a', 'One'), $now);
        $segments->save($second, $first, $german, $other, $last);
        $this->clear();

        $read = $segments->forDocument('page:1', LanguageTag::of('fr'));
        self::assertSame([['a', 0, 'untranslated'], ['b', 1, 'translated'], ['0', 2, 'untranslated']], array_map(static fn (SegmentRecord $record): array => [$record->key(), $record->position(), $record->segment()->state->value], $read));
        self::assertSame('Deux', $read[1]->segment()->target);
        self::assertSame($now->getTimestamp(), $read[1]->updatedAt()->getTimestamp());

        $read[0]->update(new Segment('a', 'One')->translate('Un'), 2, $now);
        $segments->save($read[0]);
        $segments->remove($read[1], $read[2]);
        $this->clear();

        self::assertSame([['a', 2, 'Un']], array_map(static fn (SegmentRecord $record): array => [$record->key(), $record->position(), $record->segment()->target], $segments->forDocument('page:1', LanguageTag::of('fr'))));
        self::assertCount(1, $segments->forDocument('page:1', LanguageTag::of('de')));
        self::assertCount(1, $segments->forDocument('page:2', LanguageTag::of('fr')));

        $segments->remove();
        $segments->save();
        self::assertCount(1, $segments->forDocument('page:1', LanguageTag::of('fr')));
    }

    public function testKeepsWhoWroteAndReviewedATargetAndItsFlags(): void
    {
        [$segments] = $this->stores();
        $record = SegmentRecord::of('page:1', LanguageTag::of('en'), LanguageTag::of('fr'), 0, new Segment('a', 'One')->translate('Un'), new DateTimeImmutable('2026-11-06 10:00:00 UTC'));
        $record->attribute('user:1', 'user:2', true);
        $record->flag(new Flags(['ignore-same', 'max-size:200']));

        $segments->save($record);
        $this->clear();

        $read = $segments->forDocument('page:1', LanguageTag::of('fr'))[0];
        self::assertSame(['user:1', 'user:2', true, ['ignore-same', 'max-size:200']], [$read->translatedBy(), $read->reviewedBy(), $read->isMachine(), $read->flags()->all()]);
    }

    public function testKeepsTheCommentsOfADocument(): void
    {
        [, , , $comments] = $this->stores();
        $now = new DateTimeImmutable('2026-11-06 10:00:00 UTC');
        $first = CommentRecord::of('page:1', LanguageTag::of('fr'), 'a', 'user:1', 'Why “vous”?', $now);
        $second = CommentRecord::of('page:1', LanguageTag::of('fr'), 'b', null, 'Fine.', $now);
        $comments->save($first);
        $comments->save($second);
        $comments->save(CommentRecord::of('page:1', LanguageTag::of('de'), 'a', null, 'Gut.', $now));
        $comments->save(CommentRecord::of('page:2', LanguageTag::of('fr'), 'a', null, 'Autre.', $now));
        $this->clear();

        $read = $comments->forDocument('page:1', LanguageTag::of('fr'));
        self::assertSame([['a', 'user:1', 'Why “vous”?'], ['b', null, 'Fine.']], array_map(static fn (CommentRecord $record): array => [$record->key(), $record->author(), $record->body()], $read));
        self::assertSame($now->getTimestamp(), $read[0]->createdAt()->getTimestamp());
        self::assertNotNull($first->id());
        self::assertSame('Fine.', $comments->get((int) $second->id())?->body());
        self::assertNull($comments->get(999));

        $comments->remove($read[0]);
        $this->clear();
        self::assertSame(['b'], array_map(static fn (CommentRecord $record): string => $record->key(), $comments->forDocument('page:1', LanguageTag::of('fr'))));
    }

    public function testNumbersTheCommentsFromOneAndReadsThemInTheOrderTheyWereWritten(): void
    {
        [, , , $comments] = $this->stores();
        $now = new DateTimeImmutable('2026-11-06 10:00:00 UTC');
        $first = CommentRecord::of('page:1', LanguageTag::of('fr'), 'z', null, 'First.', $now);
        $second = CommentRecord::of('page:1', LanguageTag::of('fr'), 'a', null, 'Second.', $now);
        $comments->save($first);
        $comments->save($second);
        $this->clear();

        self::assertSame([1, 2], [$first->id(), $second->id()]);
        self::assertSame(['z', 'a'], array_map(static fn (CommentRecord $record): string => $record->key(), $comments->forDocument('page:1', LanguageTag::of('fr'))));
    }

    public function testRemovingACommentThatWasNeverSavedChangesNothing(): void
    {
        [, , , $comments] = $this->stores();
        $now = new DateTimeImmutable('2026-11-06 10:00:00 UTC');
        $comments->save(CommentRecord::of('page:1', LanguageTag::of('fr'), 'a', null, 'Kept.', $now));

        try {
            $comments->remove(CommentRecord::of('page:1', LanguageTag::of('fr'), 'b', null, 'Never saved.', $now));
        } catch (Throwable) {
            // A store that writes to a database may refuse a row it does not know; what stays is what counts.
        }

        $this->clear();
        self::assertSame(['a'], array_map(static fn (CommentRecord $record): string => $record->key(), $comments->forDocument('page:1', LanguageTag::of('fr'))));
    }

    public function testKeepsTheTermsOfAPairOfLanguages(): void
    {
        [, $terms] = $this->stores();
        $terms->save(TermRecord::of(LanguageTag::of('en'), LanguageTag::of('fr'), new Term('hit points', ['points de vie'], ['vie'], true, 'Plural.', ['hit point'], ['point de vie'])));
        $terms->save(TermRecord::of(LanguageTag::of('en'), LanguageTag::of('fr'), new Term('dragon', ['dragon'])));
        $terms->save(TermRecord::of(LanguageTag::of('en'), LanguageTag::of('de'), new Term('dragon', ['Drache'])));
        $this->clear();

        $terms->save(TermRecord::of(LanguageTag::of('de'), LanguageTag::of('fr'), new Term('Drache', ['dragon'])));
        $this->clear();
        $sources = array_map(static fn (TermRecord $record): string => $record->source(), $terms->forLanguages(LanguageTag::of('en'), LanguageTag::of('fr')));
        sort($sources);
        self::assertSame(['dragon', 'hit points'], $sources);

        $record = $terms->get(LanguageTag::of('en'), LanguageTag::of('fr'), 'hit points');
        self::assertInstanceOf(TermRecord::class, $record);
        self::assertEquals(new Term('hit points', ['points de vie'], ['vie'], true, 'Plural.', ['hit point'], ['point de vie']), $record->term());
        self::assertNull($terms->get(LanguageTag::of('en'), LanguageTag::of('de'), 'hit points'));

        $record->change(new Term('hit points', ['PV'], forms: ['HP']));
        $terms->save($record);
        $this->clear();
        self::assertEquals(new Term('hit points', ['PV'], forms: ['HP']), $terms->get(LanguageTag::of('en'), LanguageTag::of('fr'), 'hit points')?->term());

        $dragon = $terms->get(LanguageTag::of('en'), LanguageTag::of('fr'), 'dragon');
        self::assertInstanceOf(TermRecord::class, $dragon);
        $terms->remove($dragon);
        $this->clear();
        self::assertSame(['hit points'], array_map(static fn (TermRecord $record): string => $record->source(), $terms->forLanguages(LanguageTag::of('en'), LanguageTag::of('fr'))));
        self::assertCount(1, $terms->forLanguages(LanguageTag::of('en'), LanguageTag::of('de')));
    }

    public function testKeepsTheMemoryOneUnitPerSource(): void
    {
        [, , $memory] = $this->stores();
        $en = LanguageTag::of('en');
        $fr = LanguageTag::of('fr');
        $unit = new MemoryUnit($en, $fr, 'Hello', 'Bonjour', 'page:1');
        $memory->add($unit);
        $memory->add(new MemoryUnit($en, $fr, 'Hello there', 'Salut'));
        $memory->add(new MemoryUnit($en, LanguageTag::of('de'), 'Hello', 'Hallo'));
        $memory->add(new MemoryUnit(LanguageTag::of('de'), $fr, 'Hello', 'Allô'));
        $this->clear();

        self::assertSame(['Bonjour'], $this->targets($memory->exact($en, $fr, $unit->fingerprint)));
        self::assertSame([], $this->targets($memory->exact($en, $fr, new MemoryUnit($en, $fr, 'Bye', 'x')->fingerprint)));
        self::assertSame(['Bonjour', 'Salut'], $this->targets($memory->candidates($en, $fr, 5, 11)));
        self::assertSame(['Bonjour'], $this->targets($memory->candidates($en, $fr, 5, 10)));
        self::assertSame(['Salut'], $this->targets($memory->candidates($en, $fr, 6, 11)));

        $memory->add(new MemoryUnit($en, $fr, 'Hello', 'Allô'));
        $this->clear();
        $units = iterator_to_array($memory->exact($en, $fr, $unit->fingerprint), false);
        self::assertSame([['Allô', null]], array_map(static fn (MemoryUnit $unit): array => [$unit->target, $unit->origin], $units));
        self::assertCount(2, iterator_to_array($memory->candidates($en, $fr, 0, 100), false));
    }

    /**
     * @return array{SegmentRepository, TermRepository, MemoryStore, CommentRepository}
     */
    abstract protected function stores(): array;

    protected function clear(): void
    {
    }

    /**
     * @param iterable<MemoryUnit> $units
     *
     * @return list<string>
     */
    private function targets(iterable $units): array
    {
        $targets = [];

        foreach ($units as $unit) {
            $targets[] = $unit->target;
        }

        sort($targets);

        return $targets;
    }
}
