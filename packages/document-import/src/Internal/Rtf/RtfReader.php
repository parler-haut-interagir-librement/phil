<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Rtf;

use Phil\DocumentImport\Exception\InvalidDocument;
use Phil\DocumentImport\Exception\LimitExceeded;
use Phil\DocumentImport\Internal\Builder;
use Phil\DocumentImport\Internal\Codepage;
use Phil\DocumentImport\Internal\Mark;
use Phil\DocumentImport\Limits;
use Phil\DocumentImport\Media;
use Phil\DocumentImport\Omitted;

/**
 * An RTF document read into blocks: its groups walked token by token, the character formatting of each group, the
 * paragraphs with their style, outline level, list and alignment, the tables row by row, the footnotes and endnotes,
 * the hyperlink and table of contents fields, the PNG and JPEG pictures, the style sheet and the document information.
 * Fonts, colours, headers and footers, comments, objects and shapes are reported, never run.
 *
 * @internal
 */
final class RtfReader
{
    /** The destinations whose content is never text. */
    private const array SKIPPED = [
        'fonttbl', 'colortbl', 'listtable', 'listoverridetable', 'revtbl', 'rsidtbl', 'generator', 'filetbl', 'pgdsctbl',
        'xmlnstbl', 'themedata', 'colorschememapping', 'latentstyles', 'datastore', 'bkmkstart', 'bkmkend', 'xe', 'tc',
        'txe', 'rxe', 'template', 'nonshppict', 'userprops', 'docvar', 'pnseclvl', 'pntxta', 'pntxtb', 'fldtype',
        'wgrffmtfilter', 'mmathPr', 'atnid', 'atnauthor', 'atndate', 'atnref', 'atrfstart', 'atrfend', 'protusertbl',
        'listpicture', 'aftncn', 'aftnsep', 'aftnsepc', 'ftncn', 'ftnsep', 'ftnsepc', 'falt', 'panose', 'blipuid',
    ];

    private const array HEADERS = ['header' => 'header', 'headerl' => 'header', 'headerr' => 'header', 'headerf' => 'header', 'footer' => 'footer', 'footerl' => 'footer', 'footerr' => 'footer', 'footerf' => 'footer'];

    private const array INFO = ['title' => 'title', 'subject' => 'subject', 'author' => 'creator', 'keywords' => 'keywords', 'doccomm' => 'description'];

    private const array CHARACTERS = [
        'tab' => ' ', 'emdash' => '—', 'endash' => '–', 'emspace' => ' ', 'enspace' => ' ', 'qmspace' => ' ',
        'bullet' => '•', 'lquote' => '‘', 'rquote' => '’', 'ldblquote' => '“', 'rdblquote' => '”',
    ];

    private const array ALIGNMENTS = ['ql' => null, 'qc' => 'center', 'qr' => 'right', 'qj' => 'justify', 'qd' => 'justify'];

    private const array MARKS = ['b' => Mark::Bold, 'i' => Mark::Italic, 'strike' => Mark::Strikethrough, 'striked' => Mark::Strikethrough];

    private RtfState $state;

    /** @var list<RtfState> */
    private array $stack = [];

    private RtfContext $context;

    /** @var list<RtfContext> */
    private array $contexts = [];

    private readonly RtfStyles $styles;

    private string $bytes = '';

    private int $toSkip = 0;

    private ?int $surrogate = null;

    private string $codepage = '1252';

    /** @var array<string, string> */
    private array $metadata = [];

    private int $pictures = 0;

    private int $captured = 0;

    public function __construct(
        private readonly string $rtf,
        private readonly Builder $out,
        private readonly Limits $limits,
    ) {
        $this->state = new RtfState();
        $this->context = new RtfContext(true);
        $this->styles = new RtfStyles($out->styles);
    }

