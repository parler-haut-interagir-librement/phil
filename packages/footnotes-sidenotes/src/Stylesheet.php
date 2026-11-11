<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes;

/**
 * The stylesheets of the package, to be served or inlined by the application.
 */
enum Stylesheet: string
{
    /** Screen: the notes region, and margin notes with their narrow toggle. */
    case Screen = 'notes.css';

    /** Print: the notes at the foot of the page, in CSS GCPM. */
    case Print = 'notes-print.css';

    /** Print, after the print sheet: the footnote counter of the engine, starting again on each natural page. */
    case PrintPerPage = 'notes-print-per-page.css';

    /**
     * The absolute path of the file.
     */
    public function path(): string
    {
        return \dirname(__DIR__).'/resources/css/'.$this->value;
    }

    /**
     * The text of the sheet, for an application that inlines it.
     */
    public function contents(): string
    {
        return (string) file_get_contents($this->path());
    }
}
