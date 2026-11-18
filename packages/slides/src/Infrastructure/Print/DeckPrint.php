<?php

declare(strict_types=1);

namespace Phil\Slides\Infrastructure\Print;

use Phil\PagedMedia\Css\PageRule;
use Phil\Slides\Domain\NotesPageSize;
use Phil\Slides\Domain\ValueObject\Deck;
use Twig\Environment;
use Twig\Error\Error;

/**
 * A deck as a page for a paged-media engine: one page per slide, the size of a slide, or, with the notes, an upright
 * page per slide that holds the slide and its notes under it. The `@page` rule is written by `phil/paged-media`; the
 * body is the template `@PhilSlides/print.html.twig`. Notes are left out unless asked for.
 */
final readonly class DeckPrint
{
    public const string TEMPLATE = '@PhilSlides/print.html.twig';

    /** The margin of a page of notes, in points: half an inch. */
    public const float NOTES_MARGIN = 36.0;

    private const string STYLESHEET = __DIR__.'/../../../assets/styles/slides-print.css';

    public function __construct(
        private Environment $twig,
        private NotesPageSize $notesPageSize = NotesPageSize::Letter,
    ) {
    }

    /**
     * The HTML of the body, already purified by the renderer of `phil/ujo`.
     *
     * @throws Error when the template fails
     */
    public function body(Deck $deck, bool $withNotes = false): string
    {
        return $this->twig->render(self::TEMPLATE, ['deck' => $deck, 'with_notes' => $withNotes]);
    }

    /**
     * The stylesheets, in the order of the cascade: the `@page` rule, then the layout of the slides.
     *
     * @return list<string>
     */
    public function stylesheets(Deck $deck, bool $withNotes = false): array
    {
        $rule = $withNotes
            ? new PageRule($this->notesPageSize->pageSize(), [self::NOTES_MARGIN])
            : new PageRule($deck->settings->aspectRatio->pageSize(), [0]);

        return [$rule->css(), (string) file_get_contents(self::STYLESHEET)];
    }

    /**
     * The whole document, for an engine the application runs itself.
     *
     * @throws Error when the template fails
     */
    public function document(Deck $deck, bool $withNotes = false): string
    {
        $head = '<meta charset="utf-8">';

        if (null !== $deck->settings->title) {
            $head .= '<title>'.htmlspecialchars($deck->settings->title, \ENT_QUOTES).'</title>';
        }

        foreach ($this->stylesheets($deck, $withNotes) as $stylesheet) {
            $head .= '<style>'.$stylesheet.'</style>';
        }

        return '<!doctype html><html lang="'.htmlspecialchars($this->language($deck), \ENT_QUOTES).'"><head>'.$head.'</head><body>'.$this->body($deck, $withNotes).'</body></html>';
    }

    /**
     * The language of the deck, English when its source names none.
     */
    public function language(Deck $deck): string
    {
        return $deck->settings->language ?? 'en';
    }
}