    /**
     * The document read into the builder, and its information as metadata.
     *
     * @return array<string, string>
     *
     * @throws InvalidDocument when the file does not start as an RTF document
     * @throws LimitExceeded   when groups nest deeper than the limits allow, or destinations hold more than twice the entry limit
     */
    public function read(): array
    {
        if (1 !== preg_match('/^\{\\\\rtf\d/', ltrim($this->rtf, "\u{FEFF} \t\r\n"))) {
            throw InvalidDocument::because('The file is not an RTF document: it does not start with "{\rtf".');
        }

        foreach (RtfLexer::tokens($this->rtf, $this->limits->entryBytes) as [$type, $value, $parameter]) {
            if (RtfLexer::TEXT === $type || RtfLexer::BYTE === $type) {
                $this->bytes($value);

                continue;
            }

            $this->flushBytes();
            $this->toSkip = RtfLexer::WORD === $type && 'u' === $value ? $this->toSkip : 0;

            match ($type) {
                RtfLexer::OPEN => $this->open(),
                RtfLexer::CLOSE => $this->close(),
                RtfLexer::WORD => $this->word($value, $parameter),
                RtfLexer::SYMBOL => $this->symbol($value),
                default => $this->binary($value),
            };
        }

        $this->flushBytes();

        while ([] !== $this->stack) {
            $this->close();
        }

        $this->endParagraph();
        $this->flushTable();

        return $this->metadata;
    }

    /**
     * @throws LimitExceeded
     */
    private function open(): void
    {
        if (\count($this->stack) >= $this->limits->depth) {
            throw LimitExceeded::because(\sprintf('The RTF document nests groups deeper than %d.', $this->limits->depth));
        }

        $this->stack[] = $this->state;
        $child = $this->state->child();

        if ('stylesheet' === $this->state->capture?->name) {
            $child->capture = new RtfCapture('style', $this->state->capture);
        }

        $this->state = $child;
    }

    private function close(): void
    {
        $closing = $this->state;
        $this->state = array_pop($this->stack) ?? new RtfState();

        if ($closing->capture instanceof RtfCapture && $closing->capture !== $this->state->capture) {
            $this->captured($closing->capture);
        }

        if ($closing->note) {
            $this->closeNote();
        }
    }

    /**
     * Raw text or a byte written as `\'hh`, in the code page of the document, after the fallback characters of a
     * `\uN` are skipped.
     */
    private function bytes(string $bytes): void
    {
        $this->state->opening = false;

        if ($this->toSkip > 0) {
            $skipped = min($this->toSkip, \strlen($bytes));
            $this->toSkip -= $skipped;
            $bytes = substr($bytes, $skipped);
        }

        $this->bytes .= $bytes;
    }

    private function flushBytes(): void
    {
        if ('' !== $this->bytes) {
            $this->emit(Codepage::decode($this->bytes, $this->codepage));
            $this->bytes = '';
        }
    }

    /**
     * @throws LimitExceeded
     */
    private function emit(string $text): void
    {
        $state = $this->state;

        if ($state->skip || '' === $text) {
            return;
        }

        if ($state->capture instanceof RtfCapture) {
            $this->capture($state->capture, $text);

            return;
        }

        if ($state->hidden) {
            $this->out->report->add(Omitted::HiddenText);

            return;
        }

        $this->context->inline->text($text, Mark::tags($state->marks(), $state->link));
    }

    /**
     * @throws LimitExceeded
     */
    private function capture(RtfCapture $capture, string $text): void
    {
        $this->captured += \strlen($text);

        if ($this->captured > 2 * $this->limits->entryBytes) {
            throw LimitExceeded::because(\sprintf('The RTF document holds more than %d bytes outside its text.', 2 * $this->limits->entryBytes));
        }

        $capture->text .= $text;
    }

    private function binary(string $bytes): void
    {
        if ('pict' === $this->state->capture?->name) {
            $this->state->capture->properties['binary'] = $bytes;
        }
    }

