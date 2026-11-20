<?php

declare(strict_types=1);

namespace Phil\BrailleExportLiblouis\Tests\Integration;

use Phil\BrailleExport\BrailleExporter;
use Phil\BrailleExport\Contraction;
use Phil\BrailleExport\Document\Document;
use Phil\BrailleExport\Document\Heading;
use Phil\BrailleExport\Document\Paragraph;
use Phil\BrailleExport\Output\BrfWriter;
use Phil\BrailleExport\Translation\Braille;
use Phil\BrailleExportLiblouis\Exception\LiblouisException;
use Phil\BrailleExportLiblouis\Exception\TableNotFound;
use Phil\BrailleExportLiblouis\LiblouisTables;
use Phil\BrailleExportLiblouis\LiblouisTranslator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;

/**
 * The real `lou_translate`, on a machine that has liblouis 3.39.0 or later; skipped elsewhere.
 */
#[CoversClass(LiblouisTranslator::class)]
#[CoversClass(LiblouisTables::class)]
#[CoversClass(TableNotFound::class)]
#[Group('liblouis')]
final class LiblouisCommandTest extends TestCase
{
    private LiblouisTranslator $translator;

    protected function setUp(): void
    {
        if (null === new ExecutableFinder()->find('lou_translate')) {
            self::markTestSkipped('The lou_translate program of liblouis is not installed.');
        }

        $this->translator = new LiblouisTranslator();

        try {
            $version = $this->translator->version();
        } catch (LiblouisException $liblouisException) {
            self::markTestSkipped('The lou_translate program of liblouis does not give its version: '.$liblouisException->getMessage());
        }

        if (version_compare($version, LiblouisTranslator::MINIMUM_VERSION, '<')) {
            self::markTestSkipped(\sprintf('liblouis %s is older than %s, which the translator refuses.', $version, LiblouisTranslator::MINIMUM_VERSION));
        }
    }

    public function testTheVersionIsRead(): void
    {
        self::assertMatchesRegularExpression('/^\d+\.\d+/', $this->translator->version());
    }

    public function testEveryDefaultTableTranslates(): void
    {
        foreach (['en' => 'The quick brown fox, 1943.', 'fr' => "L'été de la forêt, 1943."] as $language => $text) {
            foreach (Contraction::cases() as $contraction) {
                [$braille] = $this->translator->translate([$text], $language, $contraction);

                self::assertTrue(Braille::isBraille($braille), $language.' '.$contraction->value);
                self::assertNotSame('', $braille);
            }
        }
    }

    public function testContractedEnglishIsShorter(): void
    {
        $text = 'The children were standing with their mother and father.';
        [$contracted] = $this->translator->translate([$text], 'en', Contraction::Contracted);
        [$uncontracted] = $this->translator->translate([$text], 'en', Contraction::Uncontracted);

        self::assertLessThan(Braille::length($uncontracted), Braille::length($contracted));
    }

    public function testABatchKeepsItsOrder(): void
    {
        $braille = $this->translator->translate(['a', '', 'b', str_repeat('word ', 400)], 'en', Contraction::Uncontracted);

        self::assertCount(4, $braille);
        self::assertSame('', $braille[1]);
        self::assertNotSame($braille[0], $braille[2]);
        self::assertGreaterThan(1000, Braille::length($braille[3]));
    }

    public function testADocumentIsLaidOutAndWrittenAsBrf(): void
    {
        $book = new BrailleExporter($this->translator)->layout(new Document([
            new Heading('Chapitre premier'),
            new Paragraph("Lorsque j'avais six ans, j'ai vu une magnifique image."),
        ], 'fr'), Contraction::Contracted);

        self::assertNotSame('', new BrfWriter()->write($book));
    }

    public function testAMissingTableIsReported(): void
    {
        $translator = new LiblouisTranslator(new LiblouisTables(['xx' => ['contracted' => 'phil-no-such-table.ctb']]));

        $this->expectException(TableNotFound::class);

        $translator->translate(['a'], 'xx', Contraction::Contracted);
    }
}
