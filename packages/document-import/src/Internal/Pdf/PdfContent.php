<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Pdf;

use Phil\DocumentImport\Exception\LimitExceeded;
use Phil\DocumentImport\Internal\Builder;
use Phil\DocumentImport\Media;
use Phil\DocumentImport\Omitted;

/**
 * The content streams of a page interpreted for what they show: the text state and the text and graphics matrices
 * followed through `BT`, `Tf`, `Td`, `TD`, `Tm`, `T*`, `Tj`, `TJ`, `'`, `"`, `q`, `Q` and `cm`, form XObjects
 * entered, image XObjects taken out, the text of artifacts — running headers, page numbers — left out. Each string
 * becomes a run placed on the page.
 *
 * @internal
 */
final class PdfContent
{
    /** How many graphics states `q` may save. */
    private const int STATES = 256;

    /** @var list<PdfRun> */
    private array $runs = [];

    /** @var array<int, PdfFont> */
    private array $fonts = [];

    /** @var list<float> */
    private array $ctm = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];

    /** @var list<list<float>> */
    private array $saved = [];

    /** @var list<float> */
    private array $tm = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];

    /** @var list<float> */
    private array $tlm = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];

    private ?PdfFont $font = null;

    private float $size = 12.0;

    private float $charSpacing = 0.0;

    private float $wordSpacing = 0.0;

    private float $scale = 1.0;

    private float $leading = 0.0;

    private float $rise = 0.0;

    /** How many marked-content sequences are open since an artifact began, the artifact's included. */
    private int $artifact = 0;

    public function __construct(
        private readonly PdfDocument $document,
        private readonly PdfImages $images,
        private readonly Builder $out,
        private readonly int $depth,
        private readonly PdfBudget $budget,
    ) {
    }

    /**
     * The runs the content of a page shows.
     *
     * @return list<PdfRun>
     *
     * @throws LimitExceeded
     */
    public function page(string $content, PdfDictionary $resources): array
    {
        $this->runs = [];
        $this->ctm = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
        $this->saved = [];
        $this->artifact = 0;
        $this->interpret($content, $resources, 0);

        return $this->runs;
    }

    /**
     * @throws LimitExceeded
     */
    private function interpret(string $content, PdfDictionary $resources, int $level): void
    {
        foreach (new PdfParser($content, 0, $this->depth)->operations() as [$operator, $operands]) {
            $this->budget->spend(1);
            $this->operate($operator, $operands, $resources, $level);
        }
    }

    /**
     * @param list<mixed> $operands
     *
     * @throws LimitExceeded
     */
    private function operate(string $operator, array $operands, PdfDictionary $resources, int $level): void
    {
        $numbers = $this->numbers($operands);

        match ($operator) {
            'q' => \count($this->saved) < self::STATES ? $this->saved[] = $this->ctm : null,
            'Q' => $this->ctm = array_pop($this->saved) ?? $this->ctm,
            'cm' => 6 === \count($numbers) ? $this->ctm = $this->multiply($numbers, $this->ctm) : null,
            'BT' => $this->tm = $this->tlm = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0],
            'Tf' => $this->setFont($operands, $resources),
            'Tc' => $this->charSpacing = $numbers[0] ?? 0.0,
            'Tw' => $this->wordSpacing = $numbers[0] ?? 0.0,
            'Tz' => $this->scale = ($numbers[0] ?? 100.0) / 100,
            'TL' => $this->leading = $numbers[0] ?? 0.0,
            'Ts' => $this->rise = $numbers[0] ?? 0.0,
            'Td' => $this->move($numbers[0] ?? 0.0, $numbers[1] ?? 0.0),
            'TD' => $this->moveAndLead($numbers[0] ?? 0.0, $numbers[1] ?? 0.0),
            'Tm' => 6 === \count($numbers) ? $this->tm = $this->tlm = $numbers : null,
            'T*' => $this->move(0.0, -$this->leading),
            'Tj' => $this->show($operands[0] ?? null),
            'TJ' => $this->showArray($operands[0] ?? null),
            "'" => $this->nextLine($operands[0] ?? null),
            '"' => $this->spacedLine($numbers, $operands[2] ?? null),
            'Do' => $this->xobject($operands[0] ?? null, $resources, $level),
            'BMC', 'BDC' => $this->beginMarked($operands[0] ?? null),
            'EMC' => $this->endMarked(),
            'BI' => $this->out->report->add(Omitted::Image, 'inline'),
            default => null,
        };
    }

    /**
     * @param list<mixed> $operands
     *
     * @throws LimitExceeded
     */
    private function setFont(array $operands, PdfDictionary $resources): void
    {
        $name = $operands[0] ?? null;
        $size = $operands[1] ?? null;
        $this->size = \is_int($size) || \is_float($size) ? (float) $size : $this->size;

        if (!$name instanceof PdfName) {
            return;
        }

        $reference = $this->document->dictionary($resources->get('Font'))->get($name->name);
        $font = $this->document->resolve($reference);

        if (!$font instanceof PdfDictionary) {
            $this->font = null;

            return;
        }

        $key = $reference instanceof PdfReference ? $reference->number : -spl_object_id($font);
        $this->font = $this->fonts[$key] ??= new PdfFont($font, $this->document);
    }

    private function move(float $x, float $y): void
    {
        $this->tm = $this->multiply([1.0, 0.0, 0.0, 1.0, $x, $y], $this->tlm);
        $this->tlm = $this->tm;
    }

    private function moveAndLead(float $x, float $y): void
    {
        $this->leading = -$y;
        $this->move($x, $y);
    }

    private function nextLine(mixed $string): void
    {
        $this->move(0.0, -$this->leading);
        $this->show($string);
    }

    /**
     * @param list<float> $numbers
     */
    private function spacedLine(array $numbers, mixed $string): void
    {
        $this->wordSpacing = $numbers[0] ?? 0.0;
        $this->charSpacing = $numbers[1] ?? 0.0;
        $this->nextLine($string);
    }

    private function showArray(mixed $items): void
    {
        foreach (\is_array($items) ? $items : [] as $item) {
            if (\is_string($item)) {
                $this->show($item);

                continue;
            }

            if (\is_int($item) || \is_float($item)) {
                $this->tm = $this->translated(-$item / 1000 * $this->size * $this->scale, $this->tm);

                if ($item < -200) {
                    $this->emit(' ', 0.0);
                }
            }
        }
    }

    /**
     * A string shown in the current font: its text placed where the text matrix stands, which moves past it.
     */
    private function show(mixed $string): void
    {
        if (!\is_string($string) || !$this->font instanceof PdfFont) {
            return;
        }

        $text = '';
        $advance = 0.0;

        foreach ($this->font->glyphs($string) as [$glyph, $width, $space]) {
            $text .= $glyph;
            $advance += ($width / 1000 * $this->size + $this->charSpacing + ($space ? $this->wordSpacing : 0.0)) * $this->scale;
        }

        $this->emit($text, $advance);
    }

    /**
     * A run from the origin of the text space, raised by the rise, to the same point once the text matrix moved past
     * it; its size is the font size scaled by the matrices, or the font size alone when they flatten the text.
     */
    private function emit(string $text, float $advance): void
    {
        $start = $this->multiply($this->tm, $this->ctm);
        $this->tm = $this->translated($advance, $this->tm);
        $end = $this->multiply($this->tm, $this->ctm);

        if (0 < $this->artifact || '' === $text || !$this->font instanceof PdfFont) {
            return;
        }

        $size = abs($this->size) * hypot($start[2], $start[3]);
        $this->runs[] = new PdfRun(
            $text,
            $this->rise * $start[2] + $start[4],
            $this->rise * $end[2] + $end[4],
            $this->rise * $start[3] + $start[5],
            $size > 0 ? $size : abs($this->size),
            $this->font->bold,
            $this->font->italic,
        );
    }

    /**
     * Operands or the entries of an array as numbers, 0 for what is no number.
     *
     * @param array<array-key, mixed> $values
     *
     * @return list<float>
     */
    private function numbers(array $values): array
    {
        $numbers = [];

        foreach ($values as $value) {
            $numbers[] = \is_int($value) || \is_float($value) ? (float) $value : 0.0;
        }

        return $numbers;
    }

    /**
     * A matrix moved along its own horizontal axis.
     *
     * @param list<float> $matrix
     *
     * @return list<float>
     */
    private function translated(float $distance, array $matrix): array
    {
        return [$matrix[0], $matrix[1], $matrix[2], $matrix[3], $distance * $matrix[0] + $matrix[4], $distance * $matrix[1] + $matrix[5]];
    }

    /**
     * A form XObject entered with its own resources and matrix, or an image taken out.
     *
     * @throws LimitExceeded
     */
    private function xobject(mixed $name, PdfDictionary $resources, int $level): void
    {
        if (!$name instanceof PdfName || $level >= min(32, $this->depth)) {
            return;
        }

        $object = $this->document->resolve($this->document->dictionary($resources->get('XObject'))->get($name->name));

        if (!$object instanceof PdfStream) {
            return;
        }

        if ('Image' === $object->dictionary->name('Subtype')) {
            $media = 0 < $this->artifact ? null : $this->images->image($object);

            if ($media instanceof Media) {
                $this->runs[] = new PdfRun('', $this->ctm[4], $this->ctm[4] + $this->ctm[0], $this->ctm[5] + $this->ctm[3], abs($this->ctm[3]), image: $media);
            }

            return;
        }

        $saved = [$this->ctm, $this->tm, $this->tlm];
        $matrix = $this->document->resolve($object->dictionary->get('Matrix'));

        if (\is_array($matrix) && 6 === \count($matrix)) {
            $this->ctm = $this->multiply($this->numbers($matrix), $this->ctm);
        }

        $inner = $this->document->dictionary($object->dictionary->get('Resources'));
        $this->interpret($this->document->decode($object)[0], [] === $inner->entries ? $resources : $inner, $level + 1);
        [$this->ctm, $this->tm, $this->tlm] = $saved;
    }

    private function beginMarked(mixed $tag): void
    {
        if (0 < $this->artifact || ($tag instanceof PdfName && 'Artifact' === $tag->name)) {
            ++$this->artifact;
        }
    }

    /**
     * The end of a sequence: of the artifact or of one inside it while an artifact is open — sequences nest —, of
     * nothing to count otherwise, an end without beginning included.
     */
    private function endMarked(): void
    {
        $this->artifact = max(0, $this->artifact - 1);
    }

    /**
     * @param list<float> $first
     * @param list<float> $second
     *
     * @return list<float>
     */
    private function multiply(array $first, array $second): array
    {
        return [
            $first[0] * $second[0] + $first[1] * $second[2],
            $first[0] * $second[1] + $first[1] * $second[3],
            $first[2] * $second[0] + $first[3] * $second[2],
            $first[2] * $second[1] + $first[3] * $second[3],
            $first[4] * $second[0] + $first[5] * $second[2] + $second[4],
            $first[4] * $second[1] + $first[5] * $second[3] + $second[5],
        ];
    }
}