    private function symbol(string $symbol): void
    {
        if ('*' === $symbol) {
            $this->state->ignorable = true;

            return;
        }

        $this->state->opening = false;

        match ($symbol) {
            '~' => $this->emit("\u{A0}"),
            '-' => $this->emit("\u{AD}"),
            '_' => $this->emit("\u{2011}"),
            default => null,
        };
    }

    private function word(string $word, ?int $parameter): void
    {
        $opening = $this->state->opening;
        $this->state->opening = false;

        if ($this->state->skip || ($opening && $this->destination($word))) {
            return;
        }

        $capture = $this->state->capture;
        $styleKind = 'style' === $capture?->name && \in_array($word, ['cs', 'ds', 'ts'], true);

        if ($opening && $this->state->ignorable && !$styleKind) {
            $this->state->skip = true;

            return;
        }

        if ($capture instanceof RtfCapture && 'u' !== $word && 'uc' !== $word) {
            $this->captureWord($capture, $word, $parameter);

            return;
        }

        $this->control($word, $parameter);
    }

    /**
     * A control word that opens a destination: handled when the reader knows it.
     */
    private function destination(string $word): bool
    {
        $state = $this->state;
        $info = 'info' === $state->capture?->name;

        if (\in_array($word, self::SKIPPED, true) || ($info && !isset(self::INFO[$word]))) {
            $state->skip = true;

            return true;
        }

        $capture = match (true) {
            $info => new RtfCapture(self::INFO[$word]),
            'stylesheet' === $word, 'info' === $word => new RtfCapture($word),
            isset(self::HEADERS[$word]) => new RtfCapture(self::HEADERS[$word]),
            'pict' === $word => new RtfCapture('pict'),
            'picprop' === $word, 'sn' === $word, 'sv' === $word => new RtfCapture($word, $state->capture),
            'listtext' === $word, 'pntext' === $word, 'fldinst' === $word => new RtfCapture('pntext' === $word ? 'listtext' : $word),
            default => null,
        };

        if ($capture instanceof RtfCapture) {
            $state->capture = $capture;

            return true;
        }

        return match ($word) {
            'field' => $this->openField(),
            'fldrslt' => $this->fieldResult(),
            'footnote' => $this->openNote(),
            'annotation' => $this->leaveOut(Omitted::Comment, ''),
            'object' => $this->leaveOut(Omitted::EmbeddedObject, ''),
            'shp', 'do' => $this->leaveOut(Omitted::Drawing, 'shape'),
            'shptxt' => $this->leaveOut(Omitted::TextBox, ''),
            'shppict' => true,
            default => false,
        };
    }

    private function openField(): true
    {
        $this->state->field = new RtfField();

        return true;
    }

    private function leaveOut(Omitted $what, string $detail): true
    {
        $this->out->report->add($what, $detail);
        $this->state->skip = true;

        return true;
    }

    private function captureWord(RtfCapture $capture, string $word, ?int $parameter): void
    {
        match (true) {
            'style' === $capture->name && \in_array($word, ['s', 'cs', 'ds', 'ts', 'sbasedon', 'outlinelevel'], true) => $capture->properties[$word] = $parameter ?? 0,
            'pict' === $capture->name && \in_array($word, ['pngblip', 'jpegblip', 'emfblip', 'wmetafile', 'macpict', 'dibitmap', 'wbitmap', 'pmmetafile'], true) => $capture->properties['type'] = $word,
            default => null,
        };
    }

    /**
     * What a destination read until its group closed becomes.
     */
    private function captured(RtfCapture $capture): void
    {
        $text = $capture->text;

        match ($capture->name) {
            'style' => $this->styles->define($capture),
            'title', 'subject', 'creator', 'keywords', 'description' => '' === trim($text) ? null : $this->metadata[$capture->name] = trim($text),
            'header', 'footer' => '' === trim($text) ? null : $this->out->report->add(Omitted::HeaderFooter, $capture->name),
            'pict' => $this->picture($capture),
            'sn' => $capture->parent instanceof RtfCapture ? $capture->parent->properties['sn'] = trim($text) : null,
            'sv' => $capture->describePicture(),
            'listtext' => $this->context->marker = trim($text),
            'fldinst' => true === $this->state->field?->read($text) ? $this->out->report->add(Omitted::InternalLink) : null,
            default => null,
        };
    }

