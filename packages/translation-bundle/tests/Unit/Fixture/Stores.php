<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Tests\Unit\Fixture;

use Phil\TranslationBundle\Application\Comments;
use Phil\TranslationBundle\Application\Contract\MachineTranslator;
use Phil\TranslationBundle\Application\Glossaries;
use Phil\TranslationBundle\Application\TranslationPolicy;
use Phil\TranslationBundle\Application\Translations;
use Phil\TranslationBundle\Infrastructure\Memory\InMemoryCommentRepository;
use Phil\TranslationBundle\Infrastructure\Memory\InMemorySegmentRepository;
use Phil\TranslationBundle\Infrastructure\Memory\InMemoryTermRepository;
use Phil\TranslationMemory\Memory\InMemoryStore;
use Phil\TranslationMemory\Memory\TranslationMemory;
use Phil\TranslationMemory\Segmentation\Granularity;
use Phil\TranslationMemory\Segmentation\UjoSegmenter;
use Phil\TranslationMemory\Variants;
use Symfony\Component\Clock\MockClock;

/**
 * The bundle's services on stores in memory, at a fixed time, with the translators of the test.
 */
final class Stores
{
    public InMemorySegmentRepository $segments;

    public InMemoryTermRepository $terms;

    public InMemoryStore $memory;

    public InMemoryCommentRepository $commentRecords;

    public Comments $comments;

    public Translators $translators;

    public Glossaries $glossaries;

    public Translations $translations;

    public MockClock $clock;

    public function __construct(bool $prefill = true, Granularity $granularity = Granularity::Block, ?MachineTranslator $machine = null, Variants $variants = new Variants(), ?TranslationPolicy $policy = null)
    {
        $this->segments = new InMemorySegmentRepository();
        $this->terms = new InMemoryTermRepository();
        $this->memory = new InMemoryStore();
        $this->commentRecords = new InMemoryCommentRepository();
        $this->translators = new Translators();
        $this->glossaries = new Glossaries($this->terms);
        $this->clock = new MockClock('2026-11-06 10:00:00.5 UTC');
        $this->comments = new Comments($this->commentRecords, $this->clock, $this->translators);
        $this->translations = new Translations($this->segments, new TranslationMemory($this->memory, variants: $variants), $this->glossaries, new UjoSegmenter($granularity), $this->clock, $prefill, $variants, $this->translators, $machine, policy: $policy);
    }
}
