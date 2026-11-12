<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use Override;
use Phil\Markup\Level;
use Phil\Markup\Reader\Contract\ReaderProvider;
use Phil\Markup\Reader\Loss;
use Phil\Markup\Reader\LossReason;
use Phil\Markup\Reader\MarkupReader;
use Phil\Markup\Reader\Reading;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Normalizer;

/**
 * The reader of an add-on format, `levelled`, that keeps a source whole at `Level::All` and loses its HTML below: what
 * a conversion loses then depends on the level the source is read at.
 */
final class LevelledReaders implements ReaderProvider
{
    #[Override]
    public function reader(Level $level, Normalizer $normalizer): MarkupReader
    {
        return new readonly class(Level::All === $level) implements MarkupReader {
            public function __construct(
                private bool $all,
            ) {
            }

            #[Override]
            public function read(string $source): Reading
            {
                $document = new Document([new Block('b1', 'paragraph', ['text' => htmlspecialchars($source, \ENT_QUOTES)])]);

                return new Reading($document, losses: $this->all ? [] : [new Loss('raw', 'levelled', LossReason::UnknownElement)]);
            }
        };
    }
}