    private function fieldResult(): true
    {
        $field = $this->state->field;

        if (true === $field?->toc) {
            $this->endParagraph();
            $this->flushTable();
            $this->out->toc('');
            $this->state->skip = true;
        } else {
            $this->state->link = $field->link ?? $this->state->link;
        }

        return true;
    }

    private function openNote(): true
    {
        $this->state->note = true;
        $this->contexts[] = $this->context;
        $this->context = new RtfContext(false);

        return true;
    }

    private function closeNote(): void
    {
        $note = $this->context;
        $html = $note->take();
        $lines = '' === $html ? $note->lines : [...$note->lines, $html];
        $this->context = array_pop($this->contexts) ?? new RtfContext(true);
        $this->context->inline->html($this->out->note(implode('<br>', $lines), $note->endnote));
    }

    /**
     * A picture: PNG and JPEG become images, other kinds are reported.
     */
    private function picture(RtfCapture $capture): void
    {
        $type = $capture->properties['type'] ?? '';
        $extension = ['pngblip' => 'png', 'jpegblip' => 'jpg'][$type] ?? null;

        if (null === $extension) {
            $this->out->report->add(Omitted::Image, '' === $type ? 'unknown' : (string) $type);

            return;
        }

        $hex = (string) preg_replace('/[^0-9a-fA-F]/', '', $capture->text);
        $bytes = (string) ($capture->properties['binary'] ?? sodium_hex2bin(substr($hex, 0, \strlen($hex) - \strlen($hex) % 2)));

        $media = new Media('image-'.++$this->pictures.'.'.$extension, $bytes, (string) ($capture->properties['description'] ?? ''));

        if (!$this->context->body || $this->context->inTable) {
            $this->out->image($media, true);

            return;
        }

        $this->endParagraph();
        $this->flushTable();
        $this->out->image($media);
    }

    private function control(string $word, ?int $parameter): void
    {
        $context = $this->context;

        match (true) {
            'par' === $word, 'sect' === $word => $this->endParagraph(),
            'line' === $word => $context->inline->lineBreak(Mark::tags($this->state->marks(), $this->state->link)),
            'page' === $word => $this->pageBreak(),
            'cell' === $word => $context->body ? $context->endCell() : $this->emit(' '),
            'row' === $word => $context->body ? $context->endRow() : null,
            'nestcell' === $word => $this->emit(' '),
            'nestrow' === $word => $this->out->report->add(Omitted::NestedTable),
            'trhdr' === $word => $context->headerRow = true,
            'intbl' === $word => $context->inTable = true,
            'pard' === $word => $context->resetParagraph(),
            's' === $word => $context->style = $parameter ?? 0,
            'outlinelevel' === $word => $context->outline = $parameter,
            'ls' === $word => $context->list = $parameter,
            'ilvl' === $word => $context->level = null === $parameter ? 0 : max(0, min(8, $parameter)),
            \array_key_exists($word, self::ALIGNMENTS) => $context->alignment = self::ALIGNMENTS[$word],
            'ftnalt' === $word => $context->endnote = true,
            'u' === $word => $this->unicode($parameter ?? 0),
            'uc' === $word => $this->fallback($parameter),
            isset(self::CHARACTERS[$word]) => $this->emit(self::CHARACTERS[$word]),
            default => $this->format($word, $parameter),
        };
    }

    /**
     * How many characters follow each `\uN` as its fallback: 1 unless the document says otherwise.
     */
    private function fallback(?int $parameter): void
    {
        $count = $parameter ?? 1;
        $this->state->fallback = max(0, $count);
    }

