<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Tests\Unit\Translation;

use Phil\BrailleExport\Contraction;
use Phil\BrailleExport\Exception\UnsupportedTranslation;
use Phil\BrailleExport\Tests\Unit\Fixture\Ascii;
use Phil\BrailleExport\Tests\Unit\Fixture\LetterTranslator;
use Phil\BrailleExport\Translation\ChainTranslator;
use Phil\BrailleExport\Translation\UncontractedTranslator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChainTranslator::class)]
final class ChainTranslatorTest extends TestCase
{
    public function testTheFirstTranslatorThatSupportsTheLanguageTranslates(): void
    {
        $unsupported = new LetterTranslator(false);
        $first = new LetterTranslator();
        $second = new LetterTranslator();
        $chain = new ChainTranslator($unsupported, $first, $second);

        self::assertTrue($chain->supports('fr', Contraction::Contracted));
        self::assertSame(['AB'], Ascii::rows($chain->translate(['ab'], 'fr', Contraction::Contracted)));
        self::assertSame([], $unsupported->batches);
        self::assertSame([['ab']], $first->batches);
        self::assertSame([], $second->batches);
    }

    public function testTheFallbackTakesWhatTheOthersCannot(): void
    {
        $chain = new ChainTranslator(new LetterTranslator(false), new UncontractedTranslator());

        self::assertSame(['.A'], Ascii::rows($chain->translate(['A'], 'fr', Contraction::Uncontracted)));
        self::assertFalse($chain->supports('fr', Contraction::Contracted));
    }

    public function testNoTranslatorSupportsTheLanguage(): void
    {
        $chain = new ChainTranslator(new LetterTranslator(false));

        self::assertFalse($chain->supports('de', Contraction::Uncontracted));

        $this->expectException(UnsupportedTranslation::class);
        $this->expectExceptionMessageIsOrContains('No translator writes uncontracted braille for the language "de".');

        $chain->translate(['a'], 'de', Contraction::Uncontracted);
    }

    public function testAnEmptyChainSupportsNothing(): void
    {
        self::assertFalse(new ChainTranslator()->supports('en', Contraction::Uncontracted));
    }
}
