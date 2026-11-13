<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Template;

use Phil\PagedMedia\Exception\InvalidDocument;
use Phil\PagedMedia\Pdf\Color;
use Phil\PagedMedia\Pdf\Conformance;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Metadata;
use Phil\PagedMedia\Pdf\PageSize;

/**
 * A printed calendar, a month per page: the title, the names of the days, then a grid of weeks starting on the day the
 * reader's custom starts them, each day with its number and what it holds, cut to what its square shows.
 */
final readonly class Calendar
{
    private const float MARGIN = 36;

    private PageSize $page;

    /**
     * @param list<string>  $weekdays the names of the seven days, from the first day of the week, as the reader reads them
     * @param int           $firstDay the first day of the week, 1 for Monday to 7 for Sunday
     * @param PageSize|null $page     a Letter page in landscape when none is given
     */
    public function __construct(
        private array $weekdays,
        private int $firstDay = 1,
        ?PageSize $page = null,
        private TemplateFonts $fonts = new TemplateFonts(),
        private ?Color $accent = null,
        private Metadata $metadata = new Metadata(),
        private ?Conformance $conformance = null,
    ) {
        if (7 !== \count($weekdays)) {
            throw InvalidDocument::because('A calendar names the seven days of the week.');
        }

        if ($firstDay < 1 || $firstDay > 7) {
            throw InvalidDocument::because(\sprintf('The first day of a week is from 1, Monday, to 7, Sunday; %d is not.', $firstDay));
        }

        $this->page = $page ?? PageSize::letter()->landscape();
    }

    /**
     * @param list<CalendarMonth> $months
     */
    public function render(array $months): Document
    {
        $document = new Document($this->page, $this->metadata, conformance: $this->conformance);

        foreach ($months as $month) {
            $document->addPage();
            $this->month($document, $month);
        }

        return $document;
    }

    private function month(Document $document, CalendarMonth $month): void
    {
        $width = $this->page->width - 2 * self::MARGIN;
        $top = $this->page->height - self::MARGIN;
        Layout::line($document, self::MARGIN, $top - 24, $width, $month->title, 24, 12, $this->fonts->bold, true);
        $offset = ((int) $month->first()->format('N') - $this->firstDay + 7) % 7;
        $weeks = intdiv($offset + $month->days() + 6, 7);
        $column = $width / 7;
        $header = $top - 44;
        $cell = ($header - 6 - self::MARGIN) / $weeks;
        $accent = $this->accent ?? Color::gray(64);

        foreach ($this->weekdays as $index => $name) {
            $document->rectangle(self::MARGIN + $index * $column, $header - 4, $column, 18, $accent);
            Layout::line($document, self::MARGIN + $index * $column + 2, $header + 1, $column - 4, $name, 10, 6, $this->fonts->bold, true, Color::gray(255));
        }

        for ($place = 0; $place < 7 * $weeks; ++$place) {
            $x = self::MARGIN + ($place % 7) * $column;
            $y = $header - 6 - (intdiv($place, 7) + 1) * $cell;
            $document->rectangle($x, $y, $column, $cell, stroke: Color::gray(160), lineWidth: 0.5);
            $day = $place - $offset + 1;

            if ($day < 1 || $day > $month->days()) {
                continue;
            }

            $document->text($x + 4, $y + $cell - 12, (string) $day, 10, $this->fonts->bold);
            $baseline = $y + $cell - 24;

            foreach ($month->events[$day] ?? [] as $event) {
                if ($baseline < $y + 3) {
                    break;
                }

                Layout::line($document, $x + 4, $baseline, $column - 8, $event, 7, 5, $this->fonts->regular);
                $baseline -= 9;
            }
        }
    }
}