    private function format(string $word, ?int $parameter): void
    {
        $state = $this->state;
        $on = 0 !== $parameter;

        match (true) {
            isset(self::MARKS[$word]) => $state->set(self::MARKS[$word], $on),
            'ulnone' === $word => $state->set(Mark::Underline, false),
            str_starts_with($word, 'ul') && !str_starts_with($word, 'ulc') => $state->set(Mark::Underline, $on),
            'super' === $word => $state->set(Mark::Superscript, $on),
            'sub' === $word => $state->set(Mark::Subscript, $on),
            'nosupersub' === $word => $this->noPosition(),
            'v' === $word => $state->hidden = $on,
            'highlight' === $word => $state->set(Mark::Marker, null !== $parameter && $parameter > 0),
            'cf' === $word && null !== $parameter && $parameter > 0 => $this->out->report->add(Omitted::Formatting, 'colour'),
            'plain' === $word => $this->plain(),
            'ansicpg' === $word => $this->codepage = (string) ($parameter ?? 1252),
            'mac' === $word => $this->codepage = Codepage::MAC_ROMAN,
            'pc' === $word, 'pca' === $word => $this->codepage = '850',
            default => null,
        };
    }

    private function noPosition(): void
    {
        $this->state->set(Mark::Superscript, false);
        $this->state->set(Mark::Subscript, false);
    }

    private function plain(): void
    {
        $this->state->marks = [];
        $this->state->hidden = false;
    }

    /**
     * A character given by its code, its surrogate pairs joined, and the fallback characters after it skipped.
     */
    private function unicode(int $code): void
    {
        $code = $code < 0 ? $code + 65536 : $code;
        $this->toSkip = $this->state->fallback;

        if ($code >= 0xD800 && $code <= 0xDBFF) {
            $this->surrogate = $code;

            return;
        }

        if ($code >= 0xDC00 && $code <= 0xDFFF) {
            $high = $this->surrogate;
            $this->surrogate = null;
            $this->emit(null === $high ? "\u{FFFD}" : Codepage::character(0x10000 + (($high - 0xD800) << 10) + ($code - 0xDC00)));

            return;
        }

        $this->surrogate = null;
        $this->emit(0 === $code ? '' : Codepage::character($code));
    }

    private function pageBreak(): void
    {
        if ($this->context->body) {
            $this->endParagraph();
            $this->flushTable();
            $this->out->pageBreak();
        }
    }

    /**
     * The paragraph read so far: a line of the note being read, a paragraph of the cell being read, or a block.
     */
    private function endParagraph(): void
    {
        $context = $this->context;
        $marker = $context->marker;
        $context->marker = null;

        if (!$context->body) {
            $html = $context->take();

            if ('' !== $html) {
                $context->lines[] = $html;
            }

            return;
        }

        if ($context->inTable) {
            $context->cell[] = $context->take();

            return;
        }

        $html = $context->take();

        if ('' === $html) {
            return;
        }

        $this->flushTable();
        $this->block($html, $marker);
    }

    private function block(string $html, ?string $marker): void
    {
        $context = $this->context;

        if ((null !== $context->list || null !== $marker) && !$this->styles->isMapped($context->style)) {
            $ordered = null !== $marker && 1 === preg_match('/^\(?[0-9ivxlcdmIVXLCDMa-zA-Z]{1,6}[.)]/', $marker);
            $this->out->listItem('rtf-'.($context->list ?? 0), $context->level, $html, $ordered);

            return;
        }

        $this->out->block($this->styles->role($context->style, $context->outline), $html, $context->alignment, $this->styles->name($context->style));
    }

    private function flushTable(): void
    {
        $context = $this->context;

        if ([] !== $context->row || [] !== $context->cell) {
            $context->endRow();
        }

        if ([] !== $context->rows) {
            $this->out->table($context->rows, $context->headings);
        }

        $context->rows = [];
        $context->headings = false;
    }
}
